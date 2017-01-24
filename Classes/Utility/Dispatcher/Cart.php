<?php

namespace Extcode\CartGirosolution\Utility\Dispatcher;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Ajax Dispatcher
 *
 * @package cart_girosolution
 * @author Daniel Lorenz <ext.cart.girosolution@extco.de>
 */
class Cart
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Request
     *
     * @var \TYPO3\CMS\Extbase\Mvc\Request
     */
    protected $request;

    /**
     * logManager
     *
     * @var \TYPO3\CMS\Core\Log\LogManager
     */
    protected $logManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * Order Item Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\ItemRepository
     * @inject
     */
    protected $orderItemRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\PaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\TransactionRepository
     */
    protected $orderTransactionRepository;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart;

    /**
     * OrderItem
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem;

    /**
     * Order Payment
     *
     * @var \Extcode\Cart\Domain\Model\Order\Payment
     */
    protected $orderPayment;

    /**
     * Order Transaction
     *
     * @var \Extcode\Cart\Domain\Model\Order\Transaction
     */
    protected $orderTransaction;

    /**
     * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
     */
    protected $typoScriptService;

    /**
     * Order Number
     *
     * @var string
     */
    protected $orderNumber;

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * @var integer
     */
    protected $pageUid;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * Cart Configuration
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Girosolution Configuration
     *
     * @var array
     */
    protected $cartGirosolutionConf = [];

    /**
     * Curl Result
     *
     * @var string
     */
    protected $curlResult = '';

    /**
     * Curl Results
     *
     * @var array
     */
    protected $curlResults = [];

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function injectLogManager(
        \TYPO3\CMS\Core\Log\LogManager $logManager
    ) {
        $this->logManager = $logManager;
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
     * @param \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
     */
    public function injectOrderItemRepository(
        \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
    ) {
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
     */
    public function injectOrderPaymentRepository(
        \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
    ) {
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
     */
    public function injectOrderTransactionRepository(
        \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
     */
    public function injectTypoScriptService(
        \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
    ) {
        $this->typoScriptService = $typoScriptService;
    }

    /**
     * Initialize Settings
     */
    protected function initSettings()
    {
        $this->cartConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cart.']
        );
        $this->cartGirosolutionConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cartgirosolution.']
        );
    }

    /**
     * Get Request
     */
    protected function getRequest()
    {
        $request = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('request');
        $action = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('eID');

        $this->request = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Mvc\Request::class
        );
        $this->request->setControllerVendorName('Extcode');
        $this->request->setControllerExtensionName('CartGirosolution');
        $this->request->setControllerActionName($action);

        $allowedPaymentTypes = ['creditCard', 'giropay', 'paypal'];

        $paymentType = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('paymentType');

        if (in_array($paymentType, $allowedPaymentTypes)) {
            $this->request->setArgument('paymentType', $paymentType);
        }

        if (is_array($request['arguments'])) {
            $this->request->setArguments($request['arguments']);
        }
    }

    /**
     * Dispatch
     */
    public function dispatch()
    {
        $response = [];

        $this->initSettings();

        $this->getRequest();

        if ($this->request->hasArgument('paymentType')) {
            $paymentType = $this->request->getArgument('paymentType');

            switch ($this->request->getControllerActionName()) {
                case 'notifyGiroSolution':
                    $this->processNotify($paymentType);
                    break;
                case 'redirectGiroSolution':
                    $this->processRedirect($paymentType);
                    break;
            }
        }
    }

    /**
     * Process Redirect
     */
    protected function processRedirect($paymentType)
    {
        try {
            $notify = new \GiroCheckout_SDK_Notify('giropayTransaction');
            $notify->setSecret($this->cartGirosolutionConf['api'][$paymentType]['password']);
            $notify->parseNotification($_GET);

            $reference = $notify->getResponseParam('gcReference');
            $this->getOrderTransactionByReference($reference);

            if ($notify->paymentSuccessful()) {
                $resultPayment = $this->paymentSuccess($notify);

                if ($this->orderTransaction->getExternalStatusCode() != $resultPayment) {
                    $this->updatePayment($resultPayment);
                    $this->sendMails();
                }

                header('Location: ' . $this->cartGirosolutionConf['api'][$paymentType]['redirectUrl']['success']);
            } else {
                $resultPayment = $this->paymentFail($notify);

                if ($this->orderTransaction->getExternalStatusCode() != $resultPayment) {
                    $this->updatePayment($resultPayment);
                    $this->sendMails();
                }

                header('Location: ' . $this->cartGirosolutionConf['api'][$paymentType]['redirectUrl']['fail']);
            }

            if ($notify->avsSuccessful()) {
                echo $notify->getResponseParam('gcResultAVS');
            }
        } catch (\Exception $e) {
            $notify->sendBadRequestStatus();

            #TODO log the error

            exit;
        }
    }

    /**
     * Process Notify
     *
     * @return array
     */
    protected function processNotify($paymentType)
    {
        try {
            $notify = new \GiroCheckout_SDK_Notify('giropayTransaction');
            $notify->setSecret($this->cartGirosolutionConf['api'][$paymentType]['password']);
            $notify->parseNotification($_GET);

            $reference = $notify->getResponseParam('gcReference');
            $this->getOrderTransactionByReference($reference);

            if ($notify->paymentSuccessful()) {
                $resultPayment = $this->paymentSuccess($notify);

                $this->updatePayment($resultPayment);

                $this->sendMails();

                exit;
            } else {
                $resultPayment = $this->paymentFail($notify);

                $this->updatePayment($resultPayment);

                $this->sendMails();

                exit;
            }
        } catch (\Exception $e) {
            $notify->sendBadRequestStatus();

            #TODO log the error

            exit;
        }
    }

    /**
     * @param \GiroCheckout_SDK_Notify $notify
     *
     * @return string
     */
    protected function paymentSuccess(\GiroCheckout_SDK_Notify $notify)
    {
        $notify->getResponseParam('gcMerchantTxId');
        $notify->getResponseParam('gcBackendTxId');
        $notify->getResponseParam('gcAmount');
        $notify->getResponseParam('gcCurrency');
        $resultPayment = $notify->getResponseParam('gcResultPayment');

        if ($notify->avsSuccessful()) {
            $notify->getResponseParam('gcResultAVS');
        }

        $notify->sendOkStatus();

        return $resultPayment;
    }

    /**
     * @param \GiroCheckout_SDK_Notify $notify
     *
     * @return string
     */
    protected function paymentFail(\GiroCheckout_SDK_Notify $notify)
    {
        $notify->getResponseParam('gcMerchantTxId');
        $notify->getResponseParam('gcBackendTxId');
        $resultPayment = $notify->getResponseParam('gcResultPayment');

        $notify->getResponseMessage($resultPayment, 'DE');

        $notify->sendOkStatus();

        return $resultPayment;
    }

    /**
     * Send Mails
     *
     * @return void
     */
    protected function sendMails()
    {
        $billingAddress = $this->orderItem->getBillingAddress()->_loadRealInstance();
        if ($this->orderItem->getShippingAddress()) {
            $shippingAddress = $this->orderItem->getShippingAddress()->_loadRealInstance();
        }

        $this->sendBuyerMail($this->orderItem, $billingAddress, $shippingAddress);
        $this->sendSellerMail($this->orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Buyer
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     *
     * @return void
     */
    protected function sendBuyerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendBuyerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Seller
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     *
     * @return void
     */
    protected function sendSellerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendSellerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Get Cart
     */
    protected function getCart()
    {
        if ($this->orderItem) {
            /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
            $querySettings = $this->objectManager->get(
                \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
            );
            $querySettings->setStoragePageIds([$this->cartConf['settings']['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneByOrderItem($this->orderItem);
        }
    }

    /**
     * Update Cart
     */
    protected function updateCart()
    {
        $this->cart->setWasOrdered(true);

        $this->cartRepository->update($this->cart);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param int $status
     */
    protected function setOrderPaymentStatus($status)
    {
        if ($this->orderPayment) {
            $this->orderPayment->setStatus($status);
        }
    }

    /**
     * @param string $orderTransactionReference
     */
    protected function getOrderTransactionByReference($orderTransactionReference)
    {
        /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
        $querySettings = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
        );
        $querySettings->setStoragePageIds(array($this->cartConf['settings']['order']['pid']));
        $this->orderTransactionRepository->setDefaultQuerySettings($querySettings);
        $this->orderTransaction = $this->orderTransactionRepository->findOneByTxnId($orderTransactionReference);
    }

    /**
     * @param int $externalStatus
     *
     * @return void
     */
    protected function updatePayment($externalStatus)
    {
        if ($this->orderTransaction) {
            switch ($externalStatus) {
                case 4000:
                    $internalStatus = 'paid';
                    break;
                default:
                    $internalStatus = 'canceled';
            }

            $this->orderTransaction->setStatus($internalStatus);
            $this->orderTransaction->setExternalStatusCode($externalStatus);

            $this->orderTransactionRepository->update($this->orderTransaction);

            $this->orderTransaction->getPayment()->setStatus($internalStatus);

            $this->persistenceManager->persistAll();

            $this->orderItem = $this->orderTransaction->getPayment()->getItem();

            $this->getCart();

            switch ($externalStatus) {
                case 4000:
                    $this->updateCart();
                    break;
                default:
            }
        }
    }
}
