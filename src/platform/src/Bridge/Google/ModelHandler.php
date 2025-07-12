<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Google;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Response\Choice;
use Symfony\AI\Platform\Response\ChoiceResponse;
use Symfony\AI\Platform\Response\ResponseInterface as LlmResponse;
use Symfony\AI\Platform\Response\StreamResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Response\ToolCallResponse;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Roy Garrido
 */
final readonly class ModelHandler implements ModelClientInterface, ResponseConverterInterface
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function request(Model $model, array|string $payload, array $options = []): ResponseInterface
    {
        $url = \sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:%s',
            $model->getName(),
            $options['stream'] ?? false ? 'streamGenerateContent' : 'generateContent',
        );

        if (isset($options['response_format']['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options['responseJsonSchema'] = $options['response_format']['json_schema']['schema'];
            unset($options['response_format']);
        }

        $generationConfig = ['generationConfig' => $options];
        unset($generationConfig['generationConfig']['stream']);
        unset($generationConfig['generationConfig']['tools']);
        unset($generationConfig['generationConfig']['server_tools']);

        if (isset($options['tools'])) {
            $generationConfig['tools'][] = ['functionDeclarations' => $options['tools']];
            unset($options['tools']);
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }

            $generationConfig['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        return $this->httpClient->request('POST', $url, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => array_merge($generationConfig, $payload),
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function convert(ResponseInterface $response, array $options = []): LlmResponse
    {
        if ($options['stream'] ?? false) {
            return new StreamResponse($this->convertStream($response));
        }

        $data = $response->toArray();

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            throw new RuntimeException('Response does not contain any content');
        }

        /** @var Choice[] $choices */
        $choices = array_map($this->convertChoice(...), $data['candidates']);

        if (1 !== \count($choices)) {
            return new ChoiceResponse(...$choices);
        }

        if ($choices[0]->hasToolCall()) {
            return new ToolCallResponse(...$choices[0]->getToolCalls());
        }

        return new TextResponse($choices[0]->getContent());
    }

    private function convertStream(ResponseInterface $response): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $jsonDelta = trim($chunk->getContent());

            // Remove leading/trailing brackets
            if (str_starts_with($jsonDelta, '[') || str_starts_with($jsonDelta, ',')) {
                $jsonDelta = substr($jsonDelta, 1);
            }
            if (str_ends_with($jsonDelta, ']')) {
                $jsonDelta = substr($jsonDelta, 0, -1);
            }

            // Split in case of multiple JSON objects
            $deltas = explode(",\r\n", $jsonDelta);

            foreach ($deltas as $delta) {
                if ('' === $delta) {
                    continue;
                }

                try {
                    $data = json_decode($delta, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new RuntimeException('Failed to decode JSON response', 0, $e);
                }

                /** @var Choice[] $choices */
                $choices = array_map($this->convertChoice(...), $data['candidates'] ?? []);

                if (!$choices) {
                    continue;
                }

                if (1 !== \count($choices)) {
                    yield new ChoiceResponse(...$choices);
                    continue;
                }

                if ($choices[0]->hasToolCall()) {
                    yield new ToolCallResponse(...$choices[0]->getToolCalls());
                }

                if ($choices[0]->hasContent()) {
                    yield $choices[0]->getContent();
                }
            }
        }
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content: array{
     *         parts: array{
     *             functionCall?: array{
     *                 id: string,
     *                 name: string,
     *                 args: mixed[]
     *             },
     *             text?: string
     *         }[]
     *     }
     * } $choice
     */
    private function convertChoice(array $choice): Choice
    {
        $contentPart = $choice['content']['parts'][0] ?? [];

        if (isset($contentPart['functionCall'])) {
            return new Choice(toolCalls: [$this->convertToolCall($contentPart['functionCall'])]);
        }

        if (isset($contentPart['text'])) {
            return new Choice($contentPart['text']);
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finishReason']));
    }

    /**
     * @param array{
     *     id?: string,
     *     name: string,
     *     args: mixed[]
     * } $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        return new ToolCall($toolCall['id'] ?? '', $toolCall['name'], $toolCall['args']);
    }
}
