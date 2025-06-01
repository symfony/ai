<?php

use Codewithkyrian\Transformers\Pipelines\Task;
use Symfony\AI\Platform\Bridge\TransformersPHP\PlatformFactory;
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

if (!extension_loaded('ffi') || '1' !== ini_get('ffi.enable')) {
    echo 'FFI extension is not loaded or enabled. Please enable it in your php.ini file.'.\PHP_EOL;
    echo 'See https://github.com/CodeWithKyrian/transformers-php for setup instructions.'.\PHP_EOL;
    exit(1);
}

if (!is_dir(dirname(__DIR__, 2).'/.transformers-cache/Xenova/LaMini-Flan-T5-783M')) {
    echo 'Model "Xenova/LaMini-Flan-T5-783M" not found. Downloading it will be part of the first run. This may take a while...'.\PHP_EOL;
}

$platform = PlatformFactory::create();
$model = new Model('Xenova/LaMini-Flan-T5-783M');

$response = $platform->request($model, 'How many continents are there in the world?', [
    'task' => Task::Text2TextGeneration,
]);

echo $response->getContent().\PHP_EOL;
