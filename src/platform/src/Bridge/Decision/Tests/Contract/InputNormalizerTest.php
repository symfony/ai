<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Contract\InputNormalizer;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

final class InputNormalizerTest extends TestCase
{
    public function testItSupportsInputsOfDecisionsOnly()
    {
        $normalizer = new InputNormalizer();
        $options = [Contract::CONTEXT_OPTIONS => ['questions' => []]];

        $this->assertTrue($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Model('jev-latest', [Capability::DECISION])] + $options));
        $this->assertTrue($normalizer->supportsNormalization(new Collection(), context: [Contract::CONTEXT_MODEL => new Model('jev-latest', [Capability::DECISION])] + $options));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Model('jev-latest', [Capability::DECISION])]));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Model('llama3')] + $options));
        $this->assertFalse($normalizer->supportsNormalization(new Image('image', 'image/png'), context: [Contract::CONTEXT_MODEL => new Model('jev-latest', [Capability::DECISION])] + $options));
    }

    public function testItSendsTextAndStructuredDataAsState()
    {
        $this->assertSame(['state' => 'My invoice is wrong.'], $this->normalize('My invoice is wrong.'));
        $this->assertSame(['state' => 'My invoice is wrong.'], $this->normalize(new Text('My invoice is wrong.')));
        $this->assertSame(['state' => ['amount' => 5000, 'country' => 'DE']], $this->normalize(['amount' => 5000, 'country' => 'DE']));
    }

    public function testItSendsTheImagesOfACollectionAfterItsText()
    {
        $this->assertSame([
            'state' => "A yearbook page.\n\nThe caption reads: Class of 1998.",
            'images' => [base64_encode('first image'), base64_encode('second image')],
        ], $this->normalize(new Collection(
            new Text('A yearbook page.'),
            new Image('first image', 'image/png'),
            new Text('The caption reads: Class of 1998.'),
            new Image('second image', 'image/jpeg'),
        )));
    }

    public function testItRequiresAText()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "clef-flash" requires a text input.');

        $this->normalize(new Collection(new Image('image', 'image/png')));
    }

    public function testItRejectsImagesForModelsWithoutImageInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "jev-latest" does not support image inputs.');

        $this->normalize(new Collection(new Text('A photo'), new Image('image', 'image/png')), new Model('jev-latest', [Capability::DECISION]));
    }

    public function testItRejectsUnsupportedContent()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "clef-flash" does not support "Symfony\AI\Platform\Message\Content\Audio" inputs.');

        $this->normalize(new Collection(new Text('A call'), new Audio('audio', 'audio/mpeg')));
    }

    /**
     * @param string|array<mixed>|Text|Collection $input
     *
     * @return array<string, mixed>
     */
    private function normalize(string|array|Text|Collection $input, Model $model = new Model('clef-flash', [Capability::DECISION, Capability::INPUT_IMAGE])): array
    {
        return (new InputNormalizer())->normalize($input, context: [Contract::CONTEXT_MODEL => $model]);
    }
}
