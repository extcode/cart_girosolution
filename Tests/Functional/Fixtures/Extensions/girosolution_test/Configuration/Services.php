<?php

declare(strict_types=1);

use Extcode\CartGirosolution\Event\Order\CancelEvent;
use Extcode\CartGirosolution\Event\Order\FinishEvent;
use Extcode\GirosolutionTest\EventListener\EventRecorder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->set(EventRecorder::class)
        ->tag('event.listener', [
            'identifier' => 'girosolution-test--order--finish--event-recorder',
            'event' => FinishEvent::class,
        ])
        ->tag('event.listener', [
            'identifier' => 'girosolution-test--order--cancel--event-recorder',
            'event' => CancelEvent::class,
        ]);
};
