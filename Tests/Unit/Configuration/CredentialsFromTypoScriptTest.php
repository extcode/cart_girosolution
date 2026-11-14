<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Unit\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartGirosolution\Configuration\CredentialsFromTypoScript;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CredentialsFromTypoScript::class)]
final class CredentialsFromTypoScriptTest extends UnitTestCase
{
    #[Test]
    public function defaultPriorityIsLowerThanTheOneOfConfVars(): void
    {
        self::assertSame(10, CredentialsFromTypoScript::getDefaultPriority());
    }

    #[Test]
    public function readsTheFrameworkConfigurationOfTheExtension(): void
    {
        $configurationManager = $this->createMock(ConfigurationManager::class);
        $configurationManager
            ->expects(self::once())
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK, 'CartGirosolution')
            ->willReturn([]);

        new CredentialsFromTypoScript($configurationManager);
    }

    #[Test]
    public function returnsCredentialsOfTheTypoScript(): void
    {
        $credentials = [
            'giropay' => [
                'merchantId' => '1234',
                'projectId' => '5678',
                'password' => 'secret',
            ],
        ];

        $subject = new CredentialsFromTypoScript(
            $this->createConfigurationManager([
                'redirectTypeNum' => '2278106',
                'credentials' => $credentials,
            ])
        );

        self::assertSame($credentials, $subject->getCredentials());
    }

    public static function configurationWithoutCredentialsDataProvider(): \Generator
    {
        yield 'empty configuration' => [[]];
        yield 'configuration without credentials' => [['redirectTypeNum' => '2278106']];
        yield 'credentials as string' => [['credentials' => 'secret']];
    }

    #[Test]
    #[DataProvider('configurationWithoutCredentialsDataProvider')]
    public function returnsEmptyCredentialsWithoutCredentialsInTypoScript(array $configuration): void
    {
        $subject = new CredentialsFromTypoScript(
            $this->createConfigurationManager($configuration)
        );

        self::assertSame([], $subject->getCredentials());
    }

    private function createConfigurationManager(array $configuration): ConfigurationManager
    {
        $configurationManager = self::createStub(ConfigurationManager::class);
        $configurationManager->method('getConfiguration')->willReturn($configuration);

        return $configurationManager;
    }
}
