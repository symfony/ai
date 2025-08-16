Vertex AI Server Tools
======================

Server tools in Vertex AI are built-in capabilities provided by Google's Gemini models that allow the model to perform
specific actions without requiring custom tool implementations.
These tools run on Google's infrastructure and provide access to external data sources and execution environments.

Overview
--------

Vertex AI provides several server-side tools that can be enabled when calling the model:

- **URL Context** - Fetches and analyzes content from URLs
- **Grounding** - Lets a model output connect to verifiable sources of information.
- **Code Execution** - Executes code in a sandboxed environment.

Available Server Tools
----------------------

**URL Context**

The URL Context tool allows the model to fetch and analyze content from specified web pages. This is useful for:

- Analyzing current web content
- Extracting structured information from pages
- Providing context from external documents
- https://cloud.google.com/vertex-ai/generative-ai/docs/url-context

::

    $model = new VertexAi\Gemini\Model('gemini-2.5-pro');

    $content = file_get_contents('https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/');
    $messages = new MessageBag(
        Message::ofUser("Based on the following page content, what was the 12-month Euribor rate a week ago?\n\n".$content)
    );

    $result = $platform->invoke($model, $messages);


**Grounding with Google Search**
The Grounding tool allows the model to connect its responses to verifiable sources of information, enhancing the reliability
of its outputs. More at https://cloud.google.com/vertex-ai/generative-ai/docs/grounding/overview
Below is an example of grounding a model's responses using Google Search, which uses publicly-available web data.

* Grounding with Google Search *

Ground a model's responses using Google Search, which uses publicly-available web data.
More info can be found at https://cloud.google.com/vertex-ai/generative-ai/docs/grounding/overview

::

    $model = new VertexAi\Gemini\Model('gemini-2.5-pro', [
        'tools' => [[
            'googleSearch' => new \stdClass()
        ]]
    ]);

    $messages = new MessageBag(
        Message::ofUser('What are the top breakthroughs in AI in 2025 so far?')
    );

    $result = $platform->invoke($model, $messages);

**Code Execution**

Executes code in a Google-managed sandbox environment and returns both the code and its output.
More info can be found at https://cloud.google.com/vertex-ai/generative-ai/docs/multimodal/code-execution

::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'tools' => [[
            'codeExecution' => new \stdClass()
        ]]
    ]);

    $messages = new MessageBag(
        Message::ofUser('Write Python code to calculate the 50th Fibonacci number and run it')
    );

    $result = $platform->invoke($model, $messages);


Using Multiple Server Tools
---------------------------

You can enable multiple tools in a single request::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'tools' => [[
            'googleSearch' => new \stdClass(),
            'codeExecution' => new \stdClass()
        ]]
    ]);

Example
-------

See `examples/vertexai/server-tools.php`_ for a complete working example.

Limitations
-----------

- **Model support:** Not all Vertex AI Gemini model versions support all server tools — check the Vertex AI documentation for the chosen model ID.
- **Permissions:** The Vertex AI service account and the models must have the required permissions or scopes to use server tools.
- **Quotas:** Server tools are subject to usage limits and quotas configured in your Google Cloud project.
- **Latency:** Using multiple tools or fetching from slow external sources can increase response time.
- **Regional availability:** Ensure you are using a location that supports the selected model and tools.

.. _`examples/vertexai/server-tools.php`: https://github.com/symfony/ai/blob/main/examples/vertexai/server-tools.php
