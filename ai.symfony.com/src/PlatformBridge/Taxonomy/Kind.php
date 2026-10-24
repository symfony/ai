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
 * What kind of service or component a bridge integrates.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
enum Kind: string implements Option
{
    case ModelProvider = 'model-provider';
    case InferencePlatform = 'inference-platform';
    case CloudPlatform = 'cloud-platform';
    case Gateway = 'gateway';
    case LocalRuntime = 'local-runtime';
    case CodingAgent = 'coding-agent';
    case BuildingBlock = 'building-block';

    public function label(): string
    {
        return match ($this) {
            self::ModelProvider => 'Model provider',
            self::InferencePlatform => 'Inference platform',
            self::CloudPlatform => 'Cloud platform',
            self::Gateway => 'Gateway & router',
            self::LocalRuntime => 'Local runtime',
            self::CodingAgent => 'Coding agent',
            self::BuildingBlock => 'Building block',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ModelProvider => 'The company that trains the models serves them through its own API.',
            self::InferencePlatform => 'Hosts models trained by others, often open-weight ones, behind an API.',
            self::CloudPlatform => 'AI services of a hyperscaler, inside your cloud account and its regions.',
            self::Gateway => 'One API key and endpoint in front of many providers.',
            self::LocalRuntime => 'Runs models on your own hardware.',
            self::CodingAgent => 'Drives a coding agent CLI installed on the machine.',
            self::BuildingBlock => 'Protocols, catalogs and decorators to compose with other bridges.',
        };
    }
}
