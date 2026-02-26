<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, label: string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'sonic-3' => [
                'class' => Cartesia::class,
                'label' => 'Sonic 3 (TTS)',
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'ink-whisper' => [
                'class' => Cartesia::class,
                'label' => 'Ink Whisper (STT)',
                'capabilities' => [
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
