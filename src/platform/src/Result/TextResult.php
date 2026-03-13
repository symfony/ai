<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Speech\SpeechAwareInterface;
use Symfony\AI\Platform\Speech\SpeechAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextResult extends BaseResult implements SpeechAwareInterface
{
    use SpeechAwareTrait;

    public function __construct(
        private readonly string $content,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
