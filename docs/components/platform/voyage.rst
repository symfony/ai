Voyage AI
=========

Voyage AI offers a number of models for embedding text, contextualized chunks, interleaved multimodal data, and reranking.
The bundle currently supports text embedding and multimodal embedding.

For comprehensive information about Voyage AI, see the `Voyage AI API reference`_

Setup
-----

Authentication
~~~~~~~~~~~~~~

Voyage AI requires an API key, which you can set up in `Voyage AI dashboard`_.

Usage
-----

Basic text embedding usage example::

    use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory;

    $platform = PlatformFactory::create($_ENV['VOYAGE_API_KEY'], $httpClient);

    $result = $platform->invoke('voyage-3', <<<TEXT
        Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and
        rivers. The people of Japan were very kind and hardworking. They loved their country very much and took care of
        it. The country was very peaceful and prosperous. The people lived happily ever after.
        TEXT);

    echo $result->getContent();

Voyage AI supports text, base64 image data, and image URLs in its multimodal embedding model. It also allows for
multiple data types per vector embedding. To do this, wrap the data in a `Collection` as shown in the example below.

Basic multimodal embedding usage example::

    use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Collection;
    use Symfony\AI\Platform\Message\Content\ImageUrl;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = PlatformFactory::create($_ENV['VOYAGE_API_KEY'], $httpClient);

    $result = $platform->invoke(
        'voyage-multimodal-3',
        new ImageUrl('https://example.com/image1.jpg'),
        new Collection(new Text('Hello, world!'), new ImageUrl('https://example.com/image2.jpg')
    );

    echo $result->getContent();


Examples
--------

See the ``examples/voyage/`` directory for complete working examples:

* ``text-embeddings.php`` - Basic text embedding example
* ``multiple-text-embeddings.php`` - Embedding multiple text values
* ``multimodal-embeddings.php`` - Embedding multimodal data (single and multiple values)

.. _Voyage AI API reference: https://docs.voyageai.com/reference/embeddings-api
.. _Voyage AI dashboard: https://dashboard.voyageai.com/organization/api-keys
