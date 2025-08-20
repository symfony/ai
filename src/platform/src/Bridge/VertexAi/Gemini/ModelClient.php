<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Gemini;

use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final readonly class ModelClient implements ModelClientInterface
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private string $location,
        private string $projectId,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function request(BaseModel $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf(
            'https://aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:%s',
            $this->projectId,
            $this->location,
            $model->getName(),
            $options['stream'] ?? false ? 'streamGenerateContent' : 'generateContent',
        );

        if (isset($options['response_format']['json_schema']['schema'])) {
            $options['generationConfig']['responseMimeType'] = 'application/json';
            $options['generationConfig']['responseSchema'] = $options['response_format']['json_schema']['schema'];

            unset($options['response_format']);
        }

        if (isset($options['generationConfig'])) {
            $options['generationConfig'] = (object) $options['generationConfig'];
        }

        if (isset($options['stream'])) {
            $options['generation_config'] = (object) ($options['generationConfig'] ?? []);

            unset($options['generationConfig'], $options['stream']);
        }

        if (isset($options['tools'])) {
            $tools = $options['tools'];

            unset($options['tools']);

            $options['tools'][] = ['functionDeclarations' => $tools];
        }

        if (\is_string($payload)) {
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $payload],
                        ],
                    ],
                ],
            ];
        }

        return new RawHttpResult(
            $this->httpClient->request(
                'POST',
                $url,
                [
                    'json' => array_merge($options, $payload),
                ]
            )
        );
    }
}
