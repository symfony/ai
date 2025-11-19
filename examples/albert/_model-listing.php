<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Albert\ApiClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once dirname(__DIR__).'/bootstrap.php';

$app = (new SingleCommandApplication('Albert API Model Listing'))
    ->setDescription('Lists all available models on Albert API')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $io->title('Albert API Model Listing');

        $apiClient = new ApiClient(env('ALBERT_API_URL'), env('ALBERT_API_KEY'), http_client());
        $models = $apiClient->getModels();

        if (0 === count($models)) {
            $io->error('No models found for this Albert API URL.');

            return Command::FAILURE;
        }

        $io->listing(
            array_map(fn (Model $model) => $model->getName(), $models)
        );

        return Command::SUCCESS;
    })
    ->run();
