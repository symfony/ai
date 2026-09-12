<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Completions;

use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter as GenericResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * Albert reports the environmental footprint of a call next to the token usage, as an estimated
 * energy consumption ("kWh") and greenhouse gas emission ("kgCO2eq") range. It is exposed as the
 * "carbon" result metadata, in the shape the API returns it:
 *
 *     ['kWh' => ['min' => float, 'max' => float], 'kgCO2eq' => ['min' => float, 'max' => float]]
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter extends GenericResultConverter
{
    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $converted = parent::convert($result, $options);

        // A streamed footprint is only known once the stream is consumed and arrives as a metadata delta.
        if ($options['stream'] ?? false) {
            return $converted;
        }

        if (null !== $carbon = $result->getData()['usage']['carbon'] ?? null) {
            $converted->getMetadata()->add('carbon', $carbon);
        }

        return $converted;
    }

    /**
     * Albert's gateway appends its own usage chunk after the one of the inference server it routes
     * to, and the two disagree: only the gateway's is what a buffered response reports, and it is
     * the one carrying the footprint. Aggregating both would roughly double the count, so only the
     * last one survives - which costs nothing, as a streamed usage is metadata of the finished
     * stream anyway.
     *
     * @return \Generator<DeltaInterface>
     */
    protected function convertStream(RawResultInterface $result): \Generator
    {
        $usage = null;

        foreach (parent::convertStream($result) as $delta) {
            if ($delta instanceof TokenUsage) {
                $usage = $delta;

                continue;
            }

            yield $delta;
        }

        if (null !== $usage) {
            yield $usage;
        }
    }

    protected function yieldChunkMetadata(array $data): \Generator
    {
        if (isset($data['usage']['carbon'])) {
            yield new MetadataDelta('carbon', $data['usage']['carbon']);
        }
    }
}
