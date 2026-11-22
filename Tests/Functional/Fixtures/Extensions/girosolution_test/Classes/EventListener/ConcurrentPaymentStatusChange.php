<?php

declare(strict_types=1);

namespace Extcode\GirosolutionTest\EventListener;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Payment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Event\Persistence\AfterObjectThawedEvent;

/**
 * Simulates a concurrent request, e.g. the notification of Girosolution while
 * the customer is redirected back: after a payment has been loaded, its stored
 * status is changed to the given status.
 *
 * The status is stored statically, as frontend requests in functional tests
 * use their own instance of the listener.
 */
final class ConcurrentPaymentStatusChange
{
    private static ?string $status = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(AfterObjectThawedEvent $event): void
    {
        if (self::$status === null || !$event->getObject() instanceof Payment) {
            return;
        }

        $this->connectionPool
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->update(
                'tx_cart_domain_model_order_payment',
                ['status' => self::$status],
                ['uid' => $event->getObject()->getUid()]
            );
    }

    public static function changeStatusTo(?string $status): void
    {
        self::$status = $status;
    }
}
