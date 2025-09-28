<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Capability;

/**
 * OpenAI Model Definitions
 * 
 * @return array<string, array{class: string, capabilities: list<Capability>}>
 */
return [
    'gpt-3.5-turbo' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ],
    ],
    'gpt-3.5-turbo-instruct' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ],
    ],
    'gpt-4' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ],
    ],
    'gpt-4-turbo' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
        ],
    ],
    'gpt-4o' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-4o-mini' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-4o-audio-preview' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_AUDIO,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'o1-mini' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
        ],
    ],
    'o1-preview' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
        ],
    ],
    'o3-mini' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'o3-mini-high' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ],
    ],
    'gpt-4.5-preview' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-4.1' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-4.1-mini' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-4.1-nano' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-5' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-5-chat-latest' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::INPUT_IMAGE,
        ],
    ],
    'gpt-5-mini' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'gpt-5-nano' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_STRUCTURED,
        ],
    ],
    'text-embedding-ada-002' => [
        'class' => Embeddings::class,
        'capabilities' => [Capability::INPUT_TEXT],
    ],
    'text-embedding-3-large' => [
        'class' => Embeddings::class,
        'capabilities' => [Capability::INPUT_TEXT],
    ],
    'text-embedding-3-small' => [
        'class' => Embeddings::class,
        'capabilities' => [Capability::INPUT_TEXT],
    ],
    'whisper-1' => [
        'class' => Whisper::class,
        'capabilities' => [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ],
    ],
    'dall-e-2' => [
        'class' => DallE::class,
        'capabilities' => [
            Capability::INPUT_TEXT,
            Capability::OUTPUT_IMAGE,
        ],
    ],
    'dall-e-3' => [
        'class' => DallE::class,
        'capabilities' => [
            Capability::INPUT_TEXT,
            Capability::OUTPUT_IMAGE,
        ],
    ],
];
