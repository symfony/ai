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

final class ExecutableCode implements ContentInterface
{
    public function __construct(
        private readonly string $code,
        private readonly ?string $language = null,
        private readonly ?string $id = null,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
