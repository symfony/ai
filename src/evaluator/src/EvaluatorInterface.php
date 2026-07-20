<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator;

use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface EvaluatorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function evaluate(DeferredResult $deferredResult, array $options = []): float;
}
