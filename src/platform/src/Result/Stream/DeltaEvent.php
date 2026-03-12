<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DeltaEvent extends Event
{
    private bool $skipDelta = false;
    private mixed $delta;

    public function __construct(
        StreamResult $result,
        DeltaInterface $delta,
    ) {
        parent::__construct($result);
        $this->delta = $delta;
    }

    public function setDelta(mixed $delta): void
    {
        $this->delta = $delta;
    }

    public function getDelta(): mixed
    {
        return $this->delta;
    }

    public function skipDelta(): void
    {
        $this->skipDelta = true;
    }

    public function isDeltaSkipped(): bool
    {
        return $this->skipDelta;
    }
}
