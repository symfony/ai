<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MissingModelSupportException extends RuntimeException
{
    private function __construct(Model $model, string $support)
    {
        parent::__construct(\sprintf('Model "%s" (%s) does not support "%s".', $model->getName(), $model::class, $support));
    }

    public static function forToolCalling(Model $model): self
    {
        return new self($model, 'tool calling');
    }

    public static function forAudioInput(Model $model): self
    {
        return new self($model, 'audio input');
    }

    public static function forImageInput(Model $model): self
    {
        return new self($model, 'image input');
    }

    public static function forStructuredOutput(Model $model): self
    {
        return new self($model, 'structured output');
    }
}
