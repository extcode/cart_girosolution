<?php

declare(strict_types=1);

namespace Extcode\CartGirosolution\Configuration;

use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\Cart\EventListener\Order\Finish\Email;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Extcode\CartGirosolution\EventListener\Order\Payment\ClearCart;
use Extcode\CartGirosolution\EventListener\Order\Payment\ProviderRedirect;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $container): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services
        ->instanceof(CredentialLoaderInterface::class)
        ->tag('cartGirosolution.credentialLoader');

    $services
        ->load('Extcode\\CartGirosolution\\', '../Classes/*');

    $services->set(CredentialLoaderRegistry::class)
        ->arg('$credentialLoaders', tagged_iterator('cartGirosolution.credentialLoader'));

    $services->set(ClearCart::class)
        ->tag('event.listener', [
            'identifier' => 'cart-girosolution--order--payment--clear-cart',
            'event' => PaymentEvent::class,
            'before' => 'cart-girosolution--order--payment--provider-redirect',
        ]);

    $services->set(ProviderRedirect::class)
        ->tag('event.listener', [
            'identifier' => 'cart-girosolution--order--payment--provider-redirect',
            'event' => PaymentEvent::class,
        ]);

    $services->set('Extcode\\CartGirosolution\\EventListener\\Order\\Finish\\Email', Email::class)
        ->tag('event.listener', [
            'identifier' => 'cart-girosolution--order--finish--email',
            'event' => FinishEvent::class,
        ]);
};
