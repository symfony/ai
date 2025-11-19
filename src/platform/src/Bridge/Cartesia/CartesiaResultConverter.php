<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CartesiaResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Cartesia;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return match (true) {
            str_contains($response->getInfo('url'), 'stt') => new TextResult($result->getData()['text']),
            str_contains($response->getInfo('url'), 'tts') => new BinaryResult($result->getObject()->getContent(), 'audio/mpeg'),
            default => throw new RuntimeException('Unsupported Cartesia response.'),
        };
    }
}
