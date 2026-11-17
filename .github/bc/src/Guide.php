<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * Sections of the Symfony Backward Compatibility Promise that findings point to.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Guide
{
    public const URL = 'https://symfony.com/doc/current/contributing/code/bc.html';

    public const DEPRECATE = 'https://symfony.com/doc/current/contributing/code/conventions.html#deprecating-code';
    public const MOVE_CLASS = self::URL.'#changing-classes';
    public const ADD_INTERFACE_METHOD = self::URL.'#changing-interfaces';
    public const REPLACE_INTERFACE_METHOD = self::URL.'#changing-interfaces';
    public const VIRTUAL_ARGUMENT = self::URL.'#adding-an-argument-to-a-public-method';
    public const DEFAULT_VALUE = self::URL.'#changing-classes';
    public const NARROW_TYPE = self::URL.'#changing-classes';
    public const RETURN_TYPE = self::URL.'#changing-classes';
    public const FINAL = self::URL.'#the-final-keyword';
    public const INTERNAL = self::URL.'#using-our-classes';
    public const NO_UPGRADE_PATH = self::URL.'#making-code-changes-in-a-backward-compatible-way';
}
