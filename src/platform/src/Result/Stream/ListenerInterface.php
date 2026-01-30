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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ListenerInterface
{
    public function onStart(StartEvent $event): void;

    public function onChunk(ChunkEvent $event): void;

    public function onComplete(CompleteEvent $event): void;
}
