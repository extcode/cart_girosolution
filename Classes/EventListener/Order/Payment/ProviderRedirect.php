<?php
declare(strict_types=1);
namespace Extcode\CartGirosolution\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use girosolution\GiroCheckout_SDK\GiroCheckout_SDK_Request;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ProviderRedirect
{
    const PAYMENT_API_URL = 'https://frontend.pay1.de/frontend/v2/';

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

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
     * Order OrderItem
     *
     * @var OrderItem
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
     * @param PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    public function __construct(
        ConfigurationManager $configurationManager,
        PersistenceManager $persistenceManager,
        TypoScriptService $typoScriptService,
        UriBuilder $uriBuilder,
        CartRepository $cartRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->configurationManager = $configurationManager;
        $this->persistenceManager = $persistenceManager;
        $this->typoScriptService = $typoScriptService;
        $this->uriBuilder = $uriBuilder;
        $this->cartRepository = $cartRepository;
        $this->paymentRepository = $paymentRepository;

        $this->conf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartGirosolution'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        $payment = $this->orderItem->getPayment();
        $provider = $payment->getProvider();
        [$provider, $clearingType] = explode('_', $provider);

        if ($provider !== 'GIROSOLUTION') {
            return;
        }

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
                return;
        }

        $cart = new Cart();
        $cart->setOrderItem($this->orderItem);
        $cart->setCart($event->getCart());
        $cart->setPid((int)$this->cartConf['settings']['order']['pid']);

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

            $transaction = new Transaction();
            $transaction->setTxnId((string)$request->getResponseParam('reference'));
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

        $event->setPropagationStopped(true);
    }

    /**
     * Builds a return URL to Cart order controller action
     */
    protected function buildReturnUrl(string $action, string $hash): string
    {
        $pid = (int)$this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartgirosolution_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->uriBuilder;

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType((int)$this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setArguments($arguments)
            ->build();
    }
}
