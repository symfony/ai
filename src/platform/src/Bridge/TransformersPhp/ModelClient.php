<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;

use function Codewithkyrian\Transformers\Pipelines\pipeline;

final class ModelClient implements ModelClientInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawPipelineResult
    {
        if (null === $task = $options['task'] ?? null) {
            throw new InvalidArgumentException('The task option is required.');
        }

        $pipeline = pipeline(
            $task,
            $model->getName(),
            $options['quantized'] ?? true,
            $options['config'] ?? null,
            $options['cacheDir'] ?? null,
            $options['revision'] ?? 'main',
            $options['modelFilename'] ?? null,
        );

        return new RawPipelineResult(new PipelineExecution($pipeline, $payload));
    }
}
