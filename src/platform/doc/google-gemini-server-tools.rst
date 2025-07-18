Google Gemini Server Tools
==========================

Server tools are built-in capabilities provided by Google Gemini that allow the model to perform specific actions without requiring custom tool implementations. These tools run on Google's servers and provide access to external data and execution environments.

Overview
--------

Google Gemini provides several server-side tools that can be enabled when calling the model:

- **URL Context** - Fetches and analyzes content from URLs
- **Google Search** - Performs web searches using Google
- **Code Execution** - Executes code in a sandboxed environment

Available Server Tools
----------------------

**URL Context**

The URL Context tool allows Gemini to fetch and analyze content from web pages. This is useful for:

- Analyzing current web content
- Extracting information from specific pages
- Understanding context from external sources

::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'server_tools' => [
            'url_context' => true
        ]
    ]);

    $messages = new MessageBag(
        Message::ofUser('What was the 12 month Euribor rate a week ago based on https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/')
    );

    $result = $platform->invoke($model, $messages);


**Google Search**

The Google Search tool enables the model to search the web and incorporate search results into its results::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'server_tools' => [
            'google_search' => true
        ]
    ]);

    $messages = new MessageBag(
        Message::ofUser('What are the latest developments in quantum computing?')
    );

    $result = $platform->invoke($model, $messages);

**Code Execution**

The Code Execution tool provides a sandboxed environment for running code::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'server_tools' => [
            'code_execution' => true
        ]
    ]);

    $messages = new MessageBag(
        Message::ofUser('Calculate the factorial of 20 and show me the code')
    );

    $result = $platform->invoke($model, $messages);


## Using Multiple Server Tools

You can enable multiple server tools simultaneously::

    $model = new Gemini('gemini-2.5-pro-preview-03-25', [
        'server_tools' => [
            'url_context' => true,
            'google_search' => true,
            'code_execution' => true
        ]
    ]);

Example
-------

See `examples/google/server-tools.php`_ for a complete working example.

Limitations
-----------

- API key must have appropriate permissions
- Server tools may have usage quotas
- Response times may vary based on the complexity of server tool operations
- Not all Gemini model versions support all server tools

.. _`examples/google/server-tools.php`: https://github.com/symfony/ai/blob/main/examples/google/server-tools.php
