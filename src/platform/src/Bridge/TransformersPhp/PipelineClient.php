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

use Codewithkyrian\Transformers\Pipelines\Task;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

use function Codewithkyrian\Transformers\Pipelines\pipeline;

/**
 * TransformersPHP pipeline client.
 *
 * The model isn't an HTTP endpoint — it's a function call into the
 * codewithkyrian/transformers package. Per-task result shaping happens here.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PipelineClient implements EndpointClientInterface
{
    public const ENDPOINT = 'transformers_php.pipeline';

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

        return new RawPipelineResult(new PipelineExecution($pipeline, $payload, $options['input_options'] ?? []));
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $data = $raw->getData();
        $task = $options['task'] ?? null;

        if (Task::Text2TextGeneration === $task) {
            $first = reset($data);

            return new TextResult($first['generated_text']);
        }

        if (Task::Embeddings == $task || Task::FeatureExtraction == $task) {
            return new VectorResult(array_map(
                static fn (array $vector): Vector => new Vector($vector),
                $data,
            ));
        }

        return new ObjectResult($data);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
