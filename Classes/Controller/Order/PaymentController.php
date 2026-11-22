<?php

declare(strict_types=1);

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
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\ItemRepository as OrderItemRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\OrderHistory\OrderChangeSource;
use Extcode\Cart\OrderStatus\PaymentStatus;
use Extcode\Cart\OrderStatus\StatusChangeOutcome;
use Extcode\Cart\OrderStatus\StatusChangeService;
use Extcode\Cart\Service\SessionHandler;
use Extcode\CartGirosolution\Configuration\CredentialLoaderRegistry;
use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use girosolution\GiroCheckout_SDK\GiroCheckout_SDK_Notify;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends ActionController
{
    private const INITIATOR = 'cart_girosolution';

    protected ?Cart $cart = null;

    protected array $cartPluginSettings;

    protected array $pluginSettings;

    private array $credentials = [];

    public function __construct(
        private readonly PersistenceManager $persistenceManager,
        private readonly SessionHandler $sessionHandler,
        private readonly CartRepository $cartRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly PaymentRepository $paymentRepository,
        public readonly CredentialLoaderRegistry $credentialLoaderRegistry,
        private readonly StatusChangeService $statusChangeService,
        private readonly LoggerInterface $logger,
    ) {}

    protected function initializeAction(): void
    {
        $this->credentials = $this->credentialLoaderRegistry->getCredentials();

        $this->cartPluginSettings
            = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->pluginSettings
            = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartGirosolution'
            );
    }

    public function redirectAction(): ResponseInterface
    {
        $this->cart = $this->loadCartByArgumentHash();

        $orderItem = $this->cart->getOrderItem();

        $clearingType = $this->getClearingType($orderItem);

        $notify = $this->getParsedNotify($clearingType);

        if ($notify->paymentSuccessful()) {
            $dispatchEvent = $this->updatePaymentAndTransaction($notify, $orderItem, PaymentStatus::PAID, OrderChangeSource::Frontend);

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

        $dispatchEvent = $this->updatePaymentAndTransaction($notify, $orderItem, PaymentStatus::CANCELED, OrderChangeSource::Frontend);

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

    public function notifyAction(): ResponseInterface
    {
        $this->cart = $this->loadCartByArgumentHash();

        $orderItem = $this->cart->getOrderItem();

        $clearingType = $this->getClearingType($orderItem);

        $notify = $this->getParsedNotify($clearingType);

        if ($notify->paymentSuccessful()) {
            $dispatchEvent = $this->updatePaymentAndTransaction($notify, $orderItem, PaymentStatus::PAID, OrderChangeSource::Api);

            if ($dispatchEvent) {
                $orderItem = $this->cart->getOrderItem();
                $finishEvent = new FinishEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }
        } else {
            $dispatchEvent = $this->updatePaymentAndTransaction($notify, $orderItem, PaymentStatus::CANCELED, OrderChangeSource::Api);

            if ($dispatchEvent) {
                $orderItem = $this->cart->getOrderItem();
                $finishEvent = new CancelEvent($this->cart->getCart(), $orderItem, $this->cartPluginSettings);
                $this->eventDispatcher->dispatch($finishEvent);
            }
        }

        $notify->sendOkStatus();
        return $this->htmlResponse('');
    }

    /**
     * Returns true if this request has changed the status of the payment.
     * Only this request may dispatch the events, otherwise the redirect of the
     * customer and the notification, which arrive at about the same time,
     * both send the emails of the order.
     */
    private function updatePaymentAndTransaction(
        GiroCheckout_SDK_Notify $notify,
        Item $orderItem,
        string $status,
        OrderChangeSource $source
    ): bool {
        $payment = $orderItem->getPayment();

        $transaction = null;
        foreach ($payment->getTransactions() as $paymentTransaction) {
            if ($paymentTransaction->getTxnId() === $notify->getResponseParam('gcReference')) {
                $transaction = $paymentTransaction;
                break;
            }
        }
        if ($transaction === null) {
            throw new Exception('No transaction found!', 1731616906);
        }

        $transaction->setExternalStatusCode($notify->getResponseParam('gcResultPayment'));
        $transaction->setNote($notify->getResponseParam('gcBackendTxId'));

        if ($transaction->getStatus() !== $status) {
            $transaction->setStatus($status);
        }

        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        // changes the status only if no other request has changed it since the payment was loaded
        $result = $this->statusChangeService->change(
            $payment,
            $status,
            $payment->getStatus(),
            self::INITIATOR,
            null,
            $source
        );

        if (!$result->isChanged() && $result->outcome !== StatusChangeOutcome::Unchanged) {
            $this->logger->error('The status of a payment paid with Girosolution could not be changed.', [
                'payment' => $payment->getUid(),
                'toStatus' => $status,
                'outcome' => $result->outcome->name,
                'vetoReason' => $result->vetoReason,
            ]);
        }

        return $result->isChanged();
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
            ContextualFeedbackSeverity::ERROR,
            true
        );

        $flashMessageService = new FlashMessageService();
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.tx_cart_cart');
        $messageQueue->enqueue($flashMessage);
    }

    private function loadCartByArgumentHash(): Cart
    {
        if ($this->request->hasArgument('hash')) {
            $hash = $this->request->getArgument('hash');

            if (!empty($hash)) {
                $querySettings = $this->cartRepository->createQuery()->getQuerySettings();
                $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
                $this->cartRepository->setDefaultQuerySettings($querySettings);

                $cart = $this->cartRepository->findOneBy(['sHash' => $hash]);
                if (!$cart instanceof Cart) {
                    throw new InvalidArgumentException('Invalid Hash!', 1734097682);
                }

                return $cart;
            }
        }

        throw new InvalidArgumentException('No Hash!', 1734098478);
    }

    private function restoreCartSession(): void
    {
        $cart = $this->cart->getCart();
        $cart->resetOrderNumber();
        $cart->resetInvoiceNumber();
        $this->sessionHandler->writeCart($this->cartPluginSettings['settings']['cart']['pid'], $cart);
    }

    private function getClearingType(?Item $orderItem): string
    {
        $payment = $orderItem->getPayment();
        $provider = $payment->getProvider();
        [$provider, $clearingType] = array_pad(explode('_', (string)$provider), 2, '');

        return $clearingType;
    }

    private function getNotify(string $clearingType): GiroCheckout_SDK_Notify
    {
        return match ($clearingType) {
            'CREDITCARD' => new GiroCheckout_SDK_Notify('creditCardTransaction'),
            'GIROPAY' => new GiroCheckout_SDK_Notify('giropayTransaction'),
            'PAYPAL' => new GiroCheckout_SDK_Notify('paypalTransaction'),
            'PAYDIREKT' => new GiroCheckout_SDK_Notify('paydirektTransaction'),
            default => throw new InvalidArgumentException('Invalid Clearint Type', 1734099627),
        };
    }

    private function getPaymentConfiguration(string $clearingType): array
    {
        return match ($clearingType) {
            'CREDITCARD' => $this->credentials['creditCard'],
            'GIROPAY' => $this->credentials['giropay'],
            'PAYPAL' => $this->credentials['paypal'],
            'PAYDIREKT' => $this->credentials['paydirekt'],
            default => throw new InvalidArgumentException('Invalid Clearint Type', 1734099627),
        };
    }

    private function getParsedNotify(string $clearingType): GiroCheckout_SDK_Notify
    {
        $notify = $this->getNotify($clearingType);
        $paymentConf = $this->getPaymentConfiguration($clearingType);
        $notify->setSecret($paymentConf['password']);
        $notify->parseNotification($_GET);

        return $notify;
    }
}
