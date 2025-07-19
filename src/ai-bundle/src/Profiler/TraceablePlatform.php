<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Profiler;

use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultPromise;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type PlatformCallData array{
 *     model: Model,
 *     input: array<mixed>|string|object,
 *     options: array<string, mixed>,
 *     result: ResultPromise,
 * }
 */
final class TraceablePlatform implements PlatformInterface
{
    /**
     * @var PlatformCallData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly PlatformInterface $platform,
    ) {
    }

    public function invoke(Model $model, array|string|object $input, array $options = []): ResultPromise
    {
        $result = $this->platform->invoke($model, $input, $options);

        if ($input instanceof File) {
            $input = $input::class.': '.$input->getFormat();
        }

        $this->calls[] = [
            'model' => $model,
            'input' => \is_object($input) ? clone $input : $input,
            'options' => $options,
            'result' => $result,
        ];

        return $result;
    }
}
