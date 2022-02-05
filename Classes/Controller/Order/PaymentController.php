<?php

namespace Extcode\CartGirosolution\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\ItemRepository as OrderItemRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends ActionController
{
    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Session Handler
     *
     * @var SessionHandler
     */
    protected $sessionHandler;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var OrderItemRepository
     */
    protected $orderItemRepository;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var array
     */
    protected $cartPluginSettings;

    /**
     * @var array
     */
    protected $pluginSettings;

    public function injectPersistenceManager(PersistenceManager $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function injectSessionHandler(SessionHandler $sessionHandler): void
    {
        $this->sessionHandler = $sessionHandler;
    }

    public function injectCartRepository(CartRepository $cartRepository): void
    {
        $this->cartRepository = $cartRepository;
    }

    public function injectPaymentRepository(PaymentRepository $paymentRepository): void
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function injectOrderItemRepository(OrderItemRepository $orderItemRepository): void
    {
        $this->orderItemRepository = $orderItemRepository;
    }

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

    public function redirectAction(): void
    {
        $this->loadCartByArgumentHash();

        if ($this->cart) {
            $orderItem = $this->cart->getOrderItem();

            if (GeneralUtility::_GET('gcResultPayment') === '4000') {
                $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'paid');

                if ($dispatchEvent) {
                    $finishEvent = new FinishEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                    $this->eventDispatcher->dispatch($finishEvent);
                }

                $orderItemFromRepo = $this->orderItemRepository->findByUid($orderItem->getUid());
                $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItemFromRepo]);
            } else {
                $dispatchEvent = $this->updatePaymentAndTransaction($orderItem, 'canceled');

                $this->restoreCartSession();

                if ($dispatchEvent) {
                    $orderItem = $this->cart->getOrderItem();
                    $finishEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                    $this->eventDispatcher->dispatch($finishEvent);
                }

                $this->addFlashMessageToCartCart('tx_cartgirosolution.controller.order.payment.action.redirect.canceled');

                $this->redirect('show', 'Cart\Cart', 'Cart');
            }
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartgirosolution.controller.order.payment.action.redirect.error_occured',
                    'CartGirosolution'
                ),
                '',
                AbstractMessage::ERROR
            );
        }
    }

    public function notifyAction(): string
    {
        $this->loadCartByArgumentHash();

        if ($this->cart) {
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

        return 'ERROR';
    }

    protected function updatePaymentAndTransaction(Item $orderItem, string $status): bool
    {
        $dispatchEvent = false;

        $payment = $orderItem->getPayment();

        foreach ($payment->getTransactions() as $transaction) {
            if ($transaction->getTxnId() === GeneralUtility::_GET('gcReference')) {
                break;
            }
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

    protected function addFlashMessageToCartCart(string $translationKey): void
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

    protected function loadCartByArgumentHash(): void
    {
        if ($this->request->hasArgument('hash')) {
            $hash = $this->request->getArgument('hash');

            if (!empty($hash)) {
                $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
                $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
                $this->cartRepository->setDefaultQuerySettings($querySettings);

                $this->cart = $this->cartRepository->findOneBySHash($hash);
            }
        }
    }

    protected function restoreCartSession(): void
    {
        $cart = $this->cart->getCart();
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();
        $this->sessionHandler->write($cart, $this->cartPluginSettings['settings']['cart']['pid']);
    }
}
