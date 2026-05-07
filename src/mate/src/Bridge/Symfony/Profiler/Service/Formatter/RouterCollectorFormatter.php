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
 * Formats router collector data.
 *
 * Reports redirect information: whether the response was a redirect,
 * the target URL, and the guessed target route name.
 *
 * @author Johannes Wachter <johannes@sulu.io>
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

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof RouterDataCollector);

        return [
            'redirect' => $collector->getRedirect(),
            'target_url' => $collector->getTargetUrl(),
            'target_route' => $collector->getTargetRoute(),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof RouterDataCollector);

        return [
            'redirect' => $collector->getRedirect(),
            'target_route' => $collector->getTargetRoute(),
        ];
    }
}
