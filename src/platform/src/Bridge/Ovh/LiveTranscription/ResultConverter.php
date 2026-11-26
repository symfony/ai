<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\LiveTranscription;

use Symfony\AI\Platform\Bridge\Ovh\LiveTranscription;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Converts the response of "POST /gladia/v2/live" into a {@see RealtimeSessionResult}, whose client secret is
 * the WebSocket URL to stream the audio to, which holds the temporary token authenticating the session.
 *
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof LiveTranscription;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if (!\in_array($response->getStatusCode(), [200, 201], true)) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $response->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['id'], $data['url'])) {
            throw new RuntimeException('Response does not contain the id and the URL of the session.');
        }

        $model = $response->getInfo('user_data');

        return new RealtimeSessionResult(
            id: $data['id'],
            clientSecret: $data['url'],
            expiresAt: 0,
            model: \is_string($model) ? $model : '',
            modalities: ['text'],
            raw: $data,
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
