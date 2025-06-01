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
$model = new Model('facebook/bart-large-mnli');

$text = 'Hi, I recently bought a device from your company but it is not working as advertised and I would like to get reimbursed!';
$response = $platform->request($model, $text, [
    'task' => Task::ZERO_SHOT_CLASSIFICATION,
    'candidate_labels' => ['refund', 'legal', 'faq'],
]);

dump($response->getContent());
