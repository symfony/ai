<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Attribute;

/**
 * Maps the flat tool-call payload onto a single DTO method parameter.
 *
 * The tool schema exposes the DTO properties at the root. At runtime the entire
 * tool-call argument object is denormalized into that parameter. Valid only on a
 * method with exactly one non-nullable concrete class parameter.
 *
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapToolArguments
{
}
