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
use Symfony\AI\Platform\Endpoint;
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

    protected function endpointsForModel(array $modelConfig): array
    {
        $capabilities = $modelConfig['capabilities'];

        // Pure text-in tasks → /generate (text body); image/video editing →
        // /generate with file upload. A model can declare both.
        $endpoints = [];
        if (\in_array(Capability::TEXT_TO_IMAGE, $capabilities, true)
            || \in_array(Capability::TEXT_TO_VIDEO, $capabilities, true)
        ) {
            $endpoints[] = new Endpoint(GenerateClient::ENDPOINT);
        }
        if (\in_array(Capability::IMAGE_TO_IMAGE, $capabilities, true)
            || \in_array(Capability::IMAGE_TO_VIDEO, $capabilities, true)
            || \in_array(Capability::VIDEO_TO_VIDEO, $capabilities, true)
        ) {
            $endpoints[] = new Endpoint(EditClient::ENDPOINT);
        }

        return $endpoints;
    }
}
