<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\Embeddings;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__).'/bootstrap.php';

$fullResult = [];

// Add models
/** @var Symfony\Component\HttpClient\Response\CurlResponse $response */
$responseModels = http_client()->request('GET', 'https://openrouter.ai/api/v1/models');
$models = json_decode($responseModels->getContent(), true, 512, \JSON_THROW_ON_ERROR);
foreach ($models['data'] as $model) {
    $capabilities = [];

    foreach ($model['architecture']['input_modalities'] as $inputModality) {
        switch ($inputModality) {
            case 'text':
                $capabilities[] = Capability::INPUT_TEXT;
                break;
            case 'image':
                $capabilities[] = Capability::INPUT_IMAGE;
                break;
            case 'audio':
                $capabilities[] = Capability::INPUT_AUDIO;
                break;
            case 'file':
                $capabilities[] = Capability::INPUT_PDF;
                break;
            case 'video':
                $capabilities[] = Capability::INPUT_MULTIMODAL; // Video?
                break;
            default:
                echo 'What is '.$inputModality.' for input?'."\n";
        }
    }

    foreach ($model['architecture']['output_modalities'] as $outputModality) {
        switch ($outputModality) {
            case 'text':
                $capabilities[] = Capability::OUTPUT_TEXT;
                break;
            case 'image':
                $capabilities[] = Capability::OUTPUT_IMAGE;
                break;
            default:
                echo 'What is '.$outputModality.' for output?'."\n";
        }
    }
    // [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
    $fullResult[$model['id']] = [
        'class' => Model::class,
        'capabilities' => $capabilities,
    ];
}

// Add Embeddings
/** @var Symfony\Component\HttpClient\Response\CurlResponse $response */
$responseEmbeddings = http_client()->request('GET', 'https://openrouter.ai/api/v1/embeddings/models');
$embeddings = json_decode($responseEmbeddings->getContent(), true, 512, \JSON_THROW_ON_ERROR);
foreach ($embeddings['data'] as $embedding) {
    $fullResult[$embedding['id']] = [
        'class' => Embeddings::class,
        'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
    ];
}

$phpResult = var_export($fullResult, true);

echo str_replace(
    [
        "'Symfony\\\\AI\\\\Platform\\\\Bridge\\\\OpenRouter\\\\Embeddings'",
        "'Symfony\\\\AI\\\\Platform\\\\Model'",
    ],
    [
        'Embeddings::class',
        'Model::class',
    ],
    $phpResult
);
