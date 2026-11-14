<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Functional\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Codappix\Typo3PhpDatasets\TestingFramework;
use Extcode\Cart\Domain\Model\Cart\Cart;
use Extcode\CartGirosolution\Tests\Functional\Fixtures\EventRecorder;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Provides an order paid with Girosolution credit card and sends the
 * notification Girosolution sends after the payment to the frontend.
 */
abstract class AbstractPaymentControllerTestCase extends FunctionalTestCase
{
    use TestingFramework;

    protected const CART_HASH = 'cart-hash-0001';

    protected const GC_REFERENCE = 'gc-reference-0001';

    /**
     * Password of the credit card payment, configured via TypoScript in
     * Tests/Functional/Fixtures/BaseDatabase.php
     */
    protected const TYPOSCRIPT_SECRET = 'credit-card-secret';

    protected const RESULT_PAYMENT_SUCCESSFUL = '4000';

    protected const RESULT_PAYMENT_CANCELED = '4502';

    protected array $testExtensionsToLoad = [
        'extcode/cart',
        'extcode/cart-girosolution',
        'typo3conf/ext/cart_girosolution/Tests/Functional/Fixtures/Extensions/girosolution_test',
    ];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/cart_girosolution/Tests/Functional/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
        'MAIL' => [
            'transport' => 'null',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importPHPDataSet(__DIR__ . '/../../Fixtures/BaseDatabase.php');
        $this->importPHPDataSet(__DIR__ . '/../../Fixtures/Order.php');

        $cart = new Cart([]);
        $cart->setBillingCountry('de');

        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_cart_domain_model_cart')
            ->insert('tx_cart_domain_model_cart', [
                'uid' => 1,
                'pid' => 2,
                's_hash' => self::CART_HASH,
                'order_item' => 1,
                'serialized_cart' => serialize($cart),
            ]);

        EventRecorder::reset();
    }

    protected function sendNotification(
        string $resultPayment,
        string $secret,
        string $cartHash = self::CART_HASH,
    ): ResponseInterface {
        $notification = [
            'gcReference' => self::GC_REFERENCE,
            'gcMerchantTxId' => md5('ORD-2026-0001'),
            'gcBackendTxId' => 'gc-backend-tx-0001',
            'gcAmount' => '11900',
            'gcCurrency' => 'EUR',
            'gcResultPayment' => $resultPayment,
        ];
        $notification['gcHash'] = hash_hmac('md5', implode('', $notification), $secret);

        // The controller reads the notification from $_GET, like the SDK of Girosolution expects it.
        $_GET = $notification;

        return $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withQueryParameters([
                    'type' => 2278106,
                    'tx_cartgirosolution_cart[controller]' => 'Order\\Payment',
                    'tx_cartgirosolution_cart[action]' => 'notify',
                    'tx_cartgirosolution_cart[hash]' => $cartHash,
                ] + $notification)
        );
    }

    protected function getPayment(): array
    {
        return $this->getRecord('tx_cart_domain_model_order_payment', ['status']);
    }

    protected function getTransaction(): array
    {
        return $this->getRecord('tx_cart_domain_model_order_transaction', ['status', 'external_status_code', 'note']);
    }

    private function getRecord(string $table, array $fields): array
    {
        $record = $this->get(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->select($fields, $table, ['uid' => 1])
            ->fetchAssociative();

        self::assertIsArray($record);

        return $record;
    }
}
