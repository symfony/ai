<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

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
            'venice-uncensored' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                ],
            ],
            'tts-kokoro' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'nvidia/parakeet-tdt-0.6b-v3' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'openai/whisper-large-v3' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'text-embedding-bge-m3' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
