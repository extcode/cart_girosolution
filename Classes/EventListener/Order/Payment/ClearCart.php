<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Configuration\Loader\PaymentMethodsLoaderInterface;
use Extcode\Cart\Domain\Model\Order\BillingAddress;
use Extcode\Cart\Domain\Model\Order\ShippingAddress;
use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClearCart
{
    public function __construct(
        protected readonly CartUtility $cartUtility,
        protected readonly PaymentMethodsLoaderInterface $paymentMethodsLoader,
        protected readonly SessionHandler $sessionHandler
    ) {}

    public function __invoke(PaymentEvent $event): void
    {
        $orderItem = $event->getOrderItem();

        $provider = $orderItem->getPayment()->getProvider();

        if (str_starts_with((string)$provider, 'GIROSOLUTION')) {
            $cart = $event->getCart();
            $settings = $event->getSettings();

            $paymentId = $cart->getPayment()->getId();

            $paymentSettings = $this->paymentMethodsLoader->getPaymentMethods($cart);

            if ((int)($paymentSettings['options'][$paymentId]['preventClearCart'] ?? 0) != 1) {
                $cartPid = $settings['settings']['cart']['pid'];

                $this->sessionHandler->writeCart(
                    $cartPid,
                    $this->cartUtility->getNewCart($settings)
                );
                $this->sessionHandler->writeAddress(
                    'billing_address_' . $cartPid,
                    GeneralUtility::makeInstance(BillingAddress::class)
                );
                $this->sessionHandler->writeAddress(
                    'shipping_address_' . $cartPid,
                    GeneralUtility::makeInstance(ShippingAddress::class)
                );
            }
        }
    }
}
