<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsLab;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Adhik Joshi <adhik@modelslab.com>
 */
final class ModelsLabResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof ModelsLab;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['binary']) || !\is_string($data['binary'])) {
            throw new RuntimeException('ModelsLab result converter: expected binary image data.');
        }

        return new BinaryResult($data['binary'], $data['content_type'] ?? 'image/jpeg');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
