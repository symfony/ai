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

namespace PhpLlm\McpSdk\Capability\Tool;

interface MetadataInterface extends IdentifierInterface
{
    public function getDescription(): string;

    /**
     * @return array{
     *   type?: string,
     *   required?: list<string>,
     *   properties?: array<string, array{
     *       type: string,
     *       description?: string,
     *   }>,
     * }
     */
    public function getInputSchema(): array;
}
