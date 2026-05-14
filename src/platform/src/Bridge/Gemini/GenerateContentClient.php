<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TransportInterface;

/**
 * Google `generateContent` / `streamGenerateContent` contract handler.
 *
 * Shared between the Google AI Studio (`generativelanguage.googleapis.com`)
 * and Vertex AI (`aiplatform.googleapis.com`) transports — the only
 * meaningful contract-level difference is the JSON-schema response key
 * (Gemini direct expects `responseJsonSchema`, Vertex AI expects
 * `responseSchema`), exposed via a constructor flag.
 *
 * @phpstan-type Part array{
 *     functionCall?: array{id?: string, name: string, args: mixed[]},
 *     text?: string,
 *     thought?: bool,
 *     thoughtSignature?: string,
 *     inlineData?: array{data: string, mimeType: string},
 *     executableCode?: array{language: string, code: string},
 *     codeExecutionResult?: array{id?: string, outcome: self::OUTCOME_*, output: string},
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GenerateContentClient implements EndpointClientInterface
{
    public const ENDPOINT = 'google.generate_content';

    public const RESPONSE_SCHEMA_KEY_GEMINI = 'responseJsonSchema';
    public const RESPONSE_SCHEMA_KEY_VERTEX_AI = 'responseSchema';

    public const OUTCOME_OK = 'OUTCOME_OK';
    public const OUTCOME_FAILED = 'OUTCOME_FAILED';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $responseSchemaKey = self::RESPONSE_SCHEMA_KEY_GEMINI,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $method = ($options['stream'] ?? false) ? 'streamGenerateContent' : 'generateContent';

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options[$this->responseSchemaKey] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $config = ['generationConfig' => $options];
        unset(
            $config['generationConfig']['stream'],
            $config['generationConfig']['tools'],
            $config['generationConfig']['tool_config'],
            $config['generationConfig']['server_tools'],
        );

        if ([] === $config['generationConfig']) {
            $config = [];
        }

        if (isset($options['tools'])) {
            $config['tools'][] = ['functionDeclarations' => $options['tools']];
        }

        if (isset($options['tool_config'])) {
            $config['tool_config'] = $options['tool_config'];
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }
            $config['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        $envelope = new RequestEnvelope(
            payload: array_merge($config, $payload),
            path: \sprintf('models/%s:%s', $model->getName(), $method),
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error'])) {
            $code = $data['error']['code'] ?? '-';
            $status = $data['error']['status'] ?? '-';
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new RuntimeException(\sprintf('Error "%s" - "%s": "%s".', $code, $status, $message));
        }

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            throw new RuntimeException('Response does not contain any content.');
        }

        $choices = array_map($this->convertChoice(...), $data['candidates']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $choices = array_values(array_filter(array_map($this->convertChoice(...), $data['candidates'] ?? [])));

            if ([] === $choices) {
                continue;
            }

            if (1 !== \count($choices)) {
                yield new ChoiceDelta(array_map($this->resultToDelta(...), $choices));
                continue;
            }

            yield $this->resultToDelta($choices[0]);
        }
    }

    private function resultToDelta(ToolCallResult|TextResult|BinaryResult $result): DeltaInterface
    {
        return match (true) {
            $result instanceof TextResult => new TextDelta($result->getContent()),
            $result instanceof BinaryResult => new BinaryDelta($result->getContent(), $result->getMimeType()),
            $result instanceof ToolCallResult => new ToolCallComplete($result->getContent()),
        };
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content?: array{parts: list<array<string, mixed>>},
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|MultiPartResult|null
    {
        if (!isset($choice['content']['parts'])) {
            return null;
        }

        $parts = $choice['content']['parts'];

        return match (\count($parts)) {
            1 => $this->convertPart($parts[0]),
            default => new MultiPartResult(array_values(array_filter(array_map($this->convertPart(...), $parts)))),
        };
    }

    /**
     * @param array<string, mixed> $contentPart
     */
    private function convertPart(array $contentPart): ToolCallResult|TextResult|ThinkingResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|null
    {
        $signature = $contentPart['thoughtSignature'] ?? null;

        return match (true) {
            isset($contentPart['functionCall']) => new ToolCallResult([new ToolCall(
                $contentPart['functionCall']['id'] ?? '',
                $contentPart['functionCall']['name'],
                $contentPart['functionCall']['args'] ?? [],
                $signature,
            )]),
            true === ($contentPart['thought'] ?? false) => new ThinkingResult($contentPart['text'] ?? '', $signature),
            isset($contentPart['text']) => new TextResult($contentPart['text'], $signature),
            isset($contentPart['inlineData']) => BinaryResult::fromBase64(
                $contentPart['inlineData']['data'],
                $contentPart['inlineData']['mimeType'] ?? null,
            ),
            isset($contentPart['executableCode']) => new ExecutableCodeResult(
                $contentPart['executableCode']['code'],
                $contentPart['executableCode']['language'],
                $contentPart['executableCode']['id'] ?? null,
            ),
            isset($contentPart['codeExecutionResult']) => new CodeExecutionResult(
                self::OUTCOME_OK === $contentPart['codeExecutionResult']['outcome'],
                $contentPart['codeExecutionResult']['output'],
                $contentPart['codeExecutionResult']['id'] ?? null,
            ),
            default => null,
        };
    }
}
