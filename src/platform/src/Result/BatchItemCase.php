<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * How one request of a batch ended, normalized across providers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum BatchItemCase: string
{
    /**
     * The provider ran the request and answered it.
     */
    case SUCCEEDED = 'succeeded';

    /**
     * The provider ran the request and it failed, e.g. because it was malformed.
     */
    case ERRORED = 'errored';

    /**
     * The batch was canceled before the request was sent to the model.
     */
    case CANCELED = 'canceled';

    /**
     * The batch expired before the request was sent to the model.
     */
    case EXPIRED = 'expired';

    /**
     * The provider reported an outcome this enum does not know about.
     */
    case UNKNOWN = 'unknown';
}
