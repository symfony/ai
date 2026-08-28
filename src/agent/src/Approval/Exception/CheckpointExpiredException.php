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
final class CheckpointExpiredException extends ToolApprovalException
{
    public static function forCheckpoint(string $checkpointId): self
    {
        return new self(\sprintf('Execution checkpoint "%s" has expired.', $checkpointId));
    }
}
