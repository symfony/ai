<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tool;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class ToolEffectHints
{
    public function __construct(
        public readonly ?bool $readOnlyHint = null,
        public readonly ?bool $destructiveHint = null,
        public readonly ?bool $idempotentHint = null,
        public readonly ?bool $openWorldHint = null,
    ) {
    }

    /**
     * @return array{readOnlyHint?: bool, destructiveHint?: bool, idempotentHint?: bool, openWorldHint?: bool}
     */
    public function toArray(): array
    {
        return array_filter([
            'readOnlyHint' => $this->readOnlyHint,
            'destructiveHint' => $this->destructiveHint,
            'idempotentHint' => $this->idempotentHint,
            'openWorldHint' => $this->openWorldHint,
        ], static fn (?bool $hint): bool => null !== $hint);
    }
}
