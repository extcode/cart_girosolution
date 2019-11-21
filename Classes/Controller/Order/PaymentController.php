<?php

namespace Extcode\CartGirosolution\Controller\Order;

use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Session Handler
     *
     * @var \Extcode\Cart\Service\SessionHandler
     */
    protected $sessionHandler;

    /**
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * @var \Extcode\Cart\Domain\Repository\Order\PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart = null;

    /**
     * @var array
     */
    protected $cartPluginSettings;

    /**
     * @var array
     */
    protected $pluginSettings;

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \Extcode\Cart\Service\SessionHandler $sessionHandler
     */
    public function injectSessionHandler(
        \Extcode\Cart\Service\SessionHandler $sessionHandler
    ) {
        $this->sessionHandler = $sessionHandler;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
     */
    public function injectCartRepository(
        \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\PaymentRepository $paymentRepository
     */
    public function injectPaymentRepository(
        \Extcode\Cart\Domain\Repository\Order\PaymentRepository $paymentRepository
    ) {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Initialize Action
     */
    protected function initializeAction()
    {
        $this->cartPluginSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->pluginSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartGirosolution'
            );
    }

    public function redirectAction()
    {
        $this->loadCartByArgumentHash();

        if ($this->cart) {
            $orderItem = $this->cart->getOrderItem();

            if (GeneralUtility::_GET('gcResultPayment') === '4000') {
                $invokeFinisher = $this->updatePaymentAndTransaction($orderItem, 'paid');

                if ($invokeFinisher) {
                    $this->invokeFinishers($orderItem, 'success');
                }

                $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItem]);
            } else {
                $invokeFinisher = $this->updatePaymentAndTransaction($orderItem, 'canceled');

                $this->addFlashMessageToCartCart('tx_cartgirosolution.controller.order.payment.action.redirect.canceled');

                $this->redirect('show', 'Cart\Cart', 'Cart');
            }
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'tx_cartgirosolution.controller.order.payment.action.redirect.error_occured',
                    $this->extensionName
                ),
                '',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            );
        }
    }

    public function notifyAction()
    {
        $this->loadCartByArgumentHash();

        if ($this->cart) {
            $orderItem = $this->cart->getOrderItem();

            if (GeneralUtility::_GET('gcResultPayment') === '4000') {
                $invokeFinisher = $this->updatePaymentAndTransaction($orderItem, 'paid');

                if ($invokeFinisher) {
                    $this->invokeFinishers($orderItem, 'success');
                }
            } else {
                $invokeFinisher = $this->updatePaymentAndTransaction($orderItem, 'canceled');

                $this->addFlashMessageToCartCart('tx_cartgirosolution.controller.order.payment.action.redirect.canceled');
            }

            return 'OK';
        }

        return 'ERROR';
    }

    /**
     * Executes all finishers of this form
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     * @param string $returnStatus
     */
    protected function invokeFinishers(\Extcode\Cart\Domain\Model\Order\Item $orderItem, string $returnStatus)
    {
        $cartFromSession = $this->sessionHandler->restore($this->cartPluginSettings['settings']['cart']['pid']);

        $finisherContext = $this->objectManager->get(
            \Extcode\Cart\Domain\Finisher\FinisherContext::class,
            $this->cartPluginSettings,
            $cartFromSession,
            $orderItem,
            $this->getControllerContext()
        );

        if (is_array($this->pluginSettings['finishers']) &&
            is_array($this->pluginSettings['finishers']['order']) &&
            is_array($this->pluginSettings['finishers']['order'][$returnStatus])
        ) {
            ksort($this->pluginSettings['finishers']['order'][$returnStatus]);
            foreach ($this->pluginSettings['finishers']['order'][$returnStatus] as $finisherConfig) {
                $finisherClass = $finisherConfig['class'];

                if (class_exists($finisherClass)) {
                    $finisher = $this->objectManager->get($finisherClass);
                    $finisher->execute($finisherContext);
                    if ($finisherContext->isCancelled()) {
                        break;
                    }
                } else {
                    $logManager = $this->objectManager->get(
                        \TYPO3\CMS\Core\Log\LogManager::class
                    );
                    $logger = $logManager->getLogger(__CLASS__);
                    $logger->error('Can\'t find Finisher class \'' . $finisherClass . '\'.', []);
                }
            }
        }
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     * @param string $status
     *
     * @return bool
     *
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    protected function updatePaymentAndTransaction(\Extcode\Cart\Domain\Model\Order\Item $orderItem, string $status): bool
    {
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
            $invokeFinisher = true;
        }

        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        return $invokeFinisher;
    }

    /**
     * @param string $translationKey
     *
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function addFlashMessageToCartCart(string $translationKey): void
    {
        $flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Messaging\FlashMessage::class,
            LocalizationUtility::translate(
                $translationKey,
                $this->extensionName
            ),
            '',
            \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR,
            true
        );

        $flashMessageService = $this->objectManager->get(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.tx_cart_cart');
        $messageQueue->enqueue($flashMessage);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    protected function loadCartByArgumentHash(): void
    {
        if ($this->request->hasArgument('hash')) {
            $hash = $this->request->getArgument('hash');

            if (!empty($hash)) {
                $querySettings = $this->objectManager->get(
                    \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
                );
                $querySettings->setStoragePageIds([$this->cartPluginSettings['settings']['order']['pid']]);
                $this->cartRepository->setDefaultQuerySettings($querySettings);

                $this->cart = $this->cartRepository->findOneBySHash($hash);
            }
        }
    }
}
