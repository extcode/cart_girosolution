<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $container) {
    $services = $containerConfigurator->services();
    $services
        ->instanceof(CredentialLoaderInterface::class)
        ->tag('cartGirosolution.credentialLoader');

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->load(
            'Extcode\\CartGirosolution\\Configuration\\',
            '../Classes/Configuration/'
        );

    $services->set(CredentialLoaderRegistry::class)
        ->arg('$credentialLoaders', tagged_iterator('cartGirosolution.credentialLoader'));
};
