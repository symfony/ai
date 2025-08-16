<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Roy Garrido
 */
final class Gemini
{
    public const GEMINI_2_FLASH = 'gemini-2.0-flash';
    public const GEMINI_2_PRO = 'gemini-2.0-pro-exp-02-05';
    public const GEMINI_2_FLASH_LITE = 'gemini-2.0-flash-lite-preview-02-05';
    public const GEMINI_2_FLASH_THINKING = 'gemini-2.0-flash-thinking-exp-01-21';
    public const GEMINI_1_5_FLASH = 'gemini-1.5-flash';

    /**
     * @param array<string, mixed> $options The default options for the model usage
     */
    public static function create(string $name = self::GEMINI_2_PRO, array $options = ['temperature' => 1.0]): Model
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
        ];

        return new Model($name, $capabilities, $options);
    }
}
