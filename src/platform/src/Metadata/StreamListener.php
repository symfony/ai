<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Metadata;

use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class StreamListener extends AbstractStreamListener
{
    public function onDelta(DeltaEvent $event): void
    {
        $delta = $event->getDelta();

        if (!$delta instanceof MetadataDelta) {
            return;
        }

        $event->getMetadata()->add($delta->getKey(), $delta->getValue());
        $event->skipDelta();
    }
}
