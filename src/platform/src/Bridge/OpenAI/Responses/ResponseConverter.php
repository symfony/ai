<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAI\Responses;

use Symfony\AI\Platform\Bridge\OpenAI\Responses;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\Output;
use Symfony\AI\Platform\Response\OutputResponse;
use Symfony\AI\Platform\Response\ResponseInterface as LlmResponse;
use Symfony\AI\Platform\Response\StreamResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\ResponseConverterInterface as PlatformResponseConverter;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ResponseConverter implements PlatformResponseConverter
{
    public function supports(Model $model): bool
    {
        return $model instanceof Responses;
    }

    public function convert(HttpResponse $response, array $options = []): LlmResponse
    {
        if ($options['stream'] ?? false) {
            return new StreamResponse($this->convertStream($response));
        }

        try {
            $data = $response->toArray();
        } catch (ClientExceptionInterface $e) {
            $data = $response->toArray(throw: false);

            if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
                throw new ContentFilterException(message: $data['error']['message'], previous: $e);
            }

            throw $e;
        }

        if (!isset($data['output'])) {
            throw new RuntimeException('Response does not contain output');
        }

        /** @var Output[] $outputs */
        $outputs = array_map($this->convertOutput(...), $data['output']);

        if (1 !== \count($outputs)) {
            return new OutputResponse(...$outputs);
        }

        return new TextResponse($outputs[0]->getContent());
    }

    private function convertStream(HttpResponse $response): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
            if (!$chunk instanceof ServerSentEvent || '[DONE]' === $chunk->getData()) {
                continue;
            }

            try {
                $data = $chunk->getArrayData();
            } catch (JsonException) {
                // try catch only needed for Symfony 6.4
                continue;
            }

            if (!isset($data['delta'])) {
                continue;
            }

            yield $data['delta'];
        }
    }

    /**
     * @param array{
     *     id: string,
     *     type: 'message|function_call',
     *     status: string,
     *     content: array{
     *         type: string,
     *         annotations: array,
     *         text: ?string,
     *         logprobs: array,
     *     },
     *     role: string,
     * } $output
     */
    private function convertOutput(array $output): Output
    {
        if (\in_array($output['status'], ['completed'], true)) {
            return new Output($output['content'][0]['text']);
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $output['incomplete_details']['reason']));
    }
}
