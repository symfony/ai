<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaResultConverter implements ResultConverterInterface
{
    /**
     * @param string $provider the name stamped onto the handles of the predictions this converter starts
     */
    public function __construct(
        private readonly string $provider = 'replicate',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    /**
     * Replicate answers a run with a prediction rather than a result, so this produces a job handle.
     */
    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult && 400 <= $result->getObject()->getStatusCode()) {
            $data = $result->getData();
            throw new RuntimeException(\sprintf('Replicate API error (HTTP %d): "%s".', $result->getObject()->getStatusCode(), $data['detail'] ?? $result->getObject()->getContent(false)));
        }

        $data = $result->getData();

        $id = $data['id'] ?? throw new RuntimeException(\sprintf('Replicate API error: "%s".', $data['detail'] ?? 'Response does not contain a prediction identifier.'));

        return new JobResult(new JobHandle(
            (string) $id,
            ['prediction_path' => \sprintf('v1/predictions/%s', $id)],
            $this->provider,
            ReplicateJobClient::DEFAULT_MAX_DURATION,
            ReplicateJobClient::DEFAULT_POLL_INTERVAL,
        ));
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
