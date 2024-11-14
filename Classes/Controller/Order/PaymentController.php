<?php

namespace Extcode\CartGirosolution\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Exception;
use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\ItemRepository as OrderItemRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends ActionController
{
    protected ?Cart $cart = null;

    protected array $cartPluginSettings;

    protected array $pluginSettings;

    public function __construct(
        private readonly PersistenceManager $persistenceManager,
        private readonly SessionHandler $sessionHandler,
        private readonly CartRepository $cartRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {}

    protected function initializeAction(): void
    {
        $this->cartPluginSettings =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->pluginSettings =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartGirosolution'
            );
    }

    public function redirectAction(): ResponseInterface
    {
        $this->cart = $this->loadCartByArgumentHash();

        if (is_null($this->cart)) {
            // todo: log and throw an exception
        }

        $orderItem = $this->cart->getOrderItem();

        if (GeneralUtility::_GET('gcResultPayment') === '4000') {
            $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'paid');

            if ($dispatchEvent) {
                $finishEvent = new FinishEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }

            $orderItemFromRepo = $this->orderItemRepository->findByUid($orderItem->getUid());

            return $this->redirect(
                'show',
                'Cart\Order',
                'Cart',
                ['orderItem' => $orderItemFromRepo]
            );
        }

        $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'canceled');

        $this->restoreCartSession();

        if ($dispatchEvent) {
            $orderItem = $this->cart->getOrderItem();
            $finishEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
            $this->eventDispatcher->dispatch($finishEvent);
        }

        $this->addFlashMessageToCartCart('tx_cartgirosolution.controller.order.payment.action.redirect.canceled');

        return $this->redirect(
            'show',
            'Cart\Cart',
            'Cart'
        );
    }

    public function notifyAction(): string
    {
        $this->cart = $this->loadCartByArgumentHash();

        if (is_null($this->cart)) {
            return 'ERROR';
        }

        $orderItem = $this->cart->getOrderItem();

        if (GeneralUtility::_GET('gcResultPayment') === '4000') {
            $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'paid');

            if ($dispatchEvent) {
                $orderItem = $this->cart->getOrderItem();
                $finishEvent = new FinishEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }
        } else {
            $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'canceled');

            if ($dispatchEvent) {
                $orderItem = $this->cart->getOrderItem();
                $finishEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }
        }

        return 'OK';
    }

    private function updatePaymentAndTransaction(Item $orderItem, string $status): bool
    {
        $dispatchEvent = false;

        $payment = $orderItem->getPayment();

        foreach ($payment->getTransactions() as $transaction) {
            if ($transaction->getTxnId() === GeneralUtility::_GET('gcReference')) {
                break;
            }
        }
        if (!isset($transaction) || !$transaction instanceof Transaction) {
            throw new Exception('No transation found!', 1731616906);
        }

        $transaction->setExternalStatusCode(GeneralUtility::_GET('gcResultPayment'));
        $transaction->setNote(GeneralUtility::_GET('gcBackendTxId'));

        if ($transaction->getStatus() !== $status) {
            $transaction->setStatus($status);
        }

        if ($payment->getStatus() !== $status) {
            $payment->setStatus($status);
            $dispatchEvent = true;
        }

        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        return $dispatchEvent;
    }

    private function addFlashMessageToCartCart(string $translationKey): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            LocalizationUtility::translate(
                $translationKey,
                'CartGirosolution'
            ),
            '',
            AbstractMessage::ERROR,
            true
        );

        $flashMessageService = new FlashMessageService();
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.tx_cart_cart');
        $messageQueue->enqueue($flashMessage);
    }

    private function loadCartByArgumentHash(): ?Cart
    {
        if ($this->request->hasArgument('hash')) {
            $hash = $this->request->getArgument('hash');

            if (!empty($hash)) {
                $querySettings = $this->cartRepository->createQuery()->getQuerySettings();
                $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
                $this->cartRepository->setDefaultQuerySettings($querySettings);

                $cart = $this->cartRepository->findOneBy(['sHash' => $hash]);
                if ($cart instanceof Cart) {
                    return $cart;
                }
            }
        }

        return null;
    }

    protected function restoreCartSession(): void
    {
        $cart = $this->cart->getCart();
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();
        $this->sessionHandler->writeCart($this->cartPluginSettings['settings']['cart']['pid'], $cart);
    }
}
