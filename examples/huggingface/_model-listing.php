<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once dirname(__DIR__).'/bootstrap.php';

$app = (new SingleCommandApplication('HuggingFace Model Listing'))
    ->setDescription('Lists all available models on HuggingFace')
    ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Name of the inference provider to filter models by')
    ->addOption('task', 't', InputOption::VALUE_REQUIRED, 'Name of the task to filter models by')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $io->title('HuggingFace Model Listing');

        $provider = $input->getOption('provider');
        $task = $input->getOption('task');

        $models = (new ApiClient())->models($provider, $task);

        if (0 === count($models)) {
            $io->error('No models found for the given provider and task.');

            return Command::FAILURE;
        }

        $io->listing(
            array_map(fn (Model $model) => $model->getName(), $models)
        );

        return Command::SUCCESS;
    })
    ->run();
