<?php

namespace Extcode\CartGirosolution\Configuration;

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
