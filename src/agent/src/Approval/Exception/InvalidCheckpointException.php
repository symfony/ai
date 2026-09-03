<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Exception;

/**
 * @author Saiful Islam <saif012@gmail.com>
 */
final class InvalidCheckpointException extends ToolApprovalException
{
    public static function signatureMismatch(): self
    {
        return new self('The execution checkpoint signature is invalid or has been tampered with.');
    }

    public static function unreadable(string $reason): self
    {
        return new self(\sprintf('The execution checkpoint is unreadable: "%s".', $reason));
    }
}
