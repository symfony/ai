<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ExecutableCodeResult extends BaseResult
{
    public function __construct(
        private readonly string $code,
        private readonly ?string $language = null,
        private readonly ?string $id = null,
    ) {
    }

    public function getContent(): string
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
