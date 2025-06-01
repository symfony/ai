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
$model = new Model('sentence-transformers/all-MiniLM-L6-v2');

$input = [
    'source_sentence' => 'That is a happy dog',
    'sentences' => [
        'That is a happy canine',
        'That is a happy cat',
        'Today is a sunny day',
    ],
];

$response = $platform->request($model, $input, [
    'task' => Task::SENTENCE_SIMILARITY,
]);

dump($response->getContent());
