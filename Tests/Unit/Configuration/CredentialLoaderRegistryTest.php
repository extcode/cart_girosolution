<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Tests\Unit\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartGirosolution\Configuration\CredentialLoaderInterface;
use Extcode\CartGirosolution\Configuration\CredentialLoaderRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CredentialLoaderRegistry::class)]
final class CredentialLoaderRegistryTest extends UnitTestCase
{
    #[Test]
    public function returnsEmptyCredentialsWithoutLoaders(): void
    {
        $subject = new CredentialLoaderRegistry([]);

        self::assertSame([], $subject->getCredentials());
    }

    #[Test]
    public function returnsCredentialsOfASingleLoader(): void
    {
        $credentials = [
            'creditCard' => [
                'merchantId' => '1234',
                'projectId' => '5678',
                'password' => 'secret',
            ],
        ];

        $subject = new CredentialLoaderRegistry([
            $this->createLoader($credentials),
        ]);

        self::assertSame($credentials, $subject->getCredentials());
    }

    #[Test]
    public function ignoresLoadersWithoutCredentials(): void
    {
        $credentials = [
            'paypal' => [
                'password' => 'secret',
            ],
        ];

        $subject = new CredentialLoaderRegistry([
            $this->createLoader([]),
            $this->createLoader($credentials),
            $this->createLoader([]),
        ]);

        self::assertSame($credentials, $subject->getCredentials());
    }

    /**
     * The loaders are passed ordered by priority, the loader with the highest
     * priority comes first. Its values win, the following loaders only add
     * values which are missing so far.
     */
    #[Test]
    public function valuesOfEarlierLoadersOverrideValuesOfLaterLoaders(): void
    {
        $subject = new CredentialLoaderRegistry([
            $this->createLoader([
                'creditCard' => [
                    'password' => 'secret-from-first-loader',
                ],
            ]),
            $this->createLoader([
                'creditCard' => [
                    'merchantId' => '1234',
                    'password' => 'secret-from-second-loader',
                ],
                'giropay' => [
                    'password' => 'giropay-secret',
                ],
            ]),
        ]);

        self::assertSame(
            [
                'creditCard' => [
                    'merchantId' => '1234',
                    'password' => 'secret-from-first-loader',
                ],
                'giropay' => [
                    'password' => 'giropay-secret',
                ],
            ],
            $subject->getCredentials()
        );
    }

    #[Test]
    public function acceptsTraversableOfLoaders(): void
    {
        $credentials = [
            'paydirekt' => [
                'password' => 'secret',
            ],
        ];

        $subject = new CredentialLoaderRegistry(
            new \ArrayIterator([$this->createLoader($credentials)])
        );

        self::assertSame($credentials, $subject->getCredentials());
    }

    private function createLoader(array $credentials): CredentialLoaderInterface
    {
        $loader = self::createStub(CredentialLoaderInterface::class);
        $loader->method('getCredentials')->willReturn($credentials);

        return $loader;
    }
}
