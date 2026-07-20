<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * Implemented by state stores that can enumerate the workflow states they hold.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface ListableWorkflowStateStoreInterface
{
    /**
     * Lists the identifiers of every persisted workflow state.
     *
     * @return iterable<string>
     */
    public function list(): iterable;
}
