<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Batch;

/**
 * Possible values of the `processing_status` field returned by the Anthropic Message Batches API.
 *
 * Individual request outcomes (succeeded, errored, canceled, expired) are not reflected
 * here — they are available in the results stream once the batch has ended.
 *
 * @see https://docs.anthropic.com/en/docs/build-with-claude/message-batches
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
enum ProcessingStatus: string
{
    case IN_PROGRESS = 'in_progress';

    /** Cancellation has been requested but is not yet complete. */
    case CANCELING = 'canceling';

    /** All requests have finished processing (including cancelled batches). */
    case ENDED = 'ended';
}
