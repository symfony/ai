<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the jobs the Eden AI asynchronous endpoint hands out.
 *
 * `POST /v3/universal-ai/async` answers with a `public_id` and a state. Some providers have the
 * output ready by then and the invocation returns it straight away; the others report `processing`,
 * and the invocation returns a {@see JobHandle} pointing at
 * `GET /v3/universal-ai/async/{public_id}` instead - which this client reads, one request per call.
 * That endpoint is the only documented way to reach a finished job: the API exposes no separate
 * result route, so a job that never leaves `processing` has nowhere else to be fetched from.
 *
 * The subfeature the job came from is carried on the handle, because the shape to read out of a
 * finished job depends on it.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class EdenAiJobClient implements JobClientInterface
{
    use ErrorHandlingTrait;
    use ResultMetadataTrait;

    /**
     * The three states `UniversalAIAsyncResponse.status` is documented to take. Anything else stays
     * {@see JobStateCase::UNKNOWN}, which is non-terminal, so a state added after this was written
     * makes a runner keep polling rather than abort a job that is still running.
     *
     * @see https://api.edenai.run/v3/docs/openapi.json
     */
    private const STATES = [
        'processing' => JobStateCase::RUNNING,
        'success' => JobStateCase::SUCCEEDED,
        'fail' => JobStateCase::FAILED,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.edenai.run',
        private readonly string $provider = 'edenai',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param int                  $maxDuration how long this kind of job may reasonably take, in seconds
     */
    public function createHandle(string $publicId, array $data, int $maxDuration): JobHandle
    {
        return new JobHandle($publicId, $data, $this->provider, $maxDuration);
    }

    public function supports(JobHandle $handle): bool
    {
        return \is_string($handle->get('subfeature'));
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        $data = $this->query($handle);

        return $this->statusOf($data);
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $data = $this->query($handle);
        $status = $this->statusOf($data);

        if (!$status->is(JobStateCase::SUCCEEDED)) {
            throw new JobFailedException($status, \sprintf('The Eden AI job "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $status->getRaw()));
        }

        $subfeature = $handle->get('subfeature');

        if ('speech_to_text_async' !== $subfeature) {
            throw new RuntimeException(\sprintf('The Eden AI job "%s" is of subfeature "%s", which this client cannot read.', $handle->getId(), \is_string($subfeature) ? $subfeature : get_debug_type($subfeature)));
        }

        return $this->createSpeechToTextResult($handle, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function statusOf(array $data): JobStatus
    {
        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        $error = $data['error']['message'] ?? $data['error'] ?? null;

        return new JobStatus($case, $raw, \is_string($error) && '' !== $error ? $error : null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createSpeechToTextResult(JobHandle $handle, array $data): TextResult
    {
        if (!isset($data['output']['text']) || !\is_string($data['output']['text'])) {
            throw new RuntimeException(\sprintf('The finished Eden AI job "%s" does not contain text.', $handle->getId()));
        }

        $result = new TextResult($data['output']['text']);

        $this->attachEdenAiMetadata($result, $data, [
            'diarization' => $data['output']['diarization'] ?? null,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl.'/v3/universal-ai/async/'.rawurlencode($handle->getId()), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->assertSuccessfulResponse($response, [200, 202]);

        try {
            return $response->toArray(false);
        } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Could not decode the Eden AI job "%s".', $handle->getId()), 0, $e);
        }
    }
}
