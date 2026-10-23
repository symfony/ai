<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<ConfigDataCollector>
 */
final class ConfigCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'config';
    }

    /**
     * @return array{symfony_version: string, php_version: string, environment: string, debug: bool|string, sapi: string, timezone: string, xdebug_enabled: bool, apcu_enabled: bool, opcache_enabled: bool}
     */
    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof ConfigDataCollector);

        return [
            ...$this->getSummary($collector),
            'sapi' => $collector->getSapiName(),
            'timezone' => $collector->getPhpTimezone(),
            'xdebug_enabled' => $collector->hasXdebug(),
            'apcu_enabled' => $collector->hasApcu(),
            'opcache_enabled' => $collector->hasZendOpcache(),
        ];
    }

    /**
     * @return array{symfony_version: string, php_version: string, environment: string, debug: bool|string}
     */
    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof ConfigDataCollector);

        return [
            'symfony_version' => $collector->getSymfonyVersion(),
            'php_version' => $collector->getPhpVersion(),
            'environment' => $collector->getEnv(),
            'debug' => $collector->isDebug(),
        ];
    }
}
