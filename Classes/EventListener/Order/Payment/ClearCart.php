<?php
declare(strict_types=1);
namespace Extcode\CartGirosolution\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Event\Order\EventInterface;

class ClearCart extends \Extcode\Cart\EventListener\Order\Finish\ClearCart
{
    public function __invoke(EventInterface $event): void
    {
        $orderItem = $event->getOrderItem();

        $provider = $orderItem->getPayment()->getProvider();

        if (strpos($provider, 'GIROSOLUTION') === 0) {
            parent::__invoke($event);
        }
    }
}
