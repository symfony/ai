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
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Strips Markdown code fences (```json … ```) that some models wrap around
 * JSON output despite being asked for structured output.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JsonFenceStripNormalizer implements TextNormalizerInterface
{
    public function supports(Model $model, ResultInterface $result, array $options): bool
    {
        if ($result instanceof ObjectResult) {
            return true;
        }

        $responseFormat = $options['response_format'] ?? null;

        if ('json' === $responseFormat) {
            return true;
        }

        if (\is_array($responseFormat) && 'json_schema' === ($responseFormat['type'] ?? null)) {
            return true;
        }

        return false;
    }

    public function normalize(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        if (1 !== preg_match('/^\s*```(?:json)?\s*(.*?)\s*```\s*$/is', $text, $matches)) {
            return $text;
        }

        $candidate = $matches[1];

        if (!json_validate($candidate)) {
            return $text;
        }

        return $candidate;
    }
}
