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

/**
 * Base class for raw model result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface RawResultInterface
{
    /**
     * Returns an array representation of the raw result data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function getDataStream(): iterable;

    public function getObject(): object;
}
