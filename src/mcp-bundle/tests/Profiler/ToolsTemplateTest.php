<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

/**
 * @author Simo Heinonen <simo@dudgeon.fi>
 */
final class ToolsTemplateTest extends TestCase
{
    public function testRendersParameterTypesForSchemasWithoutTopLevelType()
    {
        $html = $this->render([
            'type' => 'object',
            'properties' => [
                'plain' => ['type' => 'integer'],
                'union' => ['type' => ['string', 'null']],
                'any_of' => ['maxLength' => 320, 'anyOf' => [['type' => 'string'], ['type' => 'null']]],
                'one_of' => ['oneOf' => [['type' => 'boolean'], ['type' => 'null']]],
                'nested' => ['anyOf' => [['type' => ['string', 'integer']], ['$ref' => '#/$defs/foo'], ['type' => 'string']]],
                'enum_only' => ['enum' => ['a', 'b']],
                'ref_only' => ['$ref' => '#/$defs/foo'],
            ],
        ]);

        $this->assertSame([
            'plain' => 'integer',
            'union' => 'string|null',
            'any_of' => 'string|null',
            'one_of' => 'boolean|null',
            'nested' => 'string|integer',
            'enum_only' => 'any',
            'ref_only' => 'any',
        ], $this->extractTypes($html));
    }

    /**
     * @param array<string, mixed> $inputSchema
     */
    private function render(array $inputSchema): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'Mcp');

        $twig = new Environment($loader, ['strict_variables' => true, 'debug' => true]);
        $twig->addExtension(new DebugExtension());

        return $twig->render('@Mcp/tools.html.twig', [
            'tools' => [[
                'name' => 'search',
                'description' => null,
                'inputSchema' => $inputSchema,
            ]],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function extractTypes(string $html): array
    {
        preg_match_all('#<td><code>([^<]+)</code></td>\s*<td><span class="badge badge-primary">([^<]*)</span></td>#', $html, $matches);

        return array_combine($matches[1], $matches[2]);
    }
}
