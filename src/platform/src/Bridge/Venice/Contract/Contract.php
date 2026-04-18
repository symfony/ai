<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Contract;

use Symfony\AI\Platform\Bridge\Venice\VenicePayload;
use Symfony\AI\Platform\Contract as PlatformContract;
use Symfony\AI\Platform\PayloadInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Contract extends PlatformContract
{
    public static function create(NormalizerInterface ...$normalizer): PlatformContract
    {
        return parent::create(
            new AudioNormalizer(),
            ...$normalizer,
        );
    }

    public static function resolvePayload(array|string $payload): PayloadInterface
    {
        return new VenicePayload($payload);
    }
}
