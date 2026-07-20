<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator\Listener;

use Symfony\AI\Evaluator\EvaluatorInterface;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class EvaluatorListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EvaluatorInterface $evaluator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResultEvent::class => 'onResult',
        ];
    }

    public function onResult(ResultEvent $event): void
    {
        $deferredResult = $event->getDeferredResult();

        $score = $this->evaluator->evaluate($deferredResult, $event->getOptions());

        $deferredResult->getMetadata()->add('score', $score);
    }
}
