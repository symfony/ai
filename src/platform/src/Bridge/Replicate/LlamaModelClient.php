<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!$model instanceof Llama) {
            throw new InvalidArgumentException(\sprintf('The model must be an instance of "%s".', Llama::class));
        }

        return new RawHttpResult(
            $this->client->request(\sprintf('meta/meta-%s', $model->getName()), 'predictions', $payload)
        );
    }
}
