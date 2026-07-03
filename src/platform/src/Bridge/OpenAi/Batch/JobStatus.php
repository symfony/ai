<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Batch;

/**
 * Possible values of the `status` field returned by the OpenAI Batch API.
 *
 * @see https://platform.openai.com/docs/api-reference/batch/object
 */
enum JobStatus: string
{
    case VALIDATING = 'validating';
    case IN_PROGRESS = 'in_progress';
    case FINALIZING = 'finalizing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLING = 'cancelling';
    case CANCELLED = 'cancelled';
}
