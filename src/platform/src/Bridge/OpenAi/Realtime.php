<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
class Realtime extends Model
{
    /**
     * @param non-empty-string     $name
     * @param Capability[]         $capabilities
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, array $capabilities = [Capability::REALTIME_SESSION], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
