<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Functional\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\CartGirosolution\Controller\Order\PaymentController;
use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Extcode\CartGirosolution\Tests\Functional\Fixtures\EventRecorder;
use girosolution\GiroCheckout_SDK\helper\GiroCheckout_SDK_Exception_helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Girosolution redirects the customer to the redirectAction after the
 * payment. The customer is forwarded to the order, or back to the cart if
 * the payment has been canceled.
 *
 * TYPO3 v13 sends the redirect of an Extbase plugin via header() instead of
 * the response, so the target of the redirect is not visible in functional
 * tests and not checked here.
 */
#[CoversClass(PaymentController::class)]
final class PaymentControllerRedirectActionTest extends AbstractPaymentControllerTestCase
{
    #[Test]
    public function successfulPaymentMarksPaymentAndTransactionAsPaid(): void
    {
        $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

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
        $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
        self::assertArrayNotHasKey(CancelEvent::class, EventRecorder::getEvents());
    }

    #[Test]
    public function successfulPaymentDoesNotRestoreTheCart(): void
    {
        $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertSame([], $this->getSessionData());
    }

    /**
     * Girosolution usually sends the notification before the customer is
     * redirected. The event must not be dispatched a second time.
     */
    #[Test]
    public function redirectAfterNotificationDoesNotDispatchTheEventAgain(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
        $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);

        self::assertSame('paid', $this->getPayment()['status']);
        self::assertCount(1, EventRecorder::getEvents()[FinishEvent::class] ?? []);
    }

    #[Test]
    public function canceledPaymentMarksPaymentAndTransactionAsCanceled(): void
    {
        $this->sendRedirect(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

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
        $this->sendRedirect(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

        self::assertCount(1, EventRecorder::getEvents()[CancelEvent::class] ?? []);
        self::assertArrayNotHasKey(FinishEvent::class, EventRecorder::getEvents());
    }

    #[Test]
    public function canceledPaymentRestoresTheCartInTheSession(): void
    {
        $this->sendRedirect(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

        $sessionData = $this->getSessionData();
        self::assertArrayHasKey('cart_1', $sessionData);

        $cart = unserialize($sessionData['cart_1']);
        self::assertInstanceOf(Cart::class, $cart);
        self::assertSame('de', $cart->getBillingCountry());
        self::assertSame('', $cart->getOrderNumber());
    }

    #[Test]
    public function canceledPaymentAddsFlashMessageForTheCart(): void
    {
        $this->sendRedirect(self::RESULT_PAYMENT_CANCELED, self::TYPOSCRIPT_SECRET);

        $sessionData = $this->getSessionData();
        self::assertArrayHasKey('extbase.flashmessages.tx_cart_cart', $sessionData);
        self::assertStringContainsString(
            'The payment process was aborted.',
            json_encode($sessionData['extbase.flashmessages.tx_cart_cart'], JSON_THROW_ON_ERROR)
        );
    }

    #[Test]
    public function redirectWithWrongHashIsRejected(): void
    {
        try {
            $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, 'wrong-secret');
            self::fail('The redirect with a wrong hash has been accepted.');
        } catch (GiroCheckout_SDK_Exception_helper $exception) {
            self::assertStringContainsString('hash mismatch', $exception->getMessage());
        }

        self::assertSame('open', $this->getPayment()['status']);
        self::assertSame([], EventRecorder::getEvents());
        self::assertSame([], $this->getSessionData());
    }

    #[Test]
    public function redirectWithoutCartHashIsRejected(): void
    {
        try {
            $this->sendRedirect(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET, null);
            self::fail('The redirect without cart hash has been accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(1734098478, $exception->getCode());
        }

        self::assertSame('open', $this->getPayment()['status']);
    }

    /**
     * Returns the session data of the frontend user, the test sends one
     * request, so there is at most one session.
     */
    private function getSessionData(): array
    {
        $sessions = $this->get(ConnectionPool::class)
            ->getConnectionForTable('fe_sessions')
            ->select(['ses_data'], 'fe_sessions')
            ->fetchAllAssociative();

        self::assertLessThanOrEqual(1, count($sessions));

        if ($sessions === []) {
            return [];
        }

        $sessionData = unserialize($sessions[0]['ses_data'], ['allowed_classes' => false]);
        self::assertIsArray($sessionData);

        return $sessionData;
    }
}
