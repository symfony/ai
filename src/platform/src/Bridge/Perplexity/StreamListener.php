<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\ChunkEvent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamListener extends AbstractStreamListener
{
    public function onChunk(ChunkEvent $event): void
    {
        $chunk = $event->getChunk();

        if (!\is_array($chunk)) {
            return;
        }

        if (isset($chunk['search_results'])) {
            $event->getMetadata()->add('search_results', $chunk['search_results']);
            $event->skipChunk();
        }

        if (isset($chunk['citations'])) {
            $event->getMetadata()->add('citations', $chunk['citations']);
            $event->skipChunk();
        }
    }
}
