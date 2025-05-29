<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Capability\Prompt;

final readonly class PromptGet
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {
    }
}
