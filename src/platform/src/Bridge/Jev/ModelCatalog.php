<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $capabilities = [Capability::INPUT_TEXT];

        $defaultModels = [
            'jev-latest' => [
                'class' => Jev::class,
                'capabilities' => $capabilities,
            ],
            'jev-preview' => [
                'class' => Jev::class,
                'capabilities' => $capabilities,
            ],
            'jev-1.13.0' => [
                'class' => Jev::class,
                'capabilities' => $capabilities,
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
