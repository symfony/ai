<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Event;

use Symfony\AI\Platform\Model;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before platform invocation to allow modification of input data.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class PlatformInvokationEvent extends Event
{
    /**
     * @param array<string, mixed>|string|object $input
     * @param array<string, mixed>               $options
     */
    public function __construct(
        public readonly Model $model,
        public array|string|object $input,
        public readonly array $options = [],
    ) {
    }
}
