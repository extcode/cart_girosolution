<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Unit\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\CartGirosolution\Configuration\CredentialLoaderRegistry;
use Extcode\CartGirosolution\EventListener\Order\Payment\ProviderRedirect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The request to Girosolution itself needs the API of the payment provider,
 * so only the cases which must not send a request are tested here.
 */
#[CoversClass(ProviderRedirect::class)]
final class ProviderRedirectTest extends UnitTestCase
{
    private PersistenceManager&MockObject $persistenceManager;

    private CartRepository&MockObject $cartRepository;

    private PaymentRepository&MockObject $paymentRepository;

    private SiteFinder&MockObject $siteFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistenceManager = $this->createMock(PersistenceManager::class);
        $this->cartRepository = $this->createMock(CartRepository::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        $site = self::createStub(Site::class);
        $site->method('getRouter')->willReturn(self::createStub(RouterInterface::class));

        $this->siteFinder = $this->createMock(SiteFinder::class);
        $this->siteFinder->method('getSiteByPageId')->willReturn($site);
    }

    #[Test]
    public function usesTheSiteOfTheCartPage(): void
    {
        $this->siteFinder
            ->expects(self::once())
            ->method('getSiteByPageId')
            ->with(3);

        $this->createSubject();
    }

    public static function otherProviderDataProvider(): \Generator
    {
        yield 'no provider' => [''];
        yield 'provider of another extension' => ['PAYPAL'];
        yield 'provider of another extension with clearing type' => ['PAYONE_CREDITCARD'];
    }

    #[Test]
    #[DataProvider('otherProviderDataProvider')]
    public function doesNothingForPaymentsOfOtherProviders(string $provider): void
    {
        $this->cartRepository->expects(self::never())->method('add');
        $this->paymentRepository->expects(self::never())->method('update');
        $this->persistenceManager->expects(self::never())->method('persistAll');

        $event = $this->createEvent($provider);

        ($this->createSubject())($event);

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function throwsExceptionForUnknownClearingType(): void
    {
        $this->cartRepository->expects(self::never())->method('add');

        $this->expectExceptionObject(new \InvalidArgumentException('Invalid Clearint Type', 1734099627));

        ($this->createSubject())($this->createEvent('GIROSOLUTION_UNKNOWN'));
    }

    private function createSubject(): ProviderRedirect
    {
        $configurationManager = self::createStub(ConfigurationManager::class);
        $configurationManager
            ->method('getConfiguration')
            ->willReturnMap([
                [
                    ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                    'CartGirosolution',
                    null,
                    ['redirectTypeNum' => '2278106'],
                ],
                [
                    ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                    'Cart',
                    null,
                    ['settings' => ['cart' => ['pid' => '3'], 'order' => ['pid' => '4']]],
                ],
            ]);

        return new ProviderRedirect(
            $configurationManager,
            $this->persistenceManager,
            $this->siteFinder,
            $this->cartRepository,
            $this->paymentRepository,
            new CredentialLoaderRegistry([]),
        );
    }

    private function createEvent(string $provider): PaymentEvent
    {
        $payment = new Payment();
        $payment->setProvider($provider);

        $orderItem = new OrderItem();
        $orderItem->setPayment($payment);

        return new PaymentEvent(self::createStub(Cart::class), $orderItem);
    }
}
