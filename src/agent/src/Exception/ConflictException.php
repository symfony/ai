<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConflictException extends RuntimeException
{
    public static function forMessages(MessageBag $messages): self
    {
        return new self(\sprintf('The messages "%s" were changed in the meantime, load them again before saving.', $messages->getId()->toRfc4122()));
    }
}
