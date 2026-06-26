<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class McpApprovalRequest implements ContentInterface
{
    public function __construct(
        private readonly string $serverLabel,
        private readonly string $name,
        private readonly ?string $arguments = null,
        private readonly ?string $id = null,
    ) {
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
