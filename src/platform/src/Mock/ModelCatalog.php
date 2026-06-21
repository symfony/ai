<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Optional catalog for the fake provider. Register models explicitly to gate which model names
 * route to the fake provider; otherwise pass a FallbackModelCatalog so any name resolves.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $models
     */
    public function __construct(
        protected array $models = [],
    ) {
    }
}
