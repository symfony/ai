<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Video;

use Symfony\AI\Platform\Bridge\OpenRouter\VideoGenerationModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Turns the answer to a video submission into a {@see JobResult}, resolved by {@see JobClient}.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Video generation usually finishes within one or two minutes, but can take much longer under load.
     */
    public const MAX_DURATION = 600;

    /**
     * @param string $provider the name stamped onto the handles of the jobs this converter starts
     */
    public function __construct(
        private readonly string $provider = 'openrouter',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof VideoGenerationModel;
    }

    public function convert(RawResultInterface $result, array $options = []): JobResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        $data = $response->toArray(false);

        if (!isset($data['id']) || '' === (string) $data['id']) {
            throw new RuntimeException('The video generation request did not return a job ID.');
        }

        return new JobResult(new JobHandle((string) $data['id'], [
            'type' => JobClient::TYPE,
        ], $this->provider, self::MAX_DURATION));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
