Vertex AI
=========

Google Cloud Vertex AI is a machine learning platform that provides access to Google's Gemini models and other AI services.
The Symfony AI Platform component provides a bridge to interact with Vertex AI models.

For comprehensive information about Vertex AI, see the `Vertex AI documentation`_ and `Vertex AI API reference`_.

Installation
------------

To use Vertex AI with Symfony AI Platform, you need to install the platform component and set up Google Cloud authentication:

.. code-block:: terminal

    $ composer require symfony/ai-platform

Setup
-----

Authentication
~~~~~~~~~~~~~~

Vertex AI supports the following authentication methods:

1. Application Default Credentials (ADC)
........................................

Follow the `Google cloud authentication guide`_ to set up your credentials.

For ADC, install the Google Cloud SDK and authenticate:

.. code-block:: terminal

    $ gcloud auth application-default login

For detailed authentication setup, see `Setting up authentication for Vertex AI`_.

Configure your Google Cloud project and location:

.. code-block:: bash

    GOOGLE_CLOUD_PROJECT=your-project-id
    GOOGLE_CLOUD_LOCATION=us-central1


Basic usage example::

    use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
    use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create(
        $_ENV['GOOGLE_CLOUD_LOCATION'],
        $_ENV['GOOGLE_CLOUD_PROJECT'],
        httpClient: $httpClient
    );

    $messages = new MessageBag(
        Message::ofUser('Hello, how are you?')
    );

    $result = $platform->invoke('gemini-2.5-flash', $messages);
    echo $result->getContent();

2. Service Account Key
......................

Similar to the first approach, but instead of authenticating with the `gcloud` command, you provide the service account key directly using an environment variable:

.. code-block:: bash

    GOOGLE_APPLICATION_CREDENTIALS="/path/to/service-account-key.json"

3. API Key
..........

Similar to the first approach, but instead of authenticating with the `gcloud` command, you provide the API keys when creating the platform::

    $platform = PlatformFactory::create(
        $_ENV['GOOGLE_CLOUD_LOCATION'],
        $_ENV['GOOGLE_CLOUD_PROJECT'],
        apiKey: $_ENV['GOOGLE_CLOUD_VERTEX_API_KEY'],
    );

To get an API key, visit: `Vertex AI Studio (API keys)`_.

Model Availability by Location
------------------------------

.. note::

    Model availability varies by Google Cloud location. Not all models are available in all regions.

Common model availability:

* **us-central1**: Most comprehensive model availability, recommended for development
* **us-east1**: Good model availability
* **europe-west1**: Good model availability
* **global**: Limited model availability, some newer models may not be available

Troubleshooting Model Availability
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If you encounter an error like::

    Publisher Model `projects/your-project/locations/global/publishers/google/models/gemini-2.0-flash-lite` not found

This typically means:

1. The model is not available in your specified location
2. Try switching to a different location like ``us-central1``
3. Use an alternative model that's available in your location
4. Check the `Google Cloud Console for Vertex AI`_ for model availability in your region

Checking Model Availability
^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can check which models are available in your location using the Google Cloud Console or gcloud CLI::

    gcloud ai models list --region=us-central1

Location Configuration
----------------------

Configure your location in your environment file:

.. code-block:: bash

    # Recommended: Use a region with comprehensive model support
    GOOGLE_CLOUD_LOCATION=us-central1

    # Avoid: Global location has limited model availability
    # GOOGLE_CLOUD_LOCATION=global

Token Usage Tracking
--------------------

Track token usage with the TokenOutputProcessor::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Bridge\VertexAi\TokenOutputProcessor;

    $agent = new Agent(
        $platform,
        $model,
        outputProcessors: [new TokenOutputProcessor()],
        logger: $logger
    );

    $result = $agent->call($messages);
    $tokenUsage = $result->getMetadata()->get('token_usage');

    assert($tokenUsage instanceof TokenUsage);

    echo 'Prompt Tokens: ' . $tokenUsage->promptTokens . PHP_EOL;
    echo 'Completion Tokens: ' . $tokenUsage->completionTokens . PHP_EOL;
    echo 'Total Tokens: ' . $tokenUsage->totalTokens . PHP_EOL;

Server Tools
------------

Vertex AI provides built-in server tools. See :doc:`vertexai-server-tools` for detailed information about:

* URL Context
* Grounding with Google Search
* Code Execution

Examples
--------

See the ``examples/vertexai/`` directory for complete working examples:

* ``token-metadata.php`` - Token usage tracking
* ``toolcall.php`` - Using server tools
* ``server-tools.php`` - Advanced server tool usage

.. _Vertex AI documentation: https://cloud.google.com/vertex-ai/docs
.. _Vertex AI API reference: https://cloud.google.com/vertex-ai/docs/reference
.. _Google cloud authentication guide: https://cloud.google.com/docs/authentication
.. _Setting up authentication for Vertex AI: https://cloud.google.com/vertex-ai/docs/authentication
.. _Google Cloud Console for Vertex AI: https://console.cloud.google.com/vertex-ai
.. _Vertex AI Studio (API keys): https://console.cloud.google.com/vertex-ai/studio/settings/api-keys
