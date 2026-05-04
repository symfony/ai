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
 * Same response shape as audio-classification; distinct contract identifier
 * because the upstream task tag and accepted modalities differ.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageClassificationClient extends AudioClassificationClient
{
    public const ENDPOINT = 'hf.'.Task::IMAGE_CLASSIFICATION;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
