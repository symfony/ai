<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Router\Transformer;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Router\RouterContext;

/**
 * Transforms input before routing to a model.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface TransformerInterface
{
    /**
     * Transforms the input before routing.
     *
     * @return Input New input with transformed messages/options
     */
    public function transform(Input $input, RouterContext $context): Input;
}
