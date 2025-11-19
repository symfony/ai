<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract;

use Symfony\AI\Platform\Bridge\OpenAi\Whisper\AudioNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillermo Lengemann <guillermo.lengemann@gmail.com>
 */
final class OpenAiContract extends Contract
{
    public static function create(NormalizerInterface ...$normalizer): Contract
    {
        return parent::create(
            new AudioNormalizer(),
            new DocumentNormalizer(),
            ...$normalizer
        );
    }
}
