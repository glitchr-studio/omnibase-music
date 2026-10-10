<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Everything in src/ is a plain autowired service, the way an application's
 * own src/ is. The backoffice CRUD controllers and the dashboard widget are
 * loaded only when omnibase/admin is installed.
 */
return function (ContainerConfigurator $configurator) {
    $src = dirname(__DIR__).'/src';

    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->public(false);

    $services->load('Base\\Music\\', $src.'/')
        ->exclude([
            $src.'/DependencyInjection/',
            $src.'/Entity/',
            $src.'/Enum/',
            $src.'/Controller/Admin/',
            $src.'/Admin/',
            $src.'/Service/ImportReport.php',
            $src.'/MusicBundle.php',
        ]);

    $services->load('Base\\Music\\Controller\\Client\\', $src.'/Controller/Client/')
        ->tag('controller.service_arguments');

    // The plays a robot sends are not counted: omnibase's user-agent classifier, when the core has it.
    if (class_exists('Base\\Service\\Analytics\\UserAgentClassifier')) {
        $services->get('Base\\Music\\Service\\Plays')
            ->arg('$classifier', service('Base\\Service\\Analytics\\UserAgentClassifier')->nullOnInvalid());
    }

    if (class_exists('Base\\Admin\\Controller\\AbstractCrudController')) {
        $services->load('Base\\Music\\Controller\\Admin\\', $src.'/Controller/Admin/')
            ->tag('controller.service_arguments');
        $services->load('Base\\Music\\Admin\\', $src.'/Admin/');
    }
};
