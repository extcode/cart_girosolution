<?php

namespace Extcode\CartGirosolution\Utility;

use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use girosolution\GiroCheckout_SDK\GiroCheckout_SDK_Request;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentUtility
{
    const PAYMENT_API_URL = 'https://frontend.pay1.de/frontend/v2/';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * Payment Query Url
     *
     * @var string
     */
    protected $paymentQueryUrl = self::PAYMENT_API_URL;

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
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
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

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartGirosolution'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );

        $this->cartRepository = $this->objectManager->get(
            CartRepository::class
        );

        $this->paymentRepository = $this->objectManager->get(
            PaymentRepository::class
        );
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function handlePayment(array $params): array
    {
        $this->orderItem = $params['orderItem'];

        $payment = $this->orderItem->getPayment();
        $provider = $payment->getProvider();
        list($provider, $clearingType) = explode('_', $provider);

        if ($provider !== 'GIROSOLUTION') {
            return [$params];
        }

        $params['providerUsed'] = true;
        $paymentConf = [];

        switch ($clearingType) {
            case 'CREDITCARD':
                $request = new GiroCheckout_SDK_Request('creditCardTransaction');
                $paymentConf = $this->conf['creditCard'];
                break;
            case 'GIROPAY':
                $request = new GiroCheckout_SDK_Request('giropayTransaction');
                $paymentConf = $this->conf['giropay'];
                break;
            case 'PAYPAL':
                $request = new GiroCheckout_SDK_Request('paypalTransaction');
                $paymentConf = $this->conf['paypal'];
                break;
            case 'PAYDIREKT':
                $request = new GiroCheckout_SDK_Request('paydirektTransaction');
                $paymentConf = $this->conf['paydirekt'];
                break;
            default:
                return [$params];
        }

        $cart = $this->objectManager->get(
            \Extcode\Cart\Domain\Model\Cart::class
        );
        $cart->setOrderItem($this->orderItem);
        $cart->setCart($params['cart']);
        $cart->setPid($this->cartConf['settings']['order']['pid']);

        $this->cartRepository->add($cart);
        $this->persistenceManager->persistAll();

        $this->cartFHash = $cart->getFHash();
        $this->cartSHash = $cart->getSHash();

        $request->setSecret($paymentConf['password']);
        $request->addParam('merchantId', $paymentConf['merchantId'])
            ->addParam('projectId', $paymentConf['projectId'])
            ->addParam('merchantTxId', md5($this->orderItem->getOrderNumber()))
            ->addParam('amount', round($this->orderItem->getTotalGross() * 100))
            ->addParam('currency', $this->orderItem->getCurrencyCode())
            ->addParam(
                'purpose',
                LocalizationUtility::translate(
                    'tx_cartgirosolution.payment_request.purpose',
                    'CartGirosolution',
                    [
                        $this->orderItem->getOrderNumber()
                    ]
                )
            )
            ->addParam('urlRedirect', $this->buildReturnUrl('redirect', $this->cartSHash))
            ->addParam('urlNotify', $this->buildReturnUrl('notify', $this->cartSHash));

        if ($clearingType === 'PAYDIREKT') {
            $request->addParam('orderId', $this->orderItem->getOrderNumber());

            if ($this->orderItem->getShippingAddress()) {
                $request->addParam('shippingAddresseFirstName', $this->orderItem->getShippingAddress()->getFirstName());
                $request->addParam('shippingAddresseLastName', $this->orderItem->getShippingAddress()->getLastName());
                $request->addParam('shippingZipCode', $this->orderItem->getShippingAddress()->getZip());
                $request->addParam('shippingCity', $this->orderItem->getShippingAddress()->getCity());
                $request->addParam('shippingCountry', $this->orderItem->getShippingAddress()->getCountry());
                if ($this->orderItem->getShippingAddress()->getEmail()) {
                    $request->addParam('shippingEmail', $this->orderItem->getShippingAddress()->getEmail());
                } else {
                    $request->addParam('shippingEmail', $this->orderItem->getBillingAddress()->getEmail());
                }
            } else {
                $request->addParam('shippingAddresseFirstName', $this->orderItem->getBillingAddress()->getFirstName());
                $request->addParam('shippingAddresseLastName', $this->orderItem->getBillingAddress()->getLastName());
                $request->addParam('shippingZipCode', $this->orderItem->getBillingAddress()->getZip());
                $request->addParam('shippingCity', $this->orderItem->getBillingAddress()->getCity());
                $request->addParam('shippingCountry', $this->orderItem->getBillingAddress()->getCountry());
                $request->addParam('shippingEmail', $this->orderItem->getBillingAddress()->getEmail());
            }
        }

        $request->submit();

        if ($request->requestHasSucceeded()) {
            $request->getResponseParam('rc');
            $request->getResponseParam('msg');
            $request->getResponseParam('reference');
            $request->getResponseParam('redirect');

            $transaction = $this->objectManager->get(Transaction::class);
            $transaction->setTxnId($request->getResponseParam('reference'));
            $transaction->setStatus('created');

            $payment->addTransaction($transaction);

            $this->paymentRepository->update($payment);
            $this->persistenceManager->persistAll();

            $request->redirectCustomerToPaymentProvider();
        } else {
            // if the transaction did not succeed update your local system, get the responsecode and notify the customer
            $request->getResponseParam('rc');
            $request->getResponseParam('msg');
            $request->getResponseMessage($request->getResponseParam('rc'), 'DE');
        }

        return [$params];
    }

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     * @return string
     */
    protected function buildReturnUrl(string $action, string $hash): string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartgirosolution_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);

        return $uriBuilder;
    }
}
