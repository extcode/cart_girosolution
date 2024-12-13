<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;

final class CredentialsFromTypoScript implements CredentialLoaderInterface
{
    private array $credentials;

    public function __construct(
        private readonly ConfigurationManager $configurationManager,
    ) {
        $conf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartGirosolution'
        );
        if (isset($conf['credentials']) && is_array($conf['credentials'])) {
            $this->credentials = $conf['credentials'];
        } else {
            $this->credentials = [];
        }
    }

    public static function getDefaultPriority(): int
    {
        return 10;
    }

    public function getCredentials(): array
    {
        return $this->credentials;
    }
}
