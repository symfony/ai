<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Resolves a scripted response into a {@see ResultInterface}.
 *
 * The script can be:
 *  - a string: every call resolves to a {@see TextResult} wrapping it;
 *  - a map keyed by model name: per-model {@see ResultInterface} or string;
 *  - a \Closure(Model $model, array|string|object $input, array $options): ResultInterface|string.
 *
 * Bare strings are wrapped in a {@see TextResult}, or streamed word by word as {@see TextDelta}s when the
 * invocation has the "stream" option; a {@see ResultInterface} is returned verbatim, so every result type
 * is supported.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ScriptedResponse
{
    /**
     * @param \Closure|string|array<string, ResultInterface|string> $script
     */
    public function __construct(
        private readonly \Closure|string|array $script,
    ) {
    }

    /**
     * @param array<string|int, mixed>|string|object $input
     * @param array<string, mixed>                   $options
     */
    public function resolve(Model $model, array|string|object $input, array $options): ResultInterface
    {
        $resolved = $this->resolveRaw($model, $input, $options);

        if (!\is_string($resolved)) {
            return $resolved;
        }

        if ($options['stream'] ?? false) {
            return new StreamResult((static function () use ($resolved): \Generator {
                // each word with the spaces after it, like the tokens of a provider
                foreach (preg_split('/(?<=\s)(?=\S)/u', $resolved) as $word) {
                    if ('' !== $word) {
                        yield new TextDelta($word);
                    }
                }
            })());
        }

        return new TextResult($resolved);
    }

    /**
     * @param array<string|int, mixed>|string|object $input
     * @param array<string, mixed>                   $options
     */
    private function resolveRaw(Model $model, array|string|object $input, array $options): ResultInterface|string
    {
        if ($this->script instanceof \Closure) {
            return ($this->script)($model, $input, $options);
        }

        if (\is_string($this->script)) {
            return $this->script;
        }

        $name = $model->getName();

        if (!\array_key_exists($name, $this->script)) {
            throw new InvalidArgumentException(\sprintf('No scripted response configured for model "%s".', $name));
        }

        return $this->script[$name];
    }
}
