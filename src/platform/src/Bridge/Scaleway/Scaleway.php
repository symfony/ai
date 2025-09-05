<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class Scaleway extends Model
{
    public const DEEPSEEK = 'deepseek-r1-distill-llama-70b';
    public const GOOGLE_GEMMA = 'gemma-3-27b-it';
    public const META_LLAMA_8B = 'llama-3.1-8b-instruct';
    public const META_LLAMA_70B = 'llama-3.3-70b-instruct';
    public const MISTRAL_DEVSTRAL = 'devstral-small-2505';
    public const MISTRAL_NEMO = 'mistral-nemo-instruct-2407';
    public const MISTRAL_PIXTRAL = 'pixtral-12b-2409';
    public const MISTRAL_SMALL = 'mistral-small-3.2-24b-instruct-2506';
    public const OPENAI_OSS = 'gpt-oss-120b';
    public const QWEN_CODE = 'qwen3-coder-30b-a3b-instruct';
    public const QWEN_INSTRUCT = 'qwen3-235b-a22b-instruct-2507';

    private const IMAGE_SUPPORTING = [
        self::GOOGLE_GEMMA,
        self::MISTRAL_SMALL,
        self::MISTRAL_PIXTRAL,
    ];

    private const FUNCTION_SUPPORTING = [
        self::DEEPSEEK,
        self::MISTRAL_DEVSTRAL,
        self::MISTRAL_NEMO,
        self::META_LLAMA_70B,
        self::META_LLAMA_8B,
        self::QWEN_CODE,
        self::QWEN_INSTRUCT,
        self::OPENAI_OSS,
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $name = self::OPENAI_OSS,
        array $options = [],
    ) {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        if (\in_array($name, self::IMAGE_SUPPORTING, true)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        if (\in_array($name, self::FUNCTION_SUPPORTING, true)) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        parent::__construct($name, $capabilities, $options);
    }
}
