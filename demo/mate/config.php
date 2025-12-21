<?php

// User's service configuration file
// This file is loaded into the Symfony DI container

use App\Mate\SymfonyAiFeaturesTool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Override default parameters here
        // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        // ->set('mate.env_file', ['.env']) // This will load mate/.env and mate/.env.local
    ;

    $container->services()
        ->set(SymfonyAiFeaturesTool::class)
        ->arg('$projectDir', param('mate.root_dir'))
        ->tag('mcp.capability');
};
