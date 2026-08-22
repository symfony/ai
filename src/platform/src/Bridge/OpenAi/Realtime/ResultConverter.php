<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Realtime;

use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
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
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Realtime;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The OpenAI Realtime API returned an error: "%s"', $response->getContent(false)));
        }

        $data = $response->toArray();

        $clientSecret = $data['client_secret']['value'] ?? ($data['client_secret'] ?? '');
        $expiresAt = (int) ($data['client_secret']['expires_at'] ?? ($data['expires_at'] ?? 0));

        return new RealtimeSessionResult(
            id: $data['id'] ?? '',
            clientSecret: $clientSecret,
            expiresAt: $expiresAt,
            model: $data['model'] ?? '',
            voice: $data['voice'] ?? null,
            modalities: $data['modalities'] ?? ['text', 'audio'],
            raw: $data,
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
