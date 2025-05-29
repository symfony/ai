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

namespace Symfony\AI\McpSdk\Capability\Prompt;

interface MetadataInterface extends IdentifierInterface
{
    public function getDescription(): ?string;

    /**
     * @return list<array{
     *   name: string,
     *   description?: string,
     *   required?: bool,
     * }>
     */
    public function getArguments(): array;
}
