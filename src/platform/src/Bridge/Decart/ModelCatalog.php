<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'lucy-dev-i2v' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                    Capability::VIDEO_TO_VIDEO,
                ],
            ],
            'lucy-pro-t2i' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                ],
            ],
            'lucy-pro-t2v' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::TEXT_TO_VIDEO,
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'lucy-pro-i2i' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::IMAGE_TO_IMAGE,
                ],
            ],
            'lucy-pro-i2v' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'lucy-pro-v2v' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::VIDEO_TO_VIDEO,
                ],
            ],
            'lucy-pro-flf2v' => [
                'class' => Decart::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
