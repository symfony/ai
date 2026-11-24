<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Taxonomy;

/**
 * Where the inference runs.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Deployment: string implements Option
{
    case Cloud = 'cloud';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Cloud => 'Cloud',
            self::Local => 'Local',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Cloud => 'Models run on remote servers reached over HTTP.',
            self::Local => 'Models run on your own machine or server, prompts never leave it.',
        };
    }
}
