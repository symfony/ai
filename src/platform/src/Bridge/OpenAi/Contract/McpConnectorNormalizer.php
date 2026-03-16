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
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpConnector;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpConnectorNormalizer extends ModelContractNormalizer
{
    /**
     * @param McpConnector $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $result = [
            'type' => 'mcp',
            'connector_id' => $data->getConnectorId(),
            'server_label' => $data->getServerLabel(),
            'require_approval' => McpToolNormalizer::normalizeApproval($data->getRequireApproval()),
        ];

        if (null !== $data->getServerDescription()) {
            $result['server_description'] = $data->getServerDescription();
        }

        if (null !== $data->getAuthorization()) {
            $result['authorization'] = $data->getAuthorization();
        }

        if (null !== $data->getAllowedTools()) {
            $result['allowed_tools'] = $data->getAllowedTools();
        }

        return $result;
    }

    protected function supportedDataClass(): string
    {
        return McpConnector::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
