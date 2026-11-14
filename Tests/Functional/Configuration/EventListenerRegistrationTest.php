<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Functional\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Extcode\CartGirosolution\EventListener\Order\Payment\ClearCart;
use Extcode\CartGirosolution\EventListener\Order\Payment\ProviderRedirect;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Checks the event listeners registered in Configuration/Services.php.
 */
#[CoversNothing]
final class EventListenerRegistrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'extcode/cart',
        'extcode/cart-girosolution',
    ];

    #[Test]
    public function clearsTheCartBeforeRedirectingToGirosolution(): void
    {
        $listeners = array_intersect_key(
            $this->getListenerDefinitions(PaymentEvent::class),
            array_flip([
                'cart-girosolution--order--payment--clear-cart',
                'cart-girosolution--order--payment--provider-redirect',
            ])
        );

        self::assertSame(
            [
                'cart-girosolution--order--payment--clear-cart' => ClearCart::class,
                'cart-girosolution--order--payment--provider-redirect' => ProviderRedirect::class,
            ],
            array_map(static fn(array $listener): string => $listener['service'], $listeners)
        );
    }

    #[Test]
    public function sendsTheMailsOfCartAfterTheFinishedPayment(): void
    {
        $listeners = $this->getListenerDefinitions(FinishEvent::class);

        self::assertArrayHasKey('cart-girosolution--order--finish--email', $listeners);
        self::assertSame(
            'cart-girosolution--event-listener--order--finish--email',
            $listeners['cart-girosolution--order--finish--email']['service']
        );
    }

    /**
     * @return array<string, array{service: string, method: ?string}>
     */
    private function getListenerDefinitions(string $event): array
    {
        return $this->get(ListenerProvider::class)->getAllListenerDefinitions()[$event] ?? [];
    }
}
