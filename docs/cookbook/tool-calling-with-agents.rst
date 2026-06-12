.. card:
    title: Tool Calling with Agents
    description: Let AI agents call your PHP functions to fetch data or trigger actions.
    icon: tool
    components: Agent

Tool Calling with Agents
========================

Tool calling lets an AI agent invoke your PHP functions to fetch data or trigger actions.
In this guide you will create a custom tool, wire it into an agent, and let the LLM decide
when to call it — including streaming support.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component

Step 1: Install Packages
------------------------

You need Platform for the LLM connection and Agent for the toolbox and processor pipeline::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Create a Tool Class
---------------------------

Annotate a class with the :class:`Symfony\\AI\\Agent\\Toolbox\\Attribute\\AsTool` attribute to
register it. The agent reads the attribute name and description to tell the LLM what the tool
does. Parameter descriptions come from ``@param`` docblocks on the ``__invoke()`` method::

    namespace App\Tool;

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('weather', 'Fetches the current weather for a given city')]
    final class WeatherTool
    {
        /**
         * @param string $city  The name of the city to look up
         * @param string $units "metric" or "imperial"
         */
        public function __invoke(string $city, string $units = 'metric'): array
        {
            // Call a weather API, query a database, etc.
            return [
                'city' => $city,
                'temperature' => '22°C',
                'condition' => 'sunny',
            ];
        }
    }

.. tip::

    A tool can return more than a ``string`` — arrays, objects, scalars, and ``DateTimeInterface``
    are converted automatically by the :class:`Symfony\\AI\\Agent\\Toolbox\\ToolResultConverter`,
    so you rarely need to ``json_encode()`` yourself.

Step 3: Build the Agent
-----------------------

Pass your tool instances into a :class:`Symfony\\AI\\Agent\\Toolbox\\Toolbox`, wrap it in an
:class:`Symfony\\AI\\Agent\\Toolbox\\AgentProcessor`, and construct the
:class:`Symfony\\AI\\Agent\\Agent`. The processor acts as both an input and output processor::

    use App\Tool\WeatherTool;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\Component\HttpClient\HttpClient;

    $platform = PlatformFactory::create($apiKey, HttpClient::create());

    $toolbox = new Toolbox([new WeatherTool()]);
    $processor = new AgentProcessor($toolbox);
    $agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

Step 4: Let the Agent Call Tools
--------------------------------

Send a user message. The agent will automatically discover the available tools, decide whether
to call one, execute it, and incorporate the result into its response::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $messages = new MessageBag(
        Message::ofUser('What is the weather like in Paris right now?'),
    );

    $result = $agent->call($messages);

    echo $result->getContent();
    // "The current weather in Paris is 22°C and sunny."

Step 5: Use Built-In Tools
--------------------------

The Agent component ships ready-made tools — Wikipedia, YouTube, SimilaritySearch, Brave, and
SerpApi — each as a separate Composer package:

.. code-block:: terminal

    $ composer require symfony/ai-wikipedia-tool

Add it to the toolbox alongside your custom tools::

    use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;

    $toolbox = new Toolbox([
        new WeatherTool(),
        new Wikipedia(HttpClient::create()),
    ]);

See :doc:`../components/agent` for the full list of built-in tools and their configuration.

Step 6: Stream with Tool Calling
--------------------------------

Enable streaming with ``'stream' => true``. Tool calls are still handled transparently, and
``getContent()`` yields semantic *delta* objects — filter them by type. Reasoning models also
emit ``ThinkingDelta`` deltas when you pass the ``reasoning`` option::

    use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
    use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;

    $result = $agent->call($messages, [
        'stream' => true,
        'reasoning' => ['summary' => 'auto'],
    ]);

    foreach ($result->getContent() as $delta) {
        if ($delta instanceof ThinkingDelta) {
            echo $delta->getThinking();
        }
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

Once the stream is drained, token usage is available from the metadata as a
:class:`Symfony\\AI\\Platform\\TokenUsage\\TokenUsage` object::

    $usage = $result->getMetadata()->get('token_usage');

See :doc:`../components/platform` for the full list of delta types.

Learn More
----------

* :doc:`../components/agent` - Processors, memory, and advanced agent patterns
* :doc:`../components/platform` - Supported providers and models
* :doc:`../bundles/ai-bundle` - Auto-wiring tools as Symfony services
