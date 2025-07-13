<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Groq;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Dave Hulbert <dave1010@gmail.com>
 */
class Llama extends Model
{
    public const LLAMA3_8B = 'llama3-8b-8192';
    public const LLAMA3_70B = 'llama3-70b-8192';
    public const LLAMA2_70B = 'llama2-70b-4096';
    public const MIXTRAL_8X7B = 'mistral-saba-24b';
    public const GEMMA_7B = 'gemma2-9b-it';
    public const QWEN_32B = 'qwen/qwen3-32b';

    /**
     * @param array<mixed> $options The default options for the model usage
     */
    public function __construct(
        string $name = self::LLAMA3_70B,
        array $options = ['temperature' => 1.0],
    ) {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ];

        parent::__construct($name, $capabilities, $options);
    }
}
