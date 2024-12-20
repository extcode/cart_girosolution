<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

final class CredentialLoaderRegistry
{
    /**
     * @param CredentialLoaderRegistry[] $credentialLoaders
     */
    public function __construct(private readonly iterable $credentialLoaders) {}

    public function getCredentials(): array
    {
        $credentials = [];

        foreach ($this->credentialLoaders as $credentialLoader) {
            if ($credentialLoader->getCredentials()) {
                $credentials = array_replace_recursive($credentialLoader->getCredentials(), $credentials);
            }
        }

        return $credentials;
    }
}
