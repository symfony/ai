<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;
use Symfony\AI\Platform\Result\Metadata\Metadata;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface ResultInterface
{
    /**
     * @return string|iterable<mixed>|object|null
     */
    public function getContent(): string|iterable|object|null;

    public function getMetadata(): Metadata;

    public function getRawResult(): ?RawResultInterface;

    /**
     * @throws RawResultAlreadySetException if the result is tried to be set more than once
     */
    public function setRawResult(RawResultInterface $rawResult): void;
}
