<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * The model name maps directly to a Higgsfield generation endpoint (the path that is POSTed to).
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'flux-pro/kontext/max/text-to-image' => [
                'class' => Higgsfield::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                ],
            ],
            'bytedance/seedream/v4/text-to-image' => [
                'class' => Higgsfield::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                ],
            ],
            'v1/text2image/soul' => [
                'class' => Higgsfield::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                ],
            ],
            'v1/image2video/dop' => [
                'class' => Higgsfield::class,
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
