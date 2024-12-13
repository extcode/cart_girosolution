<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class CredentialsFromConfVars implements CredentialLoaderInterface
{
    private array $credentials;

    public function __construct(
        readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        $conf = $this->extensionConfiguration->get('cart_girosolutions');

        if (isset($conf['credentials']) && is_array($conf['credentials'])) {
            $this->credentials = $conf['credentials'];
        } else {
            $this->credentials = [];
        }
    }

    public static function getDefaultPriority(): int
    {
        return 30;
    }

    public function getCredentials(): array
    {
        return $this->credentials;
    }
}
