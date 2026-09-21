<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class HiggsfieldResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * @param string $provider the name stamped onto the handles of the generations this converter starts
     */
    public function __construct(
        private readonly string $provider = 'higgsfield',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Higgsfield;
    }

    /**
     * Higgsfield answers with a request identifier instead of media, so this produces a job handle.
     */
    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        $data = $response->toArray(false);

        $requestId = $data['request_id'] ?? throw new RuntimeException(\sprintf('Higgsfield API error: "%s".', $this->extractErrorMessage($response) ?? 'Unknown error'));

        return new JobResult(new JobHandle(
            (string) $requestId,
            ['status_path' => \sprintf('requests/%s/status', $requestId)],
            $this->provider,
            HiggsfieldJobClient::DEFAULT_MAX_DURATION,
            HiggsfieldJobClient::DEFAULT_POLL_INTERVAL,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * Overrides the shared lookup: Higgsfield puts its message into `detail` rather than `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            return HiggsfieldJobClient::extractError($response->toArray(false));
        } catch (DecodingExceptionInterface) {
            return null;
        }
    }
}
