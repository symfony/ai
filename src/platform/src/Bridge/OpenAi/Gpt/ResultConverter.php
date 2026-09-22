<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Batch\JobClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ResultConverter extends OpenResponsesResultConverter
{
    use HttpStatusErrorHandlingTrait;

    /**
     * @param string $provider the name stamped onto the handles of the batches this converter starts
     */
    public function __construct(
        private readonly string $provider = 'openai',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        // A batched invocation is answered with the batch OpenAI created, not with what it will produce.
        if ($options[ModelClient::BATCH] ?? false) {
            return $this->startBatch($result);
        }

        return parent::convert($result, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    protected function extractRateLimitReset(ResponseInterface $response): ?int
    {
        $headers = $response->getHeaders(false);
        $resetTime = $headers['x-ratelimit-reset-requests'][0]
            ?? $headers['x-ratelimit-reset-tokens'][0]
            ?? null;

        return null !== $resetTime ? self::parseResetTime($resetTime) : null;
    }

    /**
     * Converts OpenAI's reset time format (e.g. "1s", "6m0s", "2m30s") into seconds.
     *
     * Supported formats:
     * - "1s"
     * - "6m0s"
     * - "2m30s"
     */
    private static function parseResetTime(string $resetTime): ?int
    {
        if (preg_match('/^(?:(\d+)m)?(?:(\d+)s)?$/', $resetTime, $matches)) {
            $minutes = isset($matches[1]) ? (int) $matches[1] : 0;
            $secs = isset($matches[2]) ? (int) $matches[2] : 0;

            return ($minutes * 60) + $secs;
        }

        return null;
    }

    /**
     * OpenAI accepted the batch, so the invocation produces a reference to it rather than a result;
     * resolving it is the job of {@see JobClient}.
     */
    private function startBatch(RawResultInterface|RawHttpResult $result): JobResult
    {
        $this->throwOnHttpError($result->getObject());

        $data = $result->getData();
        $id = $data['id'] ?? null;

        if (!\is_string($id) || '' === $id) {
            throw new RuntimeException('The OpenAI response does not contain a batch identifier.');
        }

        $endpoint = $data['endpoint'] ?? null;

        return new JobResult(new JobHandle($id, [
            'kind' => JobClient::KIND,
            'endpoint' => \is_string($endpoint) ? $endpoint : ModelClient::PATH,
        ], $this->provider, self::windowToSeconds($data['completion_window'] ?? null), JobClient::DEFAULT_POLL_INTERVAL));
    }

    /**
     * The completion window is the longest the batch may take, carried on the handle so nobody has to guess.
     */
    private static function windowToSeconds(mixed $window): int
    {
        if (\is_string($window) && 1 === preg_match('/^(\d+)h$/', $window, $matches) && 0 < (int) $matches[1]) {
            return (int) $matches[1] * 3600;
        }

        return JobClient::DEFAULT_MAX_DURATION;
    }
}
