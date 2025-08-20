<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\AI\Store\Command\DropStoreCommand;
use Symfony\AI\Store\Command\SetupStoreCommand;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('console.command.ai.setup_store', SetupStoreCommand::class)
            ->args([
                service('ai.store_locator'),
                [], // Store names
            ])
            ->tag('console.command')
        ->set('console.command.ai.drop_store', DropStoreCommand::class)
            ->args([
                service('ai.store_locator'),
                [], // Store names
            ])
            ->tag('console.command')
    ;
};
