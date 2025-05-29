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

namespace PhpLlm\McpSdk\Exception;

use PhpLlm\McpSdk\Capability\Prompt\PromptGet;

final class PromptGetException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        public readonly PromptGet $promptGet,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Handling prompt "%s" failed with error: %s', $promptGet->name, $previous->getMessage()), previous: $previous);
    }
}
