<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Taxonomy;

/**
 * What a developer can do through a bridge, condensed from the capabilities its
 * model catalog and model clients support.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Capability: string implements Option
{
    case Chat = 'chat';
    case ToolCalling = 'tool-calling';
    case StructuredOutput = 'structured-output';
    case Reasoning = 'reasoning';
    case WebSearch = 'web-search';
    case Vision = 'vision';
    case Embeddings = 'embeddings';
    case SpeechToText = 'speech-to-text';
    case TextToSpeech = 'text-to-speech';
    case ImageGeneration = 'image-generation';
    case VideoGeneration = 'video-generation';

    public function label(): string
    {
        return match ($this) {
            self::Chat => 'Chat',
            self::ToolCalling => 'Tool calling',
            self::StructuredOutput => 'Structured output',
            self::Reasoning => 'Reasoning',
            self::WebSearch => 'Web search',
            self::Vision => 'Vision',
            self::Embeddings => 'Embeddings',
            self::SpeechToText => 'Speech-to-text',
            self::TextToSpeech => 'Text-to-speech',
            self::ImageGeneration => 'Image generation',
            self::VideoGeneration => 'Video generation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Chat => 'Generate text from a conversation.',
            self::ToolCalling => 'Let the model call your PHP tools, the foundation of agents.',
            self::StructuredOutput => 'Get answers matching a JSON schema or a PHP class.',
            self::Reasoning => 'Use models that think before answering.',
            self::WebSearch => 'Ground answers with a search performed by the provider.',
            self::Vision => 'Send images along with the prompt.',
            self::Embeddings => 'Turn text into vectors for semantic search and RAG.',
            self::SpeechToText => 'Transcribe audio into text.',
            self::TextToSpeech => 'Synthesize speech from text.',
            self::ImageGeneration => 'Create or edit images from a prompt.',
            self::VideoGeneration => 'Create videos from a prompt or an image.',
        };
    }

    /**
     * Name of the UX icon, see assets/icons/tabler.
     */
    public function icon(): string
    {
        return 'tabler:'.match ($this) {
            self::Chat => 'message-chatbot',
            self::ToolCalling => 'tool',
            self::StructuredOutput => 'braces',
            self::Reasoning => 'brain',
            self::WebSearch => 'world-search',
            self::Vision => 'photo',
            self::Embeddings => 'vector-triangle',
            self::SpeechToText => 'microphone',
            self::TextToSpeech => 'volume',
            self::ImageGeneration => 'photo-ai',
            self::VideoGeneration => 'video',
        };
    }
}
