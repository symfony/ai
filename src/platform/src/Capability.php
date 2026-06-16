<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use OskarStark\Enum\Trait\Comparable;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Video;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Capability: string
{
    use Comparable;

    // INPUT
    case INPUT_AUDIO = 'input-audio';
    case INPUT_IMAGE = 'input-image';
    case INPUT_MESSAGES = 'input-messages';
    case INPUT_MULTIPLE = 'input-multiple';
    case INPUT_PDF = 'input-pdf';
    case INPUT_TEXT = 'input-text';
    case INPUT_VIDEO = 'input-video';
    case INPUT_MULTIMODAL = 'input-multimodal';

    // OUTPUT
    case OUTPUT_AUDIO = 'output-audio';
    case OUTPUT_IMAGE = 'output-image';
    case OUTPUT_STREAMING = 'output-streaming';
    case OUTPUT_STRUCTURED = 'output-structured';
    case OUTPUT_TEXT = 'output-text';

    // FUNCTIONALITY
    case TOOL_CALLING = 'tool-calling';

    // VOICE
    case TEXT_TO_SPEECH = 'text-to-speech';
    case SPEECH_TO_TEXT = 'speech-to-text';

    // IMAGE
    case TEXT_TO_IMAGE = 'text-to-image';
    case IMAGE_TO_IMAGE = 'image-to-image';

    // VIDEO
    case TEXT_TO_VIDEO = 'text-to-video';
    case IMAGE_TO_VIDEO = 'image-to-video';
    case VIDEO_TO_VIDEO = 'video-to-video';

    // EMBEDDINGS
    case EMBEDDINGS = 'embeddings';

    // RERANKING
    case RERANKING = 'reranking';

    // Thinking
    case THINKING = 'thinking';

    // Fill-in-the-middle (insert)
    case FILL_IN_THE_MIDDLE = 'fill-in-the-middle';

    /**
     * A short, human-readable description of what this capability means.
     */
    public function description(): string
    {
        return match ($this) {
            self::INPUT_AUDIO => 'Accepts audio input',
            self::INPUT_IMAGE => 'Accepts image input',
            self::INPUT_MESSAGES => 'Accepts a conversation (MessageBag) as input',
            self::INPUT_MULTIPLE => 'Accepts multiple inputs in a single call',
            self::INPUT_PDF => 'Accepts PDF documents as input',
            self::INPUT_TEXT => 'Accepts plain text input',
            self::INPUT_VIDEO => 'Accepts video input',
            self::INPUT_MULTIMODAL => 'Accepts mixed multimodal input',
            self::OUTPUT_AUDIO => 'Produces audio output',
            self::OUTPUT_IMAGE => 'Produces image output',
            self::OUTPUT_STREAMING => 'Supports streaming output',
            self::OUTPUT_STRUCTURED => 'Produces structured, schema-constrained output',
            self::OUTPUT_TEXT => 'Produces text output',
            self::TOOL_CALLING => 'Can call tools and functions',
            self::TEXT_TO_SPEECH => 'Synthesizes speech from text',
            self::SPEECH_TO_TEXT => 'Transcribes speech to text',
            self::TEXT_TO_IMAGE => 'Generates images from text',
            self::IMAGE_TO_IMAGE => 'Transforms an image into another image',
            self::TEXT_TO_VIDEO => 'Generates video from text',
            self::IMAGE_TO_VIDEO => 'Generates video from an image',
            self::VIDEO_TO_VIDEO => 'Transforms a video into another video',
            self::EMBEDDINGS => 'Produces vector embeddings',
            self::RERANKING => 'Reranks documents by relevance to a query',
            self::THINKING => 'Supports extended reasoning ("thinking")',
            self::FILL_IN_THE_MIDDLE => 'Supports fill-in-the-middle (insertion) completion',
        };
    }

    /**
     * The content class a single-modality model accepts directly for this input
     * capability, or null for capabilities that are not a single content type
     * (chat messages, embeddings, every output capability, …).
     *
     * @return class-string<ContentInterface>|null
     */
    public function inputContentType(): ?string
    {
        return match ($this) {
            self::INPUT_PDF => DocumentUrl::class,
            self::INPUT_IMAGE => ImageUrl::class,
            self::INPUT_AUDIO => Audio::class,
            self::INPUT_VIDEO => Video::class,
            self::INPUT_TEXT => Text::class,
            default => null,
        };
    }
}
