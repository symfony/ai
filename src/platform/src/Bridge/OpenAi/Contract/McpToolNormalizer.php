<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ApprovalFilter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpTool;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolNormalizer extends ModelContractNormalizer
{
    /**
     * @param McpTool $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $result = [
            'type' => 'mcp',
            'server_label' => $data->getServerLabel(),
            'server_url' => $data->getServerUrl(),
            'require_approval' => self::normalizeApproval($data->getRequireApproval()),
        ];

        if (null !== $data->getServerDescription()) {
            $result['server_description'] = $data->getServerDescription();
        }

        if (null !== $data->getHeaders()) {
            $result['headers'] = $data->getHeaders();
        }

        if (null !== $data->getAllowedTools()) {
            $result['allowed_tools'] = $data->getAllowedTools();
        }

        return $result;
    }

    /**
     * @return string|array{never: array{tool_names: list<string>}}
     */
    public static function normalizeApproval(string|ApprovalFilter $approval): string|array
    {
        if ($approval instanceof ApprovalFilter) {
            return [
                'never' => [
                    'tool_names' => $approval->getNever(),
                ],
            ];
        }

        return $approval;
    }

    protected function supportedDataClass(): string
    {
        return McpTool::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
