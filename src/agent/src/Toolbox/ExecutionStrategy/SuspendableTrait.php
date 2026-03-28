<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ExecutionStrategy;

/**
 * Provides a cooperative yield point for tool implementations.
 *
 * Use this trait in tool classes to signal that execution can be suspended at
 * a given point, allowing other concurrently running tools to make progress.
 * This is a no-op when the tool is not running inside a Fiber (e.g. under the
 * sequential execution strategy or in tests), so it is safe to call regardless
 * of the active execution strategy.
 *
 * Example usage before an I/O-bound operation:
 *
 *     use Symfony\AI\Agent\Toolbox\ExecutionStrategy\SuspendableTrait;
 *
 *     #[AsTool('weather', 'Fetches current weather data')]
 *     final class WeatherTool
 *     {
 *         use SuspendableTrait;
 *
 *         public function __invoke(string $city): string
 *         {
 *             $this->suspend(); // yield before the blocking HTTP call
 *             $response = $this->httpClient->request('GET', '...');
 *
 *             return $response->getContent();
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
