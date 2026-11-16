<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Source;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Serializer\MessageNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

final class SourceCollectionNormalizerTest extends TestCase
{
    public function testSourcesInTheMetadataOfAnAnswerSurviveTheMessageNormalizer()
    {
        $sources = new SourceCollection([
            new Source('Symfony', 'https://symfony.com', 'Symfony is a set of reusable PHP components.'),
            new Source('Symfony AI', 'https://ai.symfony.com', 'Symfony AI integrates AI into PHP applications.'),
        ]);
        $answer = Message::ofAssistant('Symfony is a PHP framework.');
        $answer->getMetadata()->add('sources', $sources);
        $serializer = new Serializer([new MessageNormalizer(), new SourceCollectionNormalizer()], [new JsonEncoder()]);

        $restored = $serializer->deserialize($serializer->serialize($answer, 'json'), MessageInterface::class, 'json');

        $this->assertEquals($sources, $restored->getMetadata()->get('sources'));
    }
}
