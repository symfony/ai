<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\InputNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;

final class InputNormalizerTest extends TestCase
{
    public function testItSupportsInputsOfDecisionsOnly()
    {
        $normalizer = new InputNormalizer();
        $options = [Contract::CONTEXT_OPTIONS => ['questions' => []]];

        $this->assertTrue($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Gpt('gpt-6-luna', [Capability::DECISION])] + $options));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Gpt('gpt-6-luna', [Capability::DECISION])]));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Gpt('gpt-5.6-luna')] + $options));
    }

    public function testItSendsTextAsAString()
    {
        $this->assertSame(['input' => 'My invoice is wrong.'], $this->normalize('My invoice is wrong.'));
        $this->assertSame(['input' => 'My invoice is wrong.'], $this->normalize(new Text('My invoice is wrong.')));
    }

    public function testItSendsStructuredStateAsJsonContent()
    {
        $this->assertSame(['input' => [['role' => 'user', 'content' => [
            ['type' => 'input_json', 'data' => ['amount' => 5000, 'country' => 'DE']],
        ]]]], $this->normalize(['amount' => 5000, 'country' => 'DE']));
    }

    public function testItSendsACollectionAsOneUserMessage()
    {
        $this->assertSame(['input' => [['role' => 'user', 'content' => [
            ['type' => 'input_text', 'text' => 'A yearbook page.'],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.base64_encode('first image')],
        ]]]], $this->normalize(new Collection(new Text('A yearbook page.'), new Image('first image', 'image/png'))));
    }

    public function testItRejectsImagesForModelsWithoutImageInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "text-only" does not support image inputs.');

        $this->normalize(new Image('image', 'image/png'), new Gpt('text-only', [Capability::DECISION]));
    }

    /**
     * @param string|array<mixed>|Text|Image|Collection $input
     *
     * @return array<string, mixed>|string
     */
    private function normalize(string|array|Text|Image|Collection $input, Gpt $model = new Gpt('gpt-6-luna', [Capability::DECISION, Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE])): array|string
    {
        // Through the contract, so the normalizers of the Responses API don't take over
        return OpenAiContract::create()->createRequestPayload($model, $input, ['questions' => []]);
    }
}
