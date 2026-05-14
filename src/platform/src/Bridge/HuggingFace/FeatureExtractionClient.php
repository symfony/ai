<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FeatureExtractionClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::FEATURE_EXTRACTION;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        /** @var list<float>|list<list<float>> $data */
        $data = $raw->getData();

        // Either an array of embeddings (one per input) or a single embedding vector.
        $vectors = isset($data[0]) && \is_array($data[0])
            ? array_map(static fn (array $v): Vector => new Vector($v), $data)
            : [new Vector($data)];

        return new VectorResult($vectors);
    }
}
