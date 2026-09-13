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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

#[AsTool('tool_with_external_ref', 'A tool with external schema provider')]
final class ToolWithExternalRef
{
    public function __invoke(
        #[Schema(provider: 'external_schema_provider')]
        string $data,
    ): string {
        return "Data: $data";
    }
}
