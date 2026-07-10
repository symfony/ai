<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\FinishReason;

use OskarStark\Enum\Trait\Comparable;

/**
 * Normalized reason why a model stopped generating.
 *
 * This is the vocabulary consumers can rely on across bridges. Each bridge maps its provider's own
 * wording onto these cases; the untouched provider value stays available via {@see FinishReason::getRaw()}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum FinishReasonCase: string
{
    use Comparable;

    /**
     * The model finished generating on its own.
     */
    case STOP = 'stop';

    /**
     * Generation was truncated by an output token limit.
     *
     * The content is incomplete and consumers may want to continue generation.
     */
    case LENGTH = 'length';

    /**
     * The model stopped in order to call one or more tools.
     */
    case TOOL_CALL = 'tool-call';

    /**
     * Generation was stopped by a safety filter or guardrail.
     */
    case CONTENT_FILTER = 'content-filter';

    /**
     * The model hit one of the caller-supplied stop sequences.
     */
    case STOP_SEQUENCE = 'stop-sequence';

    /**
     * The provider reported a reason without a normalized equivalent.
     *
     * Inspect {@see FinishReason::getRaw()} to tell those cases apart.
     */
    case OTHER = 'other';
}
