<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class ElevenLabsResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof ElevenLabs;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return match (true) {
            $response instanceof \Generator => new StreamResult($response),
            str_contains($response->getInfo('url'), 'speech-to-text') => new TextResult($result->getData()['text']),
            str_contains($response->getInfo('url'), 'text-to-speech') => new BinaryResult($result->getObject()->getContent(), 'audio/mpeg'),
            default => throw new RuntimeException('Unsupported ElevenLabs response.'),
        };
    }
}
