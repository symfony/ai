<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Implementations handle the conversion of result data into structured objects.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultConverterInterface
{
    public function supports(Model $model): bool;

    /**
     * Converts the main result data into a ResultInterface instance.
     *
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface
     */
    public function convert(RawResultInterface $result, array $options = []): ResultInterface;

    /**
     * Returns a TokenUsageExtractorInterface instance if available, null otherwise.
     */
    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface;
}
