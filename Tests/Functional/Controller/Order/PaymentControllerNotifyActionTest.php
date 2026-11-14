<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Functional\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartGirosolution\Controller\Order\PaymentController;
use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Extcode\GirosolutionTest\EventListener\EventRecorder;
use girosolution\GiroCheckout_SDK\helper\GiroCheckout_SDK_Exception_helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Checks the stored payment, the transaction and the dispatched events after
 * the notification of Girosolution.
 */
#[CoversClass(PaymentController::class)]
final class PaymentControllerNotifyActionTest extends AbstractPaymentControllerTestCase
{
    #[Test]
    public function successfulPaymentMarksPaymentAndTransactionAsPaid(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertSame('paid', $this->getPayment()['status']);
        self::assertSame(
            [
                'status' => 'paid',
                'external_status_code' => self::RESULT_PAYMENT_SUCCESSFUL,
                'note' => 'gc-backend-tx-0001',
            ],
            $this->getTransaction()
        );
    }

    #[Test]
    public function successfulPaymentDispatchesFinishEvent(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
        self::assertArrayNotHasKey(CancelEvent::class, EventRecorder::getEvents());

        $event = EventRecorder::getEvents()[FinishEvent::class][0];
        self::assertInstanceOf(FinishEvent::class, $event);
        self::assertSame('ORD-2026-0001', $event->getOrderItem()->getOrderNumber());
        self::assertSame('1', (string)$event->getSettings()['settings']['cart']['pid']);
    }

    #[Test]
    public function canceledPaymentMarksPaymentAndTransactionAsCanceled(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

        self::assertSame('canceled', $this->getPayment()['status']);
        self::assertSame(
            [
                'status' => 'canceled',
                'external_status_code' => self::RESULT_PAYMENT_CANCELED,
                'note' => 'gc-backend-tx-0001',
            ],
            $this->getTransaction()
        );
    }

    #[Test]
    public function canceledPaymentDispatchesCancelEvent(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

        self::assertCount(1, EventRecorder::getEvents()[CancelEvent::class] ?? []);
        self::assertArrayNotHasKey(FinishEvent::class, EventRecorder::getEvents());
    }

    #[Test]
    public function repeatedNotificationDoesNotDispatchTheEventAgain(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertSame('paid', $this->getPayment()['status']);
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function notificationWithWrongHashIsRejected(): void
    {
        try {
            $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, 'wrong-secret');
            self::fail('The notification with a wrong hash has been accepted.');
        } catch (GiroCheckout_SDK_Exception_helper $exception) {
            self::assertStringContainsString('hash mismatch', $exception->getMessage());
        }

        self::assertSame('open', $this->getPayment()['status']);
        self::assertSame('created', $this->getTransaction()['status']);
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function notificationForUnknownCartIsRejected(): void
    {
        try {
            $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET, 'unknown-cart-hash');
            self::fail('The notification for an unknown cart has been accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(1734097682, $exception->getCode());
        }

        self::assertSame('open', $this->getPayment()['status']);
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function notificationWithoutCartHashIsRejected(): void
    {
        try {
            $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET, null);
            self::fail('The notification without cart hash has been accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(1734098478, $exception->getCode());
        }

        self::assertSame('open', $this->getPayment()['status']);
    }

    #[Test]
    public function paypalPaymentIsVerifiedWithPaypalCredentials(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_PAYPAL');

        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, 'paypal-secret');

        self::assertSame('paid', $this->getPayment()['status']);
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function paypalPaymentIsNotVerifiedWithCreditCardCredentials(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_PAYPAL');

        $this->expectException(GiroCheckout_SDK_Exception_helper::class);

        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
    }

    #[Test]
    public function notificationForUnknownClearingTypeIsRejected(): void
    {
        $this->setPaymentProvider('GIROSOLUTION_UNKNOWN');

        try {
            $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
            self::fail('The notification for an unknown clearing type has been accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(1734099627, $exception->getCode());
        }

        self::assertSame('open', $this->getPayment()['status']);
    }

    #[Test]
    public function notificationForPaymentWithoutTransactionIsRejected(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_transaction')
            ->delete('tx_cart_domain_model_order_transaction', ['uid' => 1]);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_order_payment')
            ->update('tx_cart_domain_model_order_payment', ['transactions' => 0], ['uid' => 1]);

        try {
            $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
            self::fail('The notification for a payment without transaction has been accepted.');
        } catch (\Exception $exception) {
            self::assertSame(1731616906, $exception->getCode());
        }

        self::assertSame('open', $this->getPayment()['status']);
        self::assertSame([], EventRecorder::getEvents());
    }

    #[Test]
    public function notifyActionRespondsWithEmptyBody(): void
    {
        $response = $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', trim((string)$response->getBody()));
    }
}
