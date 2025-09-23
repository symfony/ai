<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Exception\HttpErrorHandler;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final readonly class ResultConverter implements ResultConverterInterface
{
    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    public function convert(RawHttpResult|RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();
        HttpErrorHandler::handleHttpError($response);

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($response));
        }

        $data = $result->getData();

        if (!isset($data['choices'][0]['message']['content'])) {
            if (isset($data['type'], $data['message']) && str_ends_with($data['type'], 'error')) {
                throw new RuntimeException(\sprintf('Cerebras API error: "%s"', $data['message']));
            }

            throw new RuntimeException('Response does not contain output.');
        }

        return new TextResult($data['choices'][0]['message']['content']);
    }

    private function convertStream(HttpResponse $result): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($result) as $chunk) {
            if (!$chunk instanceof ServerSentEvent || '[DONE]' === $chunk->getData()) {
                continue;
            }

            try {
                $data = $chunk->getArrayData();
            } catch (JsonException) {
                continue;
            }

            if (!isset($data['choices'][0]['delta']['content'])) {
                continue;
            }

            yield $data['choices'][0]['delta']['content'];
        }
    }
}
