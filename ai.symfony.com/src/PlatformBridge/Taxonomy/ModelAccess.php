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
 * Whether a bridge is tied to the models of a single vendor.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum ModelAccess: string implements Option
{
    case MultiVendor = 'multi-vendor';
    case SingleVendor = 'single-vendor';

    public function label(): string
    {
        return match ($this) {
            self::MultiVendor => 'Provider-agnostic',
            self::SingleVendor => 'Single provider',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MultiVendor => 'One bridge, models from several model makers (OpenAI, Meta, Mistral, Qwen...).',
            self::SingleVendor => 'Only the models of the company behind the bridge.',
        };
    }
}
