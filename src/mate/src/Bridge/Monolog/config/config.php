<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Mate\Bridge\Monolog\Capability\LogSearchTool;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogParser;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogReader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters()
        ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/log');

    $configurator->services()
        ->set(LogParser::class)

        ->set(LogReader::class)
            ->args([
                service(LogParser::class),
                '%ai_mate_monolog.log_dir%',
            ])

        ->set(LogSearchTool::class)
            ->args([
                service(LogReader::class),
            ]);
};
