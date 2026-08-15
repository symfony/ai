<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\Job;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\LogicException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobPlatformInterface;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Resolves jobs of exactly one provider and rejects the rest, the way `Platform` does - enough to
 * check that a decorator forwards `getJobClient()` instead of swallowing it.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SingleProviderJobPlatform implements PlatformInterface, JobPlatformInterface
{
    public function __construct(
        private readonly string $provider,
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        throw new LogicException('Resolving a job handle must not invoke the platform.');
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        throw new LogicException('Resolving a job handle must not need the model catalog.');
    }

    public function getJobClient(JobHandle $handle): JobClientInterface
    {
        if ($handle->getProvider() !== $this->provider) {
            throw new InvalidArgumentException(\sprintf('No provider named "%s" is configured on this platform.', $handle->getProvider() ?? ''));
        }

        return new class implements JobClientInterface {
            public function supports(JobHandle $handle): bool
            {
                return true;
            }

            public function getStatus(JobHandle $handle): JobStatus
            {
                return new JobStatus(JobStateCase::SUCCEEDED, 'Success');
            }

            public function getResult(JobHandle $handle): ResultInterface
            {
                return new TextResult('done');
            }
        };
    }
}
