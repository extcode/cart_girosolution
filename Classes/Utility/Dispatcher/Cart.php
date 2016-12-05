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
    protected $transaction;

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

        switch ($this->request->getControllerActionName()) {
            case 'notifyGiroSolution':
                $this->processNotify();
                break;
            case 'redirectGiroSolution':
                $this->processRedirect();
                break;
        }
    }

    /**
     * Process Redirect
     */
    protected function processRedirect()
    {
        $logger = $this->logManager->getLogger(__CLASS__);

        try {
            $notify = new \GiroCheckout_SDK_Notify('giropayTransaction');
            $notify->setSecret($this->cartGirosolutionConf['api']['password']);

            //the array containing the parameters
            $notify->parseNotification($_GET);

            //show the result of the transaction to the customer
            if ($notify->paymentSuccessful()) {
                header('Location: ' . $this->cartGirosolutionConf['api']['successUrl']);
            } else {
                header('Location: ' . $this->cartGirosolutionConf['api']['failUrl']);
            }

            if ($notify->avsSuccessful()) {
                echo $notify->getResponseParam('gcResultAVS');
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Process Notify
     *
     * @return array
     */
    protected function processNotify()
    {
        $logger = $this->logManager->getLogger(__CLASS__);

        $logger->debug(
            'processNotify',
            [
                'message' => '',
            ]
        );

        try {
            $notify = new \GiroCheckout_SDK_Notify('giropayTransaction');
            $notify->setSecret($this->cartGirosolutionConf['api']['password']);
            $notify->parseNotification($_GET);

            //check response and update transaction
            if ($notify->paymentSuccessful()) {
                $reference = $notify->getResponseParam('gcReference');
                $notify->getResponseParam('gcMerchantTxId');
                $notify->getResponseParam('gcBackendTxId');
                $notify->getResponseParam('gcAmount');
                $notify->getResponseParam('gcCurrency');
                $resultPayment = $notify->getResponseParam('gcResultPayment');

                if ($notify->avsSuccessful()) {
                    $notify->getResponseParam('gcResultAVS');
                }

                $notify->sendOkStatus();
                $notify->setNotifyResponseParam('Result', 'OK');
                $notify->setNotifyResponseParam('ErrorMessage', '');
                $notify->setNotifyResponseParam('MailSent', '0');
                $notify->setNotifyResponseParam('OrderId', '1111');
                $notify->setNotifyResponseParam('CustomerId', '2222');
                echo $notify->getNotifyResponseStringJson();

                $this->updatePaymentByOrderTransactionReference($reference, $resultPayment);

                exit;
            } else {
                $reference = $notify->getResponseParam('gcReference');
                $notify->getResponseParam('gcMerchantTxId');
                $notify->getResponseParam('gcBackendTxId');
                $resultPayment = $notify->getResponseParam('gcResultPayment');
                $notify->getResponseMessage($notify->getResponseParam('gcResultPayment'), 'DE');

                $notify->sendOkStatus();
                $notify->setNotifyResponseParam('Result', 'OK');
                $notify->setNotifyResponseParam('ErrorMessage', '');
                $notify->setNotifyResponseParam('MailSent', '0');
                $notify->setNotifyResponseParam('OrderId', '1111');
                $notify->setNotifyResponseParam('CustomerId', '2222');
                echo $notify->getNotifyResponseStringJson();

                $this->updatePaymentByOrderTransactionReference($reference, $resultPayment);

                exit;
            }
        } catch (\Exception $e) {
            $notify->sendBadRequestStatus();
            $notify->setNotifyResponseParam('Result', 'ERROR');
            $notify->setNotifyResponseParam('ErrorMessage', $e->getMessage());
            $notify->setNotifyResponseParam('MailSent', '0');
            $notify->setNotifyResponseParam('OrderId', '1111');
            $notify->setNotifyResponseParam('CustomerId', '2222');
            echo $notify->getNotifyResponseStringJson();

            var_dump($e->getMessage());
            exit;
        }
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
     * Get Order Item
     */
    protected function getOrderItem()
    {
        if ($this->orderNumber) {
            $this->orderItem = $this->orderItemRepository->findOneByOrderNumber($this->orderNumber);
        }
    }

    /**
     * Get Payment
     */
    protected function getOrderPayment()
    {
        if ($this->orderItem) {
            $this->orderPayment = $this->orderItem->getPayment();
        }
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
     * @param string $txn_id
     * @param string $txn_txt
     *
     * @return void
     */
    protected function addOrderTransaction($txn_id, $txn_txt = '')
    {
        $this->transaction = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Order\Transaction::class
        );
        $this->transaction->setPid($this->orderPayment->getPid());

        $this->transaction->setTxnId($txn_id);
        $this->transaction->setTxnTxt($txn_txt);
        $this->orderTransactionRepository->add($this->transaction);

        if ($this->orderPayment) {
            $this->orderPayment->addTransaction($this->transaction);
        }

        $this->orderPaymentRepository->update($this->orderPayment);

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
     * @param int $externalStatus
     *
     * @return void
     */
    protected function updatePaymentByOrderTransactionReference($orderTransactionReference, $externalStatus)
    {
        /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
        $querySettings = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
        );
        $querySettings->setStoragePageIds(array($this->cartConf['settings']['order']['pid']));
        $this->orderTransactionRepository->setDefaultQuerySettings($querySettings);
        $orderTransaction = $this->orderTransactionRepository->findOneByTxnId($orderTransactionReference);

        if ($orderTransaction) {
            switch ($externalStatus) {
                case 4000:
                    $internalStatus = 'paid';
                    break;
                default:
                    $internalStatus = 'canceled';
            }

            $orderTransaction->setStatus($internalStatus);
            $orderTransaction->setExternalStatusCode($externalStatus);

            $this->orderTransactionRepository->update($orderTransaction);

            $orderTransaction->getPayment()->setStatus($internalStatus);

            $this->persistenceManager->persistAll();
        }
    }
}
