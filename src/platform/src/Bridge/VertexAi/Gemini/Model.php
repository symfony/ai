<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Gemini;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model as BaseModel;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class Model extends BaseModel
{
    public const GEMINI_2_5_PRO = 'gemini-2.5-pro';
    public const GEMINI_2_5_FLASH = 'gemini-2.5-flash';
    public const GEMINI_2_0_FLASH = 'gemini-2.0-flash';
    public const GEMINI_2_5_FLASH_LITE = 'gemini-2.5-flash-lite';
    public const GEMINI_2_0_FLASH_LITE = 'gemini-2.0-flash-lite';

    /**
     * @param array<string, mixed> $options The default options for the model usage
     *
     * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/inference for more details
     */
    public function __construct(string $name = self::GEMINI_2_5_PRO, array $options = [])
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

        parent::__construct($name, $capabilities, $options);
    }
}
