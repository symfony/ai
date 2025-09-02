<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator\Scorer;

use Symfony\AI\Evaluator\AbstractScorer;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\String\UnicodeString;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class EndWith extends AbstractScorer
{
    public function __construct(
        private readonly string $suffix,
    ) {
    }

    public function score(DeferredResult $deferredResult, array $options = []): float
    {
        $text = $deferredResult->asText();

        $string = new UnicodeString($text);

        if (0 === $string->length()) {
            return 0.0;
        }

        return $string->endsWith($this->suffix) ? 1.0 : 0.0;
    }
}
