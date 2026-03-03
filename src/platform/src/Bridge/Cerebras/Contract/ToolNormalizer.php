<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Contract;

use Symfony\AI\Platform\Contract\Normalizer\ToolNormalizer as BaseToolNormalizer;

/**
 * @author Alexander Hinze <alexander@hinze.berlin>
 */
class ToolNormalizer extends BaseToolNormalizer
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = parent::normalize($data, $format, $context);

        $array['function']['parameters'] ??= ['type' => 'object'];

        return $array;
    }
}
