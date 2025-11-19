<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Command;

use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('ai:huggingface:model-info', 'Retrieves inference information about a model on Hugging Face')]
final class ModelInfoCommand
{
    public function __construct(
        private readonly ApiClient $apiClient,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Name of the model to get information about')]
        string $model,
    ): int {
        $io->title('Hugging Face Model Information');

        $info = $this->apiClient->getModel($model);

        $io->text(\sprintf('Model: %s', $model));

        $io->horizontalTable(
            ['ID', 'Downloads', 'Likes', 'Task', 'Warm'],
            [[
                $info['id'],
                $info['downloads'],
                $info['likes'],
                $info['pipeline_tag'],
                ('warm' === ($info['inference'] ?? null)) ? 'yes' : 'no',
            ]]
        );

        $io->text('Inference Provider:');
        if (!isset($info['inferenceProviderMapping']) || [] === $info['inferenceProviderMapping']) {
            $io->text('<comment>No inference provider information available for this model.</comment>');
            $io->newLine();
        } else {
            $io->horizontalTable(
                ['Provider', 'Status', 'Provider ID', 'Task', 'Is Model Author'],
                array_map(fn (string $provider, array $data) => [
                    $provider,
                    $data['status'],
                    $data['providerId'],
                    $data['task'],
                    $data['isModelAuthor'] ? 'yes' : 'no',
                ], array_keys($info['inferenceProviderMapping']), $info['inferenceProviderMapping'])
            );
        }

        return Command::SUCCESS;
    }
}
