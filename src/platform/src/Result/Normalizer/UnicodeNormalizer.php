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
 * Strips Unicode "Cf" (format control) characters — zero-width spaces, BOM,
 * etc. — and converts non-breaking space (U+00A0) to a regular space.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class UnicodeNormalizer implements TextNormalizerInterface
{
    public function supports(Model $model, ResultInterface $result, array $options): bool
    {
        return true;
    }

    public function normalize(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        $text = preg_replace('/[\p{Cf}]/u', '', $text);

        if (null === $text) {
            return '';
        }

        return str_replace("\u{00A0}", ' ', $text);
    }
}
