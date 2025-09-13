<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface SpeechPlatformInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generate(DeferredResult $result, array $options): ?DeferredResult;

    /**
     * @param array<mixed>|string|object $input   The input data
     * @param array<string, mixed>       $options The options to customize the model invocation
     *
     * {@see PlatformInterface::invoke()}
     */
    public function listen(object|array|string $input, array $options): ?DeferredResult;
}
