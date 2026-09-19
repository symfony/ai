<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'jev-latest' => [
                'class' => Jev::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'jev-preview' => [
                'class' => Jev::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'jev-1.13.0' => [
                'class' => Jev::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
