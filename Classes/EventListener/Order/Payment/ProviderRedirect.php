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
use Extcode\CartGirosolution\Configuration\CredentialLoaderRegistry;
use girosolution\GiroCheckout_SDK\GiroCheckout_SDK_Request;
use InvalidArgumentException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ProviderRedirect
{
    private array $credentials = [];

    private array $conf = [];

    private int $cartPid;

    private int $orderPid;

    private ?OrderItem $orderItem = null;

    private RouterInterface $pageRouter;

    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly PersistenceManager $persistenceManager,
        private readonly SiteFinder $siteFinder,
        private readonly CartRepository $cartRepository,
        private readonly PaymentRepository $paymentRepository,
        readonly CredentialLoaderRegistry $credentialLoaderRegistry
    ) {
        $this->credentials = $this->credentialLoaderRegistry->getCredentials();

        $this->conf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartGirosolution'
        );
        if (isset($this->conf['credentials'])) {
            unset($this->conf['credentials']);
        }

        $cartConf = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );

        $this->cartPid = (int)$cartConf['settings']['cart']['pid'];
        $this->orderPid = (int)$cartConf['settings']['order']['pid'];

        $this->pageRouter = $this->siteFinder->getSiteByPageId($this->cartPid)->getRouter();
    }

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        $payment = $this->orderItem->getPayment();
        $provider = $payment->getProvider();
        [$provider, $clearingType] = explode('_', (string)$provider);

        if ($provider !== 'GIROSOLUTION') {
            return;
        }

        $request = $this->getRequest($clearingType);
        $paymentConf = $this->getPaymentConfiguration($clearingType);

        $cart = $this->persistCartToDatabase($event);

        $request->setSecret($paymentConf['password']);
        $request->addParam('merchantId', $paymentConf['merchantId'])
            ->addParam('projectId', $paymentConf['projectId'])
            ->addParam('merchantTxId', md5((string)$this->orderItem->getOrderNumber()))
            ->addParam('amount', round($this->orderItem->getTotalGross() * 100))
            ->addParam('currency', $this->orderItem->getCurrencyCode())
            ->addParam(
                'purpose',
                LocalizationUtility::translate(
                    'tx_cartgirosolution.payment_request.purpose',
                    'CartGirosolution',
                    [
                        $this->orderItem->getOrderNumber(),
                    ]
                )
            )
            ->addParam('urlRedirect', $this->buildReturnUrl('redirect', $cart->getSHash()))
            ->addParam('urlNotify', $this->buildReturnUrl('notify', $cart->getSHash()));

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
        } elseif ($clearingType === 'PAYPAL') {
            $request->addParam('type', 'SALE');
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

    private function getRequest(string $clearingType): GiroCheckout_SDK_Request
    {
        return match ($clearingType) {
            'CREDITCARD' => new GiroCheckout_SDK_Request('creditCardTransaction'),
            'GIROPAY' => new GiroCheckout_SDK_Request('giropayTransaction'),
            'PAYPAL' => new GiroCheckout_SDK_Request('paypalTransaction'),
            'PAYDIREKT' => new GiroCheckout_SDK_Request('paydirektTransaction'),
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

    /**
     * Builds a return URL to Cart order controller action
     */
    private function buildReturnUrl(string $action, string $hash): string
    {
        $arguments = [
            'tx_cartgirosolution_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash,
            ],
            'type' => (int)$this->conf['redirectTypeNum'],
        ];

        return $this->pageRouter->generateUri($this->cartPid, $arguments)->__toString();
    }

    private function persistCartToDatabase(PaymentEvent $event): Cart
    {
        $cart = new Cart();
        $cart->setOrderItem($this->orderItem);
        $cart->setCart($event->getCart());
        $cart->setPid($this->orderPid);

        $this->cartRepository->add($cart);
        $this->persistenceManager->persistAll();
        return $cart;
    }
}
