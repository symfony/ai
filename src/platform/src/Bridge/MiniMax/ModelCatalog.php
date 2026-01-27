<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Component\Validator\Tests\Constraints\MinMax;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string<Model>, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'M2-her' => [
                'class' => MinMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                ],
            ],
            'image-01' => [
                'class' => MinMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                    Capability::IMAGE_TO_IMAGE,
                ],
            ],
            'image-01-live' => [
                'class' => MinMax::class,
                'capabilities' => [
                    Capability::IMAGE_TO_IMAGE,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
