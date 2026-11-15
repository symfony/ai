<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Decision\Question;

/**
 * A typed question a decision model evaluates against the state of a decision.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
interface QuestionInterface
{
    public function getInstructions(): string;
}
