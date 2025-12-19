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
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult|ObjectResult
    {
        $data = $result->getData();

        if (Task::Text2TextGeneration === $options['task']) {
            $result = reset($data);

            return new TextResult($result['generated_text']);
        }

        return new ObjectResult($data);
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
