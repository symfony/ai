<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Codewithkyrian\Transformers\Pipelines\Task;
use Symfony\AI\Platform\Bridge\TransformersPhp\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

if (!extension_loaded('ffi') || '1' !== ini_get('ffi.enable')) {
    echo 'FFI extension is not loaded or enabled. Please enable it in your php.ini file.'.\PHP_EOL;
    echo 'See https://github.com/CodeWithKyrian/transformers-php for setup instructions.'.\PHP_EOL;
    exit(1);
}

if (!is_dir(dirname(__DIR__).'/.transformers-cache/Xenova/all-MiniLM-L6-v2')) {
    echo 'Model "Xenova/all-MiniLM-L6-v2" not found. Downloading it will be part of the first run. This may take a while...'.\PHP_EOL;
}

$platform = PlatformFactory::create();

$text = <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT;

$result = $platform->invoke('Xenova/all-MiniLM-L6-v2', $text, ['task' => Task::Embeddings]);

print_vectors($result);
