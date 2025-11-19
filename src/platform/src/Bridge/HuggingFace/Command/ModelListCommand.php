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
use Symfony\AI\Platform\Model;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('ai:huggingface:model-list', 'Lists all available models on Hugging Face')]
final class ModelListCommand
{
    public function __construct(
        private readonly ApiClient $apiClient,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Name of the inference provider to filter models by', 'provider', 'p')]
        ?string $provider = null,
        #[Option('Name of the task to filter models by', 'task', 't')]
        ?string $task = null,
        #[Option('Search term to filter models by', 'search', 's')]
        ?string $search = null,
        #[Option('Only list models that are "warm" (i.e. ready for inference without cold start)', 'warm', 'w')]
        bool $warm = false,
    ): int {
        $io->title('Hugging Face Model Listing');

        $models = $this->apiClient->getModels($provider, $task, $search, $warm);

        if (0 === \count($models)) {
            $io->error('No models found for the given filters.');

            return Command::FAILURE;
        }

        $formatModel = function (Model $model) {
            return \sprintf('%s <comment>[%s]</>', $model->getName(), implode(', ', $model->getOptions()['tags'] ?? []));
        };

        $io->listing(array_map($formatModel, $models));

        $io->success(\sprintf('Found %d model(s).', \count($models)));

        return Command::SUCCESS;
    }
}
