<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Symfony\AI\Platform\Model;

/**
 * ACP model with protocol version and capabilities.
 */
final class Acp extends Model
{
    /**
     * @var array<string, mixed>
     */
    public array $clientCapabilities = [];

    /**
     * @var list<string>
     */
    public array $requiredAgentCapabilities = [];

    public int $protocolVersion = 1;
}
