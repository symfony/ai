<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput\Streaming;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Trip;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validation;

final class PartialObjectStreamListenerTest extends TestCase
{
    public function testYieldsPartialObjectDeltaPerSnapshot()
    {
        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $stream = $this->buildStream([
            '{"name":"Ber',
            'lin","population":35',
            '00000,"country":"Germany"}',
        ], [$listener]);

        $deltas = iterator_to_array($stream->getContent(), false);

        $partials = array_values(array_filter($deltas, static fn ($d) => $d instanceof PartialObjectDelta));
        $this->assertCount(3, $partials);

        $first = $partials[0]->getObject();
        $this->assertInstanceOf(City::class, $first);
        $this->assertSame('Ber', $first->name);
        $this->assertNull($first->population);

        $last = $partials[2]->getObject();
        $this->assertInstanceOf(City::class, $last);
        $this->assertSame('Berlin', $last->name);
        $this->assertSame(3500000, $last->population);
        $this->assertSame('Germany', $last->country);
    }

    public function testPopulatesNestedObjectsInPlace()
    {
        $berlin = new City(name: 'Berlin');
        $trip = new Trip(destination: $berlin);
        $listener = new PartialObjectStreamListener(new Serializer(), Trip::class, $trip);
        $stream = $this->buildStream([
            '{"title":"City trip","destination":{"popu',
            'lation":3500000}}',
        ], [$listener]);

        iterator_to_array($stream->getContent(), false);

        $this->assertSame($trip, $listener->getFinalObjectResult()?->getContent());
        $this->assertSame($berlin, $trip->destination);
        $this->assertSame('Berlin', $berlin->name);
        $this->assertSame(3500000, $berlin->population);
    }

    public function testStillYieldsOriginalTextDeltas()
    {
        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $stream = $this->buildStream([
            '{"name":"Ber',
            'lin"}',
        ], [$listener]);

        $deltas = iterator_to_array($stream->getContent(), false);

        $texts = array_values(array_filter($deltas, static fn ($d) => $d instanceof TextDelta));
        $this->assertCount(2, $texts);
        $this->assertSame('{"name":"Ber', $texts[0]->getText());
        $this->assertSame('lin"}', $texts[1]->getText());
    }

    public function testDuplicateSnapshotsAreSuppressed()
    {
        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $stream = $this->buildStream([
            '{"name":"Berlin"',
            ',',
            ' ',
            '"country":"Germany"}',
        ], [$listener]);

        $deltas = iterator_to_array($stream->getContent(), false);
        $partials = array_values(array_filter($deltas, static fn ($d) => $d instanceof PartialObjectDelta));

        // The intermediate "," and " " chunks don't change the parsed structure.
        $this->assertCount(2, $partials);
    }

    public function testCapturesFinalObjectResultOnComplete()
    {
        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $stream = $this->buildStream([
            '{"name":"Berlin","population":3500000}',
        ], [$listener]);

        iterator_to_array($stream->getContent(), false);

        $final = $listener->getFinalObjectResult();
        $this->assertNotNull($final);
        $city = $final->getContent();
        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Berlin', $city->name);
        $this->assertSame(3500000, $city->population);
    }

    public function testValidatorRunsOnlyOnFinalObject()
    {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new class implements \Symfony\Component\Validator\Mapping\Loader\LoaderInterface {
                public function loadClassMetadata(ClassMetadata $metadata): bool
                {
                    if (City::class !== $metadata->getClassName()) {
                        return false;
                    }

                    $metadata->addPropertyConstraint('country', new NotBlank());

                    return true;
                }
            })
            ->getValidator();

        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $listener->setValidator($validator);

        $stream = $this->buildStream([
            '{"name":"Berlin"',  // partial: missing country, would violate constraint if validated
            ',"country":"Germany"}',
        ], [$listener]);

        // Iterating must NOT throw — partials are not validated.
        $deltas = iterator_to_array($stream->getContent(), false);
        $partials = array_values(array_filter($deltas, static fn ($d) => $d instanceof PartialObjectDelta));
        $this->assertNotEmpty($partials);

        // Final object is fully populated and passes validation.
        $final = $listener->getFinalObjectResult();
        $this->assertNotNull($final);
        $content = $final->getContent();
        $this->assertInstanceOf(City::class, $content);
        $this->assertSame('Germany', $content->country);
    }

    public function testValidatorRejectsInvalidFinalObject()
    {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new class implements \Symfony\Component\Validator\Mapping\Loader\LoaderInterface {
                public function loadClassMetadata(ClassMetadata $metadata): bool
                {
                    if (City::class !== $metadata->getClassName()) {
                        return false;
                    }

                    $metadata->addPropertyConstraint('country', new NotBlank());

                    return true;
                }
            })
            ->getValidator();

        $listener = new PartialObjectStreamListener(new Serializer(), City::class);
        $listener->setValidator($validator);

        $stream = $this->buildStream([
            '{"name":"Berlin"}',
        ], [$listener]);

        iterator_to_array($stream->getContent(), false);

        $this->expectException(ValidationException::class);

        $listener->getFinalObjectResult();
    }

    /**
     * @param string[]                                                   $textChunks
     * @param list<\Symfony\AI\Platform\Result\Stream\ListenerInterface> $listeners
     */
    private function buildStream(array $textChunks, array $listeners): StreamResult
    {
        $generator = (static function () use ($textChunks): \Generator {
            foreach ($textChunks as $chunk) {
                yield new TextDelta($chunk);
            }
        })();

        return new StreamResult($generator, $listeners);
    }
}
