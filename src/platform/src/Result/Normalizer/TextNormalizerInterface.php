<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Normalizer;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Normalizes the textual content of model results after invocation.
 *
 * Implementations clean up provider-agnostic quirks in model output
 * (e.g. Markdown code fences around JSON, Unicode format characters).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface TextNormalizerInterface
{
    /**
     * @param array<string, mixed> $options The options passed to Platform::invoke()
     */
    public function supports(Model $model, ResultInterface $result, array $options): bool;

    public function normalize(string $text): string;
}
