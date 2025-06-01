<?php

use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['HUGGINGFACE_KEY'])) {
    echo 'Please set the HUGGINGFACE_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['HUGGINGFACE_KEY']);
$model = new Model('google/vit-base-patch16-224');

$image = Image::fromFile(dirname(__DIR__, 2).'/tests/Fixture/image.jpg');
$response = $platform->request($model, $image, [
    'task' => Task::IMAGE_CLASSIFICATION,
]);

dump($response->getContent());
