<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\McpConnectorNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ApprovalFilter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpConnector;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class McpConnectorNormalizerTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(array $expected, McpConnector $connector)
    {
        $actual = (new McpConnectorNormalizer())->normalize($connector, null, [Contract::CONTEXT_MODEL => new Gpt('gpt-4o-mini')]);
        $this->assertSame($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        yield 'minimal' => [
            [
                'type' => 'mcp',
                'connector_id' => 'connector_gmail',
                'server_label' => 'gmail',
                'require_approval' => 'never',
            ],
            new McpConnector(
                connectorId: 'connector_gmail',
                serverLabel: 'gmail',
            ),
        ];

        yield 'with all options' => [
            [
                'type' => 'mcp',
                'connector_id' => 'connector_googledrive',
                'server_label' => 'gdrive',
                'require_approval' => 'always',
                'server_description' => 'Google Drive connector',
                'authorization' => 'oauth-token-123',
                'allowed_tools' => ['search', 'read'],
            ],
            new McpConnector(
                connectorId: 'connector_googledrive',
                serverLabel: 'gdrive',
                serverDescription: 'Google Drive connector',
                authorization: 'oauth-token-123',
                requireApproval: 'always',
                allowedTools: ['search', 'read'],
            ),
        ];

        yield 'with approval filter' => [
            [
                'type' => 'mcp',
                'connector_id' => 'connector_gmail',
                'server_label' => 'gmail',
                'require_approval' => [
                    'never' => [
                        'tool_names' => ['read_email'],
                    ],
                ],
            ],
            new McpConnector(
                connectorId: 'connector_gmail',
                serverLabel: 'gmail',
                requireApproval: new ApprovalFilter(['read_email']),
            ),
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new McpConnectorNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model]),
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $connector = new McpConnector(connectorId: 'connector_gmail', serverLabel: 'gmail');
        $gpt = new Gpt('gpt-4o-mini');

        yield 'supported' => [$connector, $gpt, true];
        yield 'unsupported model' => [$connector, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
