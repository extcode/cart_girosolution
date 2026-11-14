<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Functional\Controller\Order;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartGirosolution\Configuration\CredentialLoaderRegistry;
use Extcode\CartGirosolution\Configuration\CredentialsFromConfVars;
use Extcode\CartGirosolution\Configuration\CredentialsFromTypoScript;
use girosolution\GiroCheckout_SDK\helper\GiroCheckout_SDK_Exception_helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * The credentials are configured via TypoScript and via
 * $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolution']. The
 * credentials of TYPO3_CONF_VARS have the higher priority and win.
 */
#[CoversClass(CredentialLoaderRegistry::class)]
#[CoversClass(CredentialsFromConfVars::class)]
#[CoversClass(CredentialsFromTypoScript::class)]
final class PaymentControllerWithCredentialsFromConfVarsTest extends AbstractPaymentControllerTestCase
{
    private const CONF_VARS_SECRET = 'credit-card-secret-from-conf-vars';

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance['EXTENSIONS']['cart_girosolution'] = [
            'credentials' => [
                'creditCard' => [
                    'password' => self::CONF_VARS_SECRET,
                ],
            ],
        ];

        parent::setUp();
    }

    #[Test]
    public function acceptsNotificationSignedWithPasswordOfConfVars(): void
    {
        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::CONF_VARS_SECRET);

        self::assertSame('paid', $this->getPayment()['status']);
    }

    #[Test]
    public function rejectsNotificationSignedWithPasswordOfTypoScript(): void
    {
        $this->expectException(GiroCheckout_SDK_Exception_helper::class);

        $this->sendNotification(self::RESULT_PAYMENT_SUCCESSFUL, self::TYPOSCRIPT_SECRET);
    }
}
