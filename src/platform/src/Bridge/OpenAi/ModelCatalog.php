<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\AbstractModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $models = [
            'gpt-4o' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::INPUT_IMAGE->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::OUTPUT_STRUCTURED->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'gpt-4o-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::INPUT_IMAGE->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::OUTPUT_STRUCTURED->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'gpt-4o-audio-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::INPUT_AUDIO->value,
                    Capability::INPUT_IMAGE->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::OUTPUT_STRUCTURED->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'gpt-4-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::INPUT_IMAGE->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'gpt-4' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'gpt-3.5-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES->value,
                    Capability::OUTPUT_TEXT->value,
                    Capability::OUTPUT_STREAMING->value,
                    Capability::TOOL_CALLING->value,
                ],
            ],
            'text-embedding-3-large' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_TEXT->value,
                ],
            ],
            'text-embedding-3-small' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_TEXT->value,
                ],
            ],
            'text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_TEXT->value,
                ],
            ],
        ];

        parent::__construct($models);
    }

    public function supports(PlatformInterface $platform): bool
    {
        return $platform instanceof Platform
            && str_contains($platform::class, 'OpenAi')
            && !str_contains($platform::class, 'Azure');
    }
}
