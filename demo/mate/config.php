<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// This file is loaded into the Symfony DI container

use Mate\SymfonyAiFeaturesTool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // The command your coding agent must use to run Mate. It is materialized into the
        // generated agent instructions, so a container prefix ends up where the agent reads it.
        ->set('mate.invocation', 'symfony php vendor/bin/mate')

        // The PHP version this project runs on. Mate refuses to start under a different
        // major.minor, because it would then report on a runtime that is not your application's
        // and extensions may behave differently. Set to null to disable the check.
        ->set('mate.php_version', '8.5')

        // Override default parameters here
        // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        // ->set('mate.env_file', ['.env']) // This will load mate/.env and mate/.env.local
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        ->set(SymfonyAiFeaturesTool::class)
            ->arg('$rootDir', '%mate.root_dir%')
            ->public()
    ;
};
