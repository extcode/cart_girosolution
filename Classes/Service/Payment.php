<?php

namespace Extcode\CartGirosolution\Service;

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

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Payment Service
 *
 * @package cart_girosolution
 * @author Daniel Lorenz <ext.cart.girosolution@extco.de>
 */
class Payment
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Configuration Manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

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
     * Cart Settings
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Girosolution Settings
     *
     * @var array
     */
    protected $cartGirosolutionConf = [];

    /**
     * Payment Query
     *
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * Order Item
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart = null;

    /**
     * CartFHash
     *
     * @var string
     */
    protected $cartFHash = '';

    /**
     * CartSHash
     *
     * @var string
     */
    protected $cartSHash = '';

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
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\Object\ObjectManager::class
        );

        $this->configurationManager = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class
        );

        $this->cartConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartGirosolutionConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartGirosolution'
            );
    }

    /**
     * Handle Payment - Signal Slot Function
     *
     * @param array $params
     *
     * @return array
     */
    public function handlePayment($params)
    {
        $this->orderItem = $params['orderItem'];

        $provider = $this->orderItem->getPayment()->getProvider();

        if ($provider != 'GIROSOLUTION_CREDITCARD' &&
            $provider != 'GIROSOLUTION_GIROPAY'
        ) {
            return [$params];
        }

        $params['providerUsed'] = true;

        $cart = $params['cart'];

        $this->cart = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Cart::class
        );
        $this->cart->setOrderItem($this->orderItem);
        $this->cart->setCart($cart);
        $this->cart->setPid($this->cartConf['settings']['order']['pid']);

        $cartRepository = $this->objectManager->get(
            \Extcode\Cart\Domain\Repository\CartRepository::class
        );
        $cartRepository->add($this->cart);
        $this->persistenceManager->persistAll();

        switch ($provider) {
            case 'GIROSOLUTION_CREDITCARD':
                $transactionType = 'creditCard';
                break;
            case 'GIROSOLUTION_GIROPAY':
                $transactionType = 'giropay';
                break;
            default:
                $transactionType = '';
        }
        $this->handleRequest($transactionType);

        return [$params];
    }

    /**
     * Handle GiroCheckout SDK Request
     *
     * @param string $transactionType
     *
     * @return void
     */
    protected function handleRequest($transactionType)
    {
        if (empty($transactionType)) {
            return;
        }
        $password = $this->cartGirosolutionConf['api'][$transactionType]['password'];
        $merchantId = $this->cartGirosolutionConf['api'][$transactionType]['merchantId'];
        $projectId = $this->cartGirosolutionConf['api'][$transactionType]['projectId'];
        $merchantTxId = $this->cartGirosolutionConf['api'][$transactionType]['merchantTxId'];
        $purpose = substr($this->cartGirosolutionConf['api'][$transactionType]['purpose'], 0, 27); // API String length is 27

        $amount = $this->orderItem->getTotalGross() * 100;

        /** @var \GiroCheckout_SDK_Request $request */
        $request = new \GiroCheckout_SDK_Request($transactionType . 'Transaction');
        $request->setSecret($password);
        $request->addParam('merchantId', $merchantId);
        $request->addParam('projectId', $projectId);
        $request->addParam('merchantTxId', $merchantTxId);
        $request->addParam('amount', $amount);
        $request->addParam('currency', 'EUR');
        if ($transactionType == 'creditCardTransaction') {
            $request->addParam('type', 'SALE');
        }
        $request->addParam('purpose', $purpose);
        $request->addParam('urlRedirect', $this->getApiUrl($transactionType, 'redirect'));
        $request->addParam('urlNotify', $this->getApiUrl($transactionType, 'notify'));
        $request->submit();

        if ($request->requestHasSucceeded()) {
            $reference = $request->getResponseParam('reference');
            $this->addOrderTransaction($reference);

            $request->getResponseParam('redirect');
            $request->redirectCustomerToPaymentProvider();
        } else {
            $this->markPaymentAsFailed();

            $request->getResponseParam('rc');
            $request->getResponseParam('msg');
            $message = $request->getResponseMessage(
                $request->getResponseParam('rc'),
                'DE'
            );
            $this->logMessage($message);
        }
    }

    /**
     * @param string $transactionType
     * @param string $returnUrlType
     *
     * @return string
     */
    protected function getApiUrl($transactionType, $returnUrlType)
    {
        $apiUrl = $this->cartGirosolutionConf['api'][$transactionType]['url'];
        $apiUrl .= '?eID=' . $returnUrlType . 'GiroSolution&paymentType=' . $transactionType;

        return $apiUrl;
    }

    /**
     * Mark the Order Payment Record as Failed
     */
    protected function markPaymentAsFailed()
    {
        $orderPayment = $this->orderItem->getPayment();
        $orderPayment->setStatus('failed');

        $this->orderPaymentRepository->update($orderPayment);

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
        $orderPayment = $this->orderItem->getPayment();

        $orderTransaction = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Order\Transaction::class
        );
        $orderTransaction->setPid($orderPayment->getPid());

        $orderTransaction->setTxnId($txn_id);
        $orderTransaction->setTxnTxt($txn_txt);
        $this->orderTransactionRepository->add($orderTransaction);

        if ($orderPayment) {
            $orderPayment->addTransaction($orderTransaction);
        }

        $this->orderPaymentRepository->update($orderPayment);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param string $message
     */
    protected function logMessage($message)
    {
        $logger = $this->logManager->getLogger(__CLASS__);

        $logger->debug(
            'GiroSolution CreditCard',
            [
                'message' => $message,
            ]
        );
    }
}
