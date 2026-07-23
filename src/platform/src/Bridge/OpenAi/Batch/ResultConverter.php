<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Batch;

use Symfony\AI\Platform\Batch\BatchJobResult;
use Symfony\AI\Platform\Batch\BatchResultConverterInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Converts an OpenAI batch-creation response into a {@see BatchJobResult}.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ResultConverter implements BatchResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Gpt && $model->supports(Capability::BATCH);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return new BatchJobResult(JobFactory::fromArray($result->getData()));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
