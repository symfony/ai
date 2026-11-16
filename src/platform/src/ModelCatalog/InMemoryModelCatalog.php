<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

use Symfony\AI\Platform\Capability;

/**
 * A catalog of a fixed list of models, e.g. models defined in the application configuration.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $models
     */
    public function __construct(array $models = [])
    {
        $this->models = $models;
    }
}
