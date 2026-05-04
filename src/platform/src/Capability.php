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

/**
 * Flat capability flag describing what a model accepts/emits and what
 * features it supports. Modality-style cases (INPUT_*, OUTPUT_*) coexist
 * with feature flags (TOOL_CALLING, OUTPUT_STREAMING, …) and task hints
 * (EMBEDDINGS, TEXT_TO_IMAGE, …).
 *
 * Tasks are now driven by {@see Endpoint} declarations on the {@see Model};
 * the corresponding cases here are kept because catalogs still surface
 * them through `Model::getCapabilities()` for normalizers and event
 * listeners that filter on capability.
 *
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
}
