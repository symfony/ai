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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();
        $task = $options['task'] ?? null;

        if (Task::Text2TextGeneration === $task) {
            $result = reset($data);

            return new TextResult($result['generated_text']);
        }

        if (Task::Embeddings == $task || Task::FeatureExtraction == $task) {
            return new VectorResult(
                ...array_map(static fn (array $vector) => new Vector($vector), $data),
            );
        }

        return new ObjectResult($data);
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
