<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Command;

use Symfony\AI\Platform\Bridge\Bedrock\BedrockClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:bedrock:model-list', 'Lists available foundation models on Amazon Bedrock')]
final class ModelListCommand
{
    public function __construct(
        private readonly BedrockClient $bedrockClient,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Filter by provider name (e.g. "Anthropic", "Amazon", "Meta")', 'provider', 'p')]
        ?string $provider = null,
        #[Option('Filter by output modality (e.g. "TEXT", "IMAGE", "EMBEDDING")', 'output-modality', 'o')]
        ?string $outputModality = null,
        #[Option('Filter by inference type (e.g. "ON_DEMAND", "PROVISIONED")', 'inference-type', 'i')]
        ?string $inferenceType = null,
    ): int {
        $io->title('Amazon Bedrock Foundation Models');

        $models = $this->bedrockClient->listFoundationModels($provider, $outputModality, $inferenceType);

        if ([] === $models) {
            $io->warning('No models found for the given filters.');

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($models as $model) {
            $rows[] = [
                $model['modelId'],
                $model['modelName'],
                $model['providerName'],
                implode(', ', $model['inputModalities']),
                implode(', ', $model['outputModalities']),
            ];
        }

        $io->table(
            ['Model ID', 'Name', 'Provider', 'Input Modalities', 'Output Modalities'],
            $rows,
        );

        $io->success(\sprintf('Found %d model(s).', \count($models)));

        return Command::SUCCESS;
    }
}
