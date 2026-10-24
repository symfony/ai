<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', env('OLLAMA_API_KEY'), httpClient: http_client());
$result = $platform->invoke('clef-flash', new Evaluation(
    'A photograph to classify.',
    ['outdoors' => new NoulQuestion('Was this photograph taken outdoors?')],
    images: [Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg')],
));

$answer = $result->asObject(Answers::class)->getNoul('outdoors');
echo sprintf('Outdoors: %s (probability: %.2f)', $answer->isTrue() ? 'yes' : 'no', $answer->getProbability()).\PHP_EOL;
