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
use Symfony\AI\Platform\Result\Stream\DeltaEvent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamListener extends AbstractStreamListener
{
    public function onDelta(DeltaEvent $event): void
    {
        $delta = $event->getDelta();

        if ($delta instanceof PerplexitySearchResults) {
            $event->getMetadata()->add('search_results', $delta->getSearchResults());
            $event->skipDelta();
        }

        if ($delta instanceof PerplexityCitations) {
            $event->getMetadata()->add('citations', $delta->getCitations());
            $event->skipDelta();
        }
    }
}
