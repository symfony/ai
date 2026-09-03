<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval;

/**
 * @author Saiful Islam <saif012@gmail.com>
 */
enum ApprovalDecisionType: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Modified = 'modified';
}
