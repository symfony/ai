<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Job\JobHandle;

/**
 * The provider accepted the work but has not done it yet.
 *
 * Returned by bridges whose backend answers a request with a job identifier instead of a result -
 * video generation, asynchronous speech synthesis, batch endpoints. The content is the
 * {@see JobHandle} needed to come back to that job, possibly from another process.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobResult extends BaseResult
{
    public function __construct(
        private JobHandle $handle,
    ) {
    }

    public function getContent(): JobHandle
    {
        return $this->handle;
    }

    /**
     * Binds the handle to the provider it came from.
     *
     * A `ResultConverter` does not know under which name its provider was registered - the name is a
     * `Provider` constructor argument - so `Provider` stamps it onto the handle once the result has
     * been converted, the same way it stamps the raw result.
     */
    public function bindProvider(string $provider): void
    {
        $this->handle = $this->handle->withProvider($provider);
    }
}
