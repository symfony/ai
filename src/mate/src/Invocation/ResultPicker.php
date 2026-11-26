<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Invocation;

use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Cuts a decoded result down to the paths a caller asked for.
 *
 * A result wrapped by {@see \Symfony\AI\Mate\Encoding\ResponseEncoder::encodeUntrusted()}
 * keeps its security notice, so trimmed data is still marked as data.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultPicker
{
    /**
     * @param list<string> $paths
     *
     * @return array{result: mixed, missing_paths: list<string>} the picked values keyed by path, and the paths without a value
     */
    public static function pick(mixed $result, array $paths): array
    {
        $content = PathResolver::unwrap($result);

        $picked = [];
        $missing = [];
        foreach ($paths as $path) {
            try {
                $picked[$path] = PathResolver::resolve($content, $path);
            } catch (RuntimeException) {
                $picked[$path] = null;
                $missing[] = $path;
            }
        }

        if (\is_array($result) && \array_key_exists('untrusted_data', $result)) {
            $picked = (isset($result['_security_notice']) ? ['_security_notice' => $result['_security_notice']] : []) + ['untrusted_data' => $picked];
        }

        return ['result' => $picked, 'missing_paths' => $missing];
    }
}
