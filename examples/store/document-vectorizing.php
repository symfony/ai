<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$embeddings = new Embeddings(Embeddings::TEXT_3_LARGE);

$textDocuments = [
    new TextDocument(Uuid::v4(), 'Hello World'),
    new TextDocument(Uuid::v4(), 'Lorem ipsum dolor sit amet'),
    new TextDocument(Uuid::v4(), 'PHP Hypertext Preprocessor'),
];

$vectorizer = new Vectorizer($platform, $embeddings);
$vectorDocuments = $vectorizer->vectorizeDocuments($textDocuments);

dump(array_map(fn (VectorDocument $document) => $document->vector->getDimensions(), $vectorDocuments));
