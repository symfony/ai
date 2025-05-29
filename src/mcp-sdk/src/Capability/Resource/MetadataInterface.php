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

namespace PhpLlm\McpSdk\Capability\Resource;

interface MetadataInterface extends IdentifierInterface
{
    public function getName(): string;

    public function getDescription(): ?string;

    public function getMimeType(): ?string;

    /**
     * Size in bytes.
     */
    public function getSize(): ?int;
}
