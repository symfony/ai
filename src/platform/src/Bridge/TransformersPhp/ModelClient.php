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
use Symfony\AI\Platform\PlatformOptions;

use function Codewithkyrian\Transformers\Pipelines\pipeline;

final class ModelClient implements ModelClientInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawPipelineResult
    {
        $providerOptions = $options[PlatformOptions::PROVIDER_OPTIONS] ?? [];

        if (null === $task = $providerOptions['task'] ?? null) {
            throw new InvalidArgumentException('The task option is required.');
        }

        $inputOptions = $providerOptions['input_options'] ?? [];

        $pipeline = pipeline(
            $task,
            $model->getName(),
            $providerOptions['quantized'] ?? true,
            $providerOptions['config'] ?? null,
            $providerOptions['cacheDir'] ?? null,
            $providerOptions['revision'] ?? 'main',
            $providerOptions['modelFilename'] ?? null,
        );

        return new RawPipelineResult(new PipelineExecution($pipeline, $payload, $inputOptions));
    }
}
