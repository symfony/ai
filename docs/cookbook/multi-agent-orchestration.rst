Multi-Agent Orchestration
=========================

This guide explains how to build multi-agent systems with Symfony AI, where
a central orchestrator routes user requests to specialized agents based on
the content of the message.

What is Multi-Agent Orchestration?
-----------------------------------

A single monolithic agent quickly becomes hard to maintain as capabilities
grow. Multi-agent systems solve this by splitting responsibilities:

* **Orchestrator** – analyses each request and decides which specialist handles it
* **Specialized agents** – each expert in a narrow domain (support, billing, research, …)
* **Fallback agent** – catches everything that does not match a specialist

Symfony AI ships two complementary patterns for this:

1. **MultiAgent** – a ready-made orchestrator-with-handoffs component
2. **Subagent tool** – wraps any agent as a callable tool so a parent agent
   can invoke it just like any other tool

Prerequisites
-------------

* Symfony AI Agent component (``symfony/ai-agent``)
* An API key for at least one supported platform
* Basic understanding of :doc:`../components/agent`

Pattern 1: MultiAgent with Handoffs
------------------------------------

The :class:`Symfony\\AI\\Agent\\MultiAgent\\MultiAgent` class implements
:class:`Symfony\\AI\\Agent\\AgentInterface`, so it can replace any regular
agent transparently.

Minimal Example
~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ composer require symfony/ai-agent

::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
    use Symfony\AI\Agent\MultiAgent\Handoff;
    use Symfony\AI\Agent\MultiAgent\MultiAgent;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    // Structured output is required for the orchestrator's routing decision
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new PlatformSubscriber());

    $platform = PlatformFactory::create(
        apiKey: $_ENV['OPENAI_API_KEY'],
        eventDispatcher: $dispatcher,
    );

    // The orchestrator only routes – keep it cheap and fast
    $orchestrator = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SystemPromptInputProcessor(
            'You are an intelligent agent orchestrator that routes user questions to specialized agents.'
        )],
    );

    // Specialist: technical support
    $technical = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SystemPromptInputProcessor('You are a technical support specialist. Help users resolve bugs, errors, and technical problems.')],
        name: 'technical',
    );

    // Fallback for everything else
    $general = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SystemPromptInputProcessor('You are a helpful general assistant.')],
        name: 'general',
    );

    $multiAgent = new MultiAgent(
        orchestrator: $orchestrator,
        handoffs: [
            new Handoff(to: $technical, when: ['bug', 'error', 'exception', 'crash', 'technical']),
        ],
        fallback: $general,
    );

    $result = $multiAgent->call(new MessageBag(
        Message::ofUser('I get "Call to undefined method" in my PHP code, how do I fix it?')
    ));

    echo $result->getContent();

How It Works
~~~~~~~~~~~~

When ``call()`` is invoked, the ``MultiAgent``:

1. Extracts the user message text.
2. Builds a routing prompt listing all registered agents and their trigger
   keywords (``when``).
3. Asks the **orchestrator** to pick the best agent using structured output
   (a :class:`Symfony\\AI\\Agent\\MultiAgent\\Handoff\\Decision` object).
4. Delegates the **original** user message to the selected specialist, or to
   the fallback if no specialist matches.

.. note::

    The orchestrator uses structured output internally, so a
    :class:`Symfony\\AI\\Platform\\StructuredOutput\\PlatformSubscriber` must
    be registered with the platform's event dispatcher.

Handoff Configuration
~~~~~~~~~~~~~~~~~~~~~

Each :class:`Symfony\\AI\\Agent\\MultiAgent\\Handoff` requires:

* ``to`` – the target :class:`Symfony\\AI\\Agent\\AgentInterface` instance
* ``when`` – a non-empty list of keywords/phrases that trigger this handoff

The orchestrator converts these into plain English descriptions that are
included in its routing prompt, so the more descriptive the keywords, the
better the routing decisions::

    new Handoff(
        to: $billingAgent,
        when: ['invoice', 'payment', 'subscription', 'refund', 'billing'],
    ),
    new Handoff(
        to: $securityAgent,
        when: ['password', 'login', 'account locked', '2fa', 'security'],
    ),

Multiple Specialists
~~~~~~~~~~~~~~~~~~~~

::

    $multiAgent = new MultiAgent(
        orchestrator: $orchestrator,
        handoffs: [
            new Handoff(to: $technical,  when: ['bug', 'error', 'technical']),
            new Handoff(to: $billing,    when: ['invoice', 'payment', 'refund']),
            new Handoff(to: $onboarding, when: ['getting started', 'install', 'setup']),
        ],
        fallback: $general,
    );

Agent Names
~~~~~~~~~~~

Give each specialist a descriptive ``name`` via the ``name`` constructor
parameter of :class:`Symfony\\AI\\Agent\\Agent`. The ``MultiAgent`` uses this
name to match the orchestrator's routing decision to the correct agent::

    $technical = new Agent($platform, 'gpt-4o-mini', [...], name: 'technical_support');

Debug Logging
~~~~~~~~~~~~~

Pass a :class:`Psr\\Log\\LoggerInterface` to ``MultiAgent`` to trace routing
decisions at runtime::

    use Psr\Log\LoggerInterface;

    $multiAgent = new MultiAgent(
        orchestrator: $orchestrator,
        handoffs: [...],
        fallback: $general,
        logger: $logger, // logs agent selection and reasoning
    );

The logger emits ``debug``-level messages for every routing step.

Pattern 2: Subagent Tool
-------------------------

The :class:`Symfony\\AI\\Agent\\Toolbox\\Tool\\Subagent` class wraps an agent
as a tool. This is useful when the parent agent should decide *when* to invoke
a specialist based on the conversation context, rather than having a fixed
routing table::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Tool\Subagent;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;

    // A Wikipedia research agent
    $researchAgent = new Agent($platform, 'gpt-4o-mini', [
        new SystemPromptInputProcessor('You are a research specialist. Answer questions with facts from Wikipedia.'),
    ]);

    $subagent = new Subagent($researchAgent);

    $metadataFactory = (new MemoryToolFactory())
        ->addTool(
            class: $subagent,
            name: 'research',
            description: 'Researches a topic and returns a factual summary.',
        );

    $toolbox = new Toolbox($metadataFactory, [$subagent]);
    $processor = new AgentProcessor($toolbox);

    // Parent agent that can delegate to the research specialist
    $parent = new Agent($platform, 'gpt-4o', [$processor], [$processor]);
    $result = $parent->call(new MessageBag(
        Message::ofUser('Summarise the history of PHP in two paragraphs.')
    ));

The ``Subagent`` wrapper also propagates sources from the inner agent, so
citations from a Wikipedia tool remain available on the outer result.

Choosing a Pattern
------------------

+----------------------------+---------------------------------------------------+
| Use **MultiAgent**         | Use **Subagent**                                  |
+============================+===================================================+
| Fixed routing table        | Dynamic tool-based delegation                     |
+----------------------------+---------------------------------------------------+
| Single entry point needed  | Parent agent decides when to delegate             |
+----------------------------+---------------------------------------------------+
| Specialist hidden from LLM | Specialist visible to LLM as a named tool         |
+----------------------------+---------------------------------------------------+
| Routing driven by keywords | Routing driven by full conversation context       |
+----------------------------+---------------------------------------------------+

Bundle Configuration
--------------------

When using :doc:`../bundles/ai-bundle`, agents are defined in YAML and can
reference each other by their service name:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

        agent:
            # Specialized agents
            technical_support:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'You are a technical support specialist.'

            research:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'You are a research specialist.'
                tools:
                    - 'Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia'

            # Parent agent that uses the research agent as a sub-agent tool
            assistant:
                model: 'gpt-4o'
                prompt:
                    text: 'You are a helpful assistant. Use the research tool for factual questions.'
                tools:
                    - agent: 'research'
                      name: 'research'
                      description: 'Researches a topic and returns a factual summary.'

Inject the agent services into your controllers or services via
autowiring using the ``#[Target]`` attribute::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\Component\DependencyInjection\Attribute\Target;

    class AssistantController
    {
        public function __construct(
            #[Target('ai.agent.assistant')]
            private AgentInterface $agent,
        ) {
        }
    }

Best Practices
--------------

* **Keep the orchestrator cheap.** Use a fast, small model for routing (e.g.
  ``gpt-4o-mini``) and reserve bigger models for the specialists that actually
  answer users.
* **Name your handoffs precisely.** Vague keywords like ``"help"`` will
  confuse the orchestrator. Prefer domain-specific terms.
* **Limit specialist scope.** A specialist with a narrow, well-defined purpose
  produces better answers. If a specialist drifts into general territory,
  restrict it in its system prompt.
* **Enable fault tolerance.** Wrap toolboxes in
  :class:`Symfony\\AI\\Agent\\Toolbox\\FaultTolerantToolbox` to prevent a
  single failed tool call from crashing the entire multi-agent pipeline.
* **Log routing decisions in development.** Pass a logger to ``MultiAgent``
  and set the log level to ``debug`` so you can verify that requests are
  routed to the intended specialist.

Complete Example
----------------

See the complete runnable example:
`multi-agent/orchestrator.php <https://github.com/symfony/ai/blob/main/examples/multi-agent/orchestrator.php>`_

Related Documentation
---------------------

* :doc:`../components/agent` – Agent component documentation
* :doc:`../bundles/ai-bundle` – AI Bundle configuration reference
* :doc:`streaming-responses` – Stream agent responses token by token
* :doc:`rag-implementation` – Augment agents with external knowledge
