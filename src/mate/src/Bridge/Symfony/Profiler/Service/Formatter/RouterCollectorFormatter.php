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
     * @return array{redirect: bool, route: string|null, target_host: string|null, target_path: string|null}
     */
    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof RouterDataCollector);

        $targetHost = null;
        $targetPath = null;
        if (null !== $targetUrl = $collector->getTargetUrl()) {
            $parts = parse_url($targetUrl);
            if (false !== $parts) {
                $targetHost = $parts['host'] ?? null;
                $targetPath = $parts['path'] ?? null;
            }
        }

        return [
            'redirect' => $collector->getRedirect(),
            'route' => 'n/a' === $collector->getTargetRoute() ? null : $collector->getTargetRoute(),
            'target_host' => $targetHost,
            'target_path' => $targetPath,
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
