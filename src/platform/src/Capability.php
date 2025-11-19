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
}
