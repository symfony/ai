<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Bridge\Gemini\Decision\InputNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;

final class InputNormalizerTest extends TestCase
{
    public function testItSupportsInputsOfDecisionsOnly()
    {
        $normalizer = new InputNormalizer();

        $options = [Contract::CONTEXT_OPTIONS => ['questions' => []]];

        $this->assertTrue($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Decision('gemini-decision-1')] + $options));
        $this->assertTrue($normalizer->supportsNormalization(new Collection(new Text('state')), context: [Contract::CONTEXT_MODEL => new Decision('gemini-decision-1')] + $options));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Gemini('gemini-2.5-flash')] + $options));
        $this->assertFalse($normalizer->supportsNormalization(['is_urgent' => []], context: [Contract::CONTEXT_MODEL => new Decision('gemini-decision-1')]));
    }

    public function testItSendsTextAsAString()
    {
        $this->assertSame(['input' => 'My invoice is wrong.'], $this->normalize('My invoice is wrong.'));
        $this->assertSame(['input' => 'My invoice is wrong.'], $this->normalize(new Text('My invoice is wrong.')));
    }

    public function testItEncodesStructuredStateAsJsonText()
    {
        $this->assertSame(['input' => '{"amount":5000,"country":"DE"}'], $this->normalize(['amount' => 5000, 'country' => 'DE']));
    }

    public function testItSendsACollectionAsContentBlocks()
    {
        $this->assertSame(['input' => [
            ['type' => 'text', 'text' => 'A yearbook page.'],
            ['type' => 'image', 'data' => base64_encode('first image'), 'mime_type' => 'image/png'],
            ['type' => 'image', 'data' => base64_encode('second image'), 'mime_type' => 'image/jpeg'],
        ]], $this->normalize(new Collection(new Text('A yearbook page.'), new Image('first image', 'image/png'), new Image('second image', 'image/jpeg'))));
    }

    public function testItRejectsImagesForModelsWithoutImageInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "text-only" does not support image inputs.');

        $this->normalize(new Collection(new Text('A photo'), new Image('image', 'image/png')), new Decision('text-only'));
    }

    public function testItRejectsUnsupportedContent()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "gemini-decision-1" does not support "Symfony\AI\Platform\Message\Content\Audio" inputs.');

        $this->normalize(new Collection(new Text('A call'), new Audio('audio', 'audio/mpeg')));
    }

    /**
     * @param string|array<mixed>|Text|Collection $input
     *
     * @return array<string, mixed>
     */
    private function normalize(string|array|Text|Collection $input, Decision $model = new Decision('gemini-decision-1', [Capability::DECISION, Capability::INPUT_TEXT, Capability::INPUT_IMAGE])): array
    {
        return (new InputNormalizer())->normalize($input, context: [Contract::CONTEXT_MODEL => $model]);
    }
}
