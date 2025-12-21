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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

#[AsTool('tool_with_ToolParameter_attribute', 'A tool which has a parameter with described with #[ToolParameter] attribute')]
final class ToolWithToolParameterAttribute
{
    /**
     * @param string       $animal           The animal given to the tool
     * @param int          $numberOfArticles The number of articles given to the tool
     * @param string       $infoEmail        The info email given to the tool
     * @param string       $locales          The locales given to the tool
     * @param string       $text             The text given to the tool
     * @param int          $number           The number given to the tool
     * @param array<mixed> $products         The products given to the tool
     * @param string       $shippingAddress  The shipping address given to the tool
     */
    public function __invoke(
        #[With(enum: ['dog', 'cat', 'bird'])]
        string $animal,
        #[With(const: 42)]
        int $numberOfArticles,
        #[With(const: 'info@example.de')]
        string $infoEmail,
        #[With(const: ['de', 'en'])]
        string $locales,
        #[With(
            pattern: '^[a-zA-Z]+$',
            minLength: 1,
            maxLength: 10,
        )]
        string $text,
        #[With(
            minimum: 1,
            maximum: 10,
            multipleOf: 2,
            exclusiveMinimum: 1,
            exclusiveMaximum: 10,
        )]
        int $number,
        #[With(
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
            minContains: 1,
            maxContains: 10,
        )]
        array $products,
        #[With(
            minProperties: 1,
            maxProperties: 10,
            dependentRequired: true,
        )]
        string $shippingAddress,
    ): string {
        return 'Hello, World!';
    }
}
