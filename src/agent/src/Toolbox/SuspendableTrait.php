<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

/**
 * Provides a cooperative yield point for tool implementations.
 *
 * Use this trait in tool classes to signal that execution can be suspended at a given point, allowing
 * other concurrently running tools to make progress. The {@see self::suspend()} method is a no-op when
 * the tool is not running inside a Fiber (e.g. under the {@see SequentialToolExecutor} or in tests), so
 * it is safe to call regardless of the active tool executor.
 *
 * The yield point belongs between starting an I/O-bound operation and awaiting its outcome, so that
 * every concurrently running tool has its request on the wire before the first one blocks on a response:
 *
 *     use Symfony\AI\Agent\Toolbox\SuspendableTrait;
 *
 *     #[AsTool('weather', 'Fetches current weather data')]
 *     final class WeatherTool
 *     {
 *         use SuspendableTrait;
 *
 *         public function __invoke(string $city): string
 *         {
 *             $response = $this->httpClient->request('GET', '...'); // does not block yet
 *
 *             $this->suspend(); // let the other tools send their requests
 *
 *             return $response->getContent(); // blocks, but all responses are already on their way
 *         }
 *     }
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
trait SuspendableTrait
{
    /**
     * Yields execution to allow other concurrently running tools to make progress.
     *
     * This is a no-op when called outside of a PHP Fiber context.
     */
    protected function suspend(): void
    {
        if (null !== \Fiber::getCurrent()) {
            \Fiber::suspend();
        }
    }
}
