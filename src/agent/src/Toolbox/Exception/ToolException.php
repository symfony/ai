<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Exception;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolException extends InvalidArgumentException implements ExceptionInterface
{
    public static function invalidReference(mixed $reference): self
    {
        return new self(\sprintf('The reference "%s" is not a valid tool.', $reference));
    }

    public static function missingAttribute(string $className): self
    {
        return new self(\sprintf('The class "%s" is not a tool, please add %s attribute.', $className, AsTool::class));
    }
}
