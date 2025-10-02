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

/**
 * OpenAI model identifiers.
 *
 * These constants provide IDE autocompletion and type safety when working with OpenAI models.
 * Use these with ModelCatalog::getModel() for better developer experience.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Model
{
    // GPT-3.5 Models
    public const GPT_3_5_TURBO = 'gpt-3.5-turbo';
    public const GPT_3_5_TURBO_INSTRUCT = 'gpt-3.5-turbo-instruct';

    // GPT-4 Models
    public const GPT_4 = 'gpt-4';
    public const GPT_4_TURBO = 'gpt-4-turbo';
    public const GPT_4O = 'gpt-4o';
    public const GPT_4O_MINI = 'gpt-4o-mini';
    public const GPT_4O_AUDIO_PREVIEW = 'gpt-4o-audio-preview';

    // O1 Reasoning Models
    public const O1_MINI = 'o1-mini';
    public const O1_PREVIEW = 'o1-preview';

    // O3 Reasoning Models
    public const O3_MINI = 'o3-mini';
    public const O3_MINI_HIGH = 'o3-mini-high';

    // GPT-4.5 Preview Models
    public const GPT_4_5_PREVIEW = 'gpt-4.5-preview';

    // GPT-4.1 Models
    public const GPT_4_1 = 'gpt-4.1';
    public const GPT_4_1_MINI = 'gpt-4.1-mini';
    public const GPT_4_1_NANO = 'gpt-4.1-nano';

    // GPT-5 Models
    public const GPT_5 = 'gpt-5';
    public const GPT_5_CHAT_LATEST = 'gpt-5-chat-latest';
    public const GPT_5_MINI = 'gpt-5-mini';
    public const GPT_5_NANO = 'gpt-5-nano';

    // Embedding Models
    public const TEXT_EMBEDDING_ADA_002 = 'text-embedding-ada-002';
    public const TEXT_EMBEDDING_3_LARGE = 'text-embedding-3-large';
    public const TEXT_EMBEDDING_3_SMALL = 'text-embedding-3-small';

    // Audio Models
    public const WHISPER_1 = 'whisper-1';

    // Image Generation Models
    public const DALL_E_2 = 'dall-e-2';
    public const DALL_E_3 = 'dall-e-3';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GPT_3_5_TURBO,
            self::GPT_3_5_TURBO_INSTRUCT,
            self::GPT_4,
            self::GPT_4_TURBO,
            self::GPT_4O,
            self::GPT_4O_MINI,
            self::GPT_4O_AUDIO_PREVIEW,
            self::O1_MINI,
            self::O1_PREVIEW,
            self::O3_MINI,
            self::O3_MINI_HIGH,
            self::GPT_4_5_PREVIEW,
            self::GPT_4_1,
            self::GPT_4_1_MINI,
            self::GPT_4_1_NANO,
            self::GPT_5,
            self::GPT_5_CHAT_LATEST,
            self::GPT_5_MINI,
            self::GPT_5_NANO,
            self::TEXT_EMBEDDING_ADA_002,
            self::TEXT_EMBEDDING_3_LARGE,
            self::TEXT_EMBEDDING_3_SMALL,
            self::WHISPER_1,
            self::DALL_E_2,
            self::DALL_E_3,
        ];
    }
}
