<?php

use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Model;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['HUGGINGFACE_KEY'])) {
    echo 'Please set the HUGGINGFACE_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['HUGGINGFACE_KEY']);
$model = new Model('microsoft/tapex-base');

$input = [
    'query' => 'select year where city = beijing',
    'table' => [
        'year' => [1896, 1900, 1904, 2004, 2008, 2012],
        'city' => ['athens', 'paris', 'st. louis', 'athens', 'beijing', 'london'],
    ],
];

$response = $platform->request($model, $input, [
    'task' => Task::TABLE_QUESTION_ANSWERING,
]);

dump($response->getContent());
