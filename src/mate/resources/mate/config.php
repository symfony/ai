<?php

// User's service configuration file
// This file is loaded into the Symfony DI container

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Override default parameters here
        // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        // ->set('mate.env_file', ['.env']) // This will load .mate/.env and .mate/.env.local
    ;

    $container->services()
        // Register your custom services here
    ;
};
