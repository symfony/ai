<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

/**
 * Same response shape as text-generation (`[0]['generated_text']`); inherits
 * the parser. Distinct contract identifier because the model selection and
 * upstream HF task tag differ.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageToTextClient extends TextGenerationClient
{
    public const ENDPOINT = 'hf.'.Task::IMAGE_TO_TEXT;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
