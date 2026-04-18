<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Extracts raw payload data from Symfony profiler collectors.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
trait ExtractsCollectorDataTrait
{
    /**
     * @return array<string, mixed>
     */
    private function extractCollectorData(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof DataCollector) {
            return [];
        }

        $reflectionClass = new \ReflectionClass(DataCollector::class);
        $property = $reflectionClass->getProperty('data');
        $data = $property->getValue($collector);

        if ($data instanceof Data) {
            $rawData = $data->getValue(true);

            return \is_array($rawData) ? $this->convertDataObjects($rawData) : [];
        }

        if (\is_array($data)) {
            return $this->convertDataObjects($data);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertDataObjects(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Data) {
                $data[$key] = $value->getValue(true);

                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->convertNestedDataObjects($value);
            }
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function convertNestedDataObjects(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Data) {
                $data[$key] = $value->getValue(true);

                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->convertNestedDataObjects($value);
            }
        }

        return $data;
    }
}
