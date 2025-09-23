<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class Gpt extends Model
{
    public const GPT_35_TURBO = 'gpt-3.5-turbo';
    public const GPT_35_TURBO_INSTRUCT = 'gpt-3.5-turbo-instruct';
    public const GPT_4 = 'gpt-4';
    public const GPT_4_TURBO = 'gpt-4-turbo';
    public const GPT_4O = 'gpt-4o';
    public const GPT_4O_MINI = 'gpt-4o-mini';
    public const GPT_4O_AUDIO = 'gpt-4o-audio-preview';
    public const O1_MINI = 'o1-mini';
    public const O1_PREVIEW = 'o1-preview';
    public const O3_MINI = 'o3-mini';
    public const O3_MINI_HIGH = 'o3-mini-high';
    public const GPT_45_PREVIEW = 'gpt-4.5-preview';
    public const GPT_41 = 'gpt-4.1';
    public const GPT_41_MINI = 'gpt-4.1-mini';
    public const GPT_41_NANO = 'gpt-4.1-nano';
    public const GPT_5 = 'gpt-5';
    public const GPT_5_CHAT = 'gpt-5-chat-latest';
    public const GPT_5_MINI = 'gpt-5-mini';
    public const GPT_5_NANO = 'gpt-5-nano';

    private const IMAGE_SUPPORTING = [
        self::GPT_4_TURBO,
        self::GPT_4O,
        self::GPT_4O_MINI,
        self::O1_MINI,
        self::O1_PREVIEW,
        self::O3_MINI,
        self::GPT_45_PREVIEW,
        self::GPT_41,
        self::GPT_41_MINI,
        self::GPT_41_NANO,
        self::GPT_5,
        self::GPT_5_MINI,
        self::GPT_5_NANO,
        self::GPT_5_CHAT,
    ];

    private const STRUCTURED_OUTPUT_SUPPORTING = [
        self::GPT_4O,
        self::GPT_4O_MINI,
        self::O3_MINI,
        self::GPT_45_PREVIEW,
        self::GPT_41,
        self::GPT_41_MINI,
        self::GPT_41_NANO,
        self::GPT_5,
        self::GPT_5_MINI,
        self::GPT_5_NANO,
    ];

    /**
     * @param array<mixed> $options The default options for the model usage
     */
    public function __construct(
        string $name = self::GPT_4O,
        array $options = [],
    ) {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        if (self::GPT_5_CHAT !== $name) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        if (self::GPT_4O_AUDIO === $name) {
            $capabilities[] = Capability::INPUT_AUDIO;
        }

        if (\in_array($name, self::IMAGE_SUPPORTING, true)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        if (\in_array($name, self::STRUCTURED_OUTPUT_SUPPORTING, true)) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        parent::__construct($name, $capabilities, $options);
    }
}
