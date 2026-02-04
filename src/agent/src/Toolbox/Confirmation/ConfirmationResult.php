<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConfirmationResult
{
    private function __construct(
        private readonly bool $confirmed,
        private readonly bool $remember,
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function shouldRemember(): bool
    {
        return $this->remember;
    }

    public static function confirmed(): self
    {
        return new self(true, false);
    }

    public static function denied(): self
    {
        return new self(false, false);
    }

    public static function always(): self
    {
        return new self(true, true);
    }

    public static function never(): self
    {
        return new self(false, true);
    }
}
