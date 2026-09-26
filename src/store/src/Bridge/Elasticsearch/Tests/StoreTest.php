<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Elasticsearch\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Elasticsearch\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupOnExistingIndex()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 404,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => 'foo',
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCanSetupWithExtraOptions()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 400,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => 'foo',
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');

        $store->setup([
            'dimensions' => 768,
            'similarity' => 'dot_product',
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnUndefinedIndex()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 404,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The index "foo" does not exist.');
        $this->expectExceptionCode(0);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $store->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCanSave()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'took' => 100,
                'errors' => false,
                'items' => [
                    [
                        'index' => [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_version' => 1,
                            'result' => 'created',
                            '_shards' => [],
                            'status' => 201,
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $store->add([new VectorDocument(Uuid::v7(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreThrowsExceptionWhenBulkIndexingFails()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'errors' => true,
                'took' => 200,
                'items' => [
                    [
                        'index' => [
                            '_index' => 'foo',
                            '_id' => 'a',
                            '_version' => 1,
                            'result' => 'created',
                            'status' => 201,
                        ],
                    ],
                    [
                        'index' => [
                            '_index' => 'foo',
                            '_id' => 'b',
                            'status' => 400,
                            'error' => [
                                'type' => 'document_parsing_exception',
                                'reason' => '[1:21] failed to parse: The [dense_vector] field [_vectors] in doc [document with id \'b\'] has a different number of dimensions [2] than defined in the mapping [3]',
                                'caused_by' => [
                                    'type' => 'illegal_argument_exception',
                                    'reason' => 'The [dense_vector] field [_vectors] in doc [document with id \'b\'] has a different number of dimensions [2] than defined in the mapping [3]',
                                ],
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to process 1 of 2 bulk operation(s) on the "foo" index, first error for document "b": [document_parsing_exception] [1:21] failed to parse: The [dense_vector] field [_vectors] in doc [document with id \'b\'] has a different number of dimensions [2] than defined in the mapping [3] (caused by [illegal_argument_exception] The [dense_vector] field [_vectors] in doc [document with id \'b\'] has a different number of dimensions [2] than defined in the mapping [3])');

        $store->add([
            new VectorDocument('a', new Vector([0.1, 0.2, 0.3])),
            new VectorDocument('b', new Vector([0.1, 0.2])),
        ]);
    }

    public function testStoreCanRemove()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'errors' => false,
                'took' => 2,
                'items' => [
                    [
                        'delete' => [
                            '_index' => 'foo',
                            '_id' => 'a',
                            '_version' => 2,
                            'result' => 'deleted',
                            'status' => 200,
                        ],
                    ],
                    [
                        'delete' => [
                            '_index' => 'foo',
                            '_id' => 'b',
                            '_version' => 1,
                            'result' => 'not_found',
                            'status' => 404,
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $store->remove(['a', 'b']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreThrowsExceptionWhenBulkRemovalFails()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'errors' => true,
                'took' => 0,
                'items' => [
                    [
                        'delete' => [
                            '_index' => 'foo',
                            '_id' => 'a',
                            'status' => 403,
                            'error' => [
                                'type' => 'cluster_block_exception',
                                'reason' => 'index [foo] blocked by: [FORBIDDEN/8/index write (api)];',
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to process 1 of 1 bulk operation(s) on the "foo" index, first error for document "a": [cluster_block_exception] index [foo] blocked by: [FORBIDDEN/8/index write (api)];');

        $store->remove('a');
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'took' => 100,
                'errors' => false,
                'hits' => [
                    'total' => [
                        'value' => 1,
                        'relation' => 'eq',
                    ],
                    'hits' => [
                        [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_score' => 1.4363918,
                            '_source' => [
                                '_vectors' => [0.1, 0.2, 0.3],
                                'metadata' => json_encode([
                                    'foo' => 'bar',
                                ]),
                            ],
                        ],
                        [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_score' => 1.3363918,
                            '_source' => [
                                '_vectors' => [0.1, 0.4, 0.3],
                                'metadata' => json_encode([
                                    'foo' => 'bar',
                                ]),
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');
        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(2, iterator_to_array($results));
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanClear()
    {
        $requestedMethod = null;
        $requestedUrl = null;
        $requestedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$requestedBody): JsonMockResponse {
            $requestedMethod = $method;
            $requestedUrl = $url;
            $requestedBody = $options['body'];

            return new JsonMockResponse([
                'took' => 10,
                'timed_out' => false,
                'deleted' => 2,
            ], [
                'http_code' => 200,
            ]);
        });

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9200/'), 'foo');
        $store->clear();

        $this->assertSame('POST', $requestedMethod);
        $this->assertSame('http://127.0.0.1:9200/foo/_delete_by_query?refresh=true&conflicts=proceed', $requestedUrl);
        $this->assertSame('{"query":{"match_all":{}}}', $requestedBody);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'test-index');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'test-index');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'test-index');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'count' => 42,
                '_shards' => [
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'foo');

        $this->assertSame(42, $store->count());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCountReturnsZeroOnMalformedResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['count' => '42']),
        ]);

        $store = new Store($httpClient, 'foo');

        $this->assertSame(0, $store->count());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideInvalidQueryOptions')]
    public function testQueryRejectsNonIntegerOptions(array $options, string $expectedMessage)
    {
        $httpClient = new MockHttpClient();
        $store = new Store($httpClient, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), $options));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidQueryOptions(): iterable
    {
        yield 'k as string' => [['k' => '10'], 'The "k" option must be an integer.'];
        yield 'num_candidates as float' => [['num_candidates' => 1.5], 'The "num_candidates" option must be an integer.'];
    }

    /**
     * @param array<string, mixed>     $response
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideMalformedSearchResponses')]
    public function testQueryThrowsOnMalformedSearchResponse(array $response, string $expectedException, string $expectedMessage)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($response),
        ]);
        $store = new Store($httpClient, 'foo');

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<\Throwable>, string}>
     */
    public static function provideMalformedSearchResponses(): iterable
    {
        $id = '0199b6a5-4c3b-7d7e-9f6a-4b8f5b0f7a1e';
        $source = ['_vectors' => [0.1, 0.2, 0.3], 'metadata' => '{"foo":"bar"}'];

        yield 'missing hits' => [[], RuntimeException::class, 'The Elasticsearch search response is malformed.'];
        yield 'missing inner hits' => [['hits' => []], RuntimeException::class, 'The Elasticsearch search response does not contain a result set.'];
        yield 'invalid hit' => [['hits' => ['hits' => ['foo']]], RuntimeException::class, 'The Elasticsearch search response contains an invalid hit.'];
        yield 'missing id' => [['hits' => ['hits' => [['_source' => $source]]]], InvalidArgumentException::class, 'Missing "_id" field in the document data.'];
        yield 'non-string id' => [['hits' => ['hits' => [['_id' => 42, '_source' => $source]]]], InvalidArgumentException::class, 'The document "_id" field must be a string.'];
        yield 'missing source' => [['hits' => ['hits' => [['_id' => $id]]]], InvalidArgumentException::class, 'Missing "_source" field in the document data.'];
        yield 'non-array vector' => [['hits' => ['hits' => [['_id' => $id, '_source' => ['_vectors' => 'foo', 'metadata' => '{}']]]]], InvalidArgumentException::class, 'The document vector must be an array of numbers.'];
        yield 'non-numeric vector component' => [['hits' => ['hits' => [['_id' => $id, '_source' => ['_vectors' => [0.1, 'foo'], 'metadata' => '{}']]]]], InvalidArgumentException::class, 'The document vector must contain only numbers.'];
        yield 'missing metadata' => [['hits' => ['hits' => [['_id' => $id, '_source' => ['_vectors' => [0.1]]]]]], InvalidArgumentException::class, 'The document metadata must be a JSON encoded string.'];
        yield 'metadata not a JSON object' => [['hits' => ['hits' => [['_id' => $id, '_source' => ['_vectors' => [0.1], 'metadata' => '"foo"']]]]], InvalidArgumentException::class, 'The document metadata is not a valid JSON object.'];
        yield 'metadata with integer keys' => [['hits' => ['hits' => [['_id' => $id, '_source' => ['_vectors' => [0.1], 'metadata' => '["foo"]']]]]], InvalidArgumentException::class, 'The document metadata must be keyed by strings.'];
        yield 'non-numeric score' => [['hits' => ['hits' => [['_id' => $id, '_score' => 'high', '_source' => $source]]]], InvalidArgumentException::class, 'The document "_score" field must be a number.'];
    }

    public function testQueryReturnsNullVectorWhenVectorsFieldIsMissing()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['hits' => ['hits' => [[
                '_id' => '0199b6a5-4c3b-7d7e-9f6a-4b8f5b0f7a1e',
                '_score' => 1,
                '_source' => ['metadata' => '{"foo":"bar"}'],
            ]]]]),
        ]);
        $store = new Store($httpClient, 'foo');

        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(NullVector::class, $documents[0]->getVector());
        $this->assertSame(1.0, $documents[0]->getScore());
        $this->assertSame('bar', $documents[0]->getMetadata()['foo']);
    }
}
