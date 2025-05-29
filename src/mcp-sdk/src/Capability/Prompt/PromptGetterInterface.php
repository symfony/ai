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

namespace Symfony\AI\McpSdk\Capability\Prompt;

use Symfony\AI\McpSdk\Exception\PromptGetException;
use Symfony\AI\McpSdk\Exception\PromptNotFoundException;

interface PromptGetterInterface
{
    /**
     * @throws PromptGetException      if the prompt execution fails
     * @throws PromptNotFoundException if the prompt is not found
     */
    public function get(PromptGet $input): PromptGetResult;
}
