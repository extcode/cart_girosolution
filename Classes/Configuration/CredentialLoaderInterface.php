<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

/*
 * This file is part of the package extcode/cart-girosolution.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

interface CredentialLoaderInterface
{
    public static function getDefaultPriority(): int;

    public function getCredentials(): array;
}
