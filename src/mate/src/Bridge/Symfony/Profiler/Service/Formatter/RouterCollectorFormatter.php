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
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<RouterDataCollector>
 */
final class RouterCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'router';
    }

    /**
     * @return array{redirect: bool, target_route: string|null, target_host: string|null}
     */
    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof RouterDataCollector);

        $targetHost = null;
        if (null !== $targetUrl = $collector->getTargetUrl()) {
            $host = parse_url($targetUrl, \PHP_URL_HOST);
            if (\is_string($host)) {
                $targetHost = $host;
            }
        }

        return [
            'redirect' => $collector->getRedirect(),
            'target_route' => $collector->getTargetRoute(),
            'target_host' => $targetHost,
        ];
    }

    /**
     * @return array{redirect: bool}
     */
    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof RouterDataCollector);

        return ['redirect' => $collector->getRedirect()];
    }
}
