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
 * Implemented by state stores that can provision and tear down their backend.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface ManagedWorkflowStateStoreInterface
{
    /**
     * Prepares the backend so workflow state can be persisted.
     */
    public function setup(): void;

    /**
     * Removes every workflow state managed by this store.
     */
    public function drop(): void;
}
