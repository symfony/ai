<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Question;

/**
 * A typed question Jev evaluates against the state of an evaluation.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
interface QuestionInterface extends \JsonSerializable
{
    /**
     * @return array{type: string, instructions: string|array<mixed>, criteria?: array<mixed>}
     */
    public function jsonSerialize(): array;
}
