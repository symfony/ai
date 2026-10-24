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
 * Who operates the service the bridge talks to.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Hosting: string implements Option
{
    case Saas = 'saas';
    case SelfHosted = 'self-hosted';

    public function label(): string
    {
        return match ($this) {
            self::Saas => 'SaaS',
            self::SelfHosted => 'Self-hosted',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Saas => 'A managed service operated by the vendor, you sign up and get credentials.',
            self::SelfHosted => 'Software you install and operate yourself, on a laptop, a server or your cloud.',
        };
    }
}
