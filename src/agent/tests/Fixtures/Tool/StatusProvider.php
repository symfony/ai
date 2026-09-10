<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;

final class StatusProvider implements SchemaProviderInterface
{
    public function getSchemaFragment(array $context = []): array
    {
        return ['enum' => ['active', 'archived']];
    }
}
