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
use Extcode\Cart\Domain\Model\Cart\ServiceInterface;
use Extcode\Cart\Domain\Model\Order\BillingAddress;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Model\Order\ShippingAddress;
use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\Cart\Service\PaymentMethodsFromTypoScriptService;
use Extcode\Cart\Service\PaymentMethodsServiceInterface;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use Extcode\CartGirosolution\EventListener\Order\Payment\ClearCart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ClearCart::class)]
final class ClearCartTest extends UnitTestCase
{
    private const PAYMENT_ID = 2;

    private const BILLING_COUNTRY = 'de';

    private const SETTINGS = [
        'settings' => [
            'cart' => [
                'pid' => '3',
            ],
        ],
    ];

    private CartUtility&MockObject $cartUtility;

    private PaymentMethodsFromTypoScriptService&MockObject $paymentMethodsService;

    private SessionHandler&MockObject $sessionHandler;

    private Cart $cart;

    private ClearCart $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartUtility = $this->createMock(CartUtility::class);
        $this->paymentMethodsService = $this->createMock(PaymentMethodsFromTypoScriptService::class);
        $this->sessionHandler = $this->createMock(SessionHandler::class);

        $payment = self::createStub(ServiceInterface::class);
        $payment->method('getId')->willReturn(self::PAYMENT_ID);

        $this->cart = self::createStub(Cart::class);
        $this->cart->method('getPayment')->willReturn($payment);
        $this->cart->method('getBillingCountry')->willReturn(self::BILLING_COUNTRY);

        $this->subject = new ClearCart(
            $this->cartUtility,
            $this->paymentMethodsService,
            $this->sessionHandler,
        );
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
        $this->paymentMethodsService->expects(self::never())->method('getConfigurationsForType');
        $this->cartUtility->expects(self::never())->method('getNewCart');
        $this->sessionHandler->expects(self::never())->method('writeCart');
        $this->sessionHandler->expects(self::never())->method('writeAddress');

        ($this->subject)($this->createEvent($provider));
    }

    #[Test]
    public function doesNothingIfThePaymentMethodsServiceProvidesNoConfiguration(): void
    {
        $this->cartUtility->expects(self::never())->method('getNewCart');
        $this->sessionHandler->expects(self::never())->method('writeCart');
        $this->sessionHandler->expects(self::never())->method('writeAddress');

        $subject = new ClearCart(
            $this->cartUtility,
            self::createStub(PaymentMethodsServiceInterface::class),
            $this->sessionHandler,
        );

        $subject($this->createEvent('GIROSOLUTION_CREDITCARD'));
    }

    #[Test]
    public function doesNotClearTheCartIfPreventClearCartIsSet(): void
    {
        $this->paymentMethodsService
            ->method('getConfigurationsForType')
            ->willReturn([
                'options' => [
                    self::PAYMENT_ID => [
                        'preventClearCart' => '1',
                    ],
                ],
            ]);

        $this->cartUtility->expects(self::never())->method('getNewCart');
        $this->sessionHandler->expects(self::never())->method('writeCart');
        $this->sessionHandler->expects(self::never())->method('writeAddress');

        ($this->subject)($this->createEvent('GIROSOLUTION_CREDITCARD'));
    }

    public static function paymentConfigurationWithoutPreventClearCartDataProvider(): \Generator
    {
        yield 'no payment configuration' => [null];
        yield 'no options' => [['options' => []]];
        yield 'preventClearCart not set' => [['options' => [self::PAYMENT_ID => []]]];
        yield 'preventClearCart disabled' => [['options' => [self::PAYMENT_ID => ['preventClearCart' => '0']]]];
        yield 'preventClearCart set for another payment' => [['options' => [self::PAYMENT_ID + 1 => ['preventClearCart' => '1']]]];
    }

    #[Test]
    #[DataProvider('paymentConfigurationWithoutPreventClearCartDataProvider')]
    public function clearsCartAndAddressesForGirosolutionPayments(?array $paymentConfiguration): void
    {
        $this->paymentMethodsService
            ->expects(self::once())
            ->method('getConfigurationsForType')
            ->with('payments', self::BILLING_COUNTRY)
            ->willReturn($paymentConfiguration);

        $newCart = self::createStub(Cart::class);
        $this->cartUtility
            ->expects(self::once())
            ->method('getNewCart')
            ->with(self::SETTINGS)
            ->willReturn($newCart);

        $this->sessionHandler
            ->expects(self::once())
            ->method('writeCart')
            ->with('3', $newCart);

        $writtenAddresses = [];
        $this->sessionHandler
            ->expects(self::exactly(2))
            ->method('writeAddress')
            ->willReturnCallback(function (string $key, object $address) use (&$writtenAddresses): void {
                $writtenAddresses[$key] = $address::class;
            });

        ($this->subject)($this->createEvent('GIROSOLUTION_GIROPAY'));

        self::assertSame(
            [
                'billing_address_3' => BillingAddress::class,
                'shipping_address_3' => ShippingAddress::class,
            ],
            $writtenAddresses
        );
    }

    #[Test]
    public function doesNotStopThePropagationOfTheEvent(): void
    {
        $event = $this->createEvent('GIROSOLUTION_PAYPAL');

        ($this->subject)($event);

        self::assertFalse($event->isPropagationStopped());
    }

    private function createEvent(string $provider): PaymentEvent
    {
        $payment = new Payment();
        $payment->setProvider($provider);

        $orderItem = new OrderItem();
        $orderItem->setPayment($payment);

        return new PaymentEvent($this->cart, $orderItem, self::SETTINGS);
    }
}
