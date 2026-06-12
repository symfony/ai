.. card:
    title: Multi-Agent Orchestration
    description: Route questions to specialist agents with a central orchestrator.
    icon: network
    components: Agent

Multi-Agent Orchestration
=========================

Sometimes a single AI agent is not enough. You may need specialists for different domains, with
an orchestrator that routes questions to the right expert. In this guide you will build a
multi-agent system where a central orchestrator delegates tasks to specialist agents based on
the content of the user's question.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component

Step 1: Install Packages
------------------------

Install the Platform and Agent components via Composer::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Create Specialist Agents
--------------------------------

Each specialist agent is a regular :class:`Symfony\\AI\\Agent\\Agent` with a
:class:`Symfony\\AI\\Agent\\InputProcessor\\SystemPromptInputProcessor` that defines its area of
expertise. Give each agent a descriptive ``name`` so the orchestrator can identify it::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;

    $technical = new Agent(
        $platform,
        'gpt-5-mini',
        [new SystemPromptInputProcessor(
            'You are a technical support specialist. Help users resolve bugs and errors.',
        )],
        name: 'technical',
    );

    $billing = new Agent(
        $platform,
        'gpt-5-mini',
        [new SystemPromptInputProcessor(
            'You are a billing specialist. Help users with invoices and payments.',
        )],
        name: 'billing',
    );

Step 3: Create an Orchestrator Agent
------------------------------------

The orchestrator is another agent whose system prompt instructs it to analyze user questions and
decide which specialist should handle them. It does not need a name since it acts as the entry
point::

    $orchestrator = new Agent(
        $platform,
        'gpt-5-mini',
        [new SystemPromptInputProcessor(
            'You are an agent orchestrator that routes user questions to specialized agents.',
        )],
    );

Step 4: Configure Handoffs
--------------------------

A :class:`Symfony\\AI\\Agent\\MultiAgent\\Handoff` defines when a question should be routed to a
specific agent. The ``when`` parameter accepts an array of keywords that trigger the routing.
When the orchestrator detects these keywords in the user's question, it delegates to the matching
agent::

    use Symfony\AI\Agent\MultiAgent\Handoff;

    $handoffs = [
        new Handoff(
            to: $technical,
            when: ['bug', 'error', 'exception', 'technical'],
        ),
        new Handoff(
            to: $billing,
            when: ['invoice', 'payment', 'billing', 'subscription'],
        ),
    ];

Step 5: Build the MultiAgent
----------------------------

The :class:`Symfony\\AI\\Agent\\MultiAgent\\MultiAgent` ties everything together. It takes the
orchestrator, an array of handoffs, and a fallback agent that handles questions that do not match
any specialist::

    use Symfony\AI\Agent\MultiAgent\MultiAgent;

    $fallback = new Agent(
        $platform,
        'gpt-5-mini',
        [new SystemPromptInputProcessor(
            'You are a general assistant. Help users with any non-specialized questions.',
        )],
        name: 'fallback',
    );

    $multiAgent = new MultiAgent(
        orchestrator: $orchestrator,
        handoffs: $handoffs,
        fallback: $fallback,
    );

Step 6: Route Questions Automatically
-------------------------------------

Call the multi-agent with a ``MessageBag`` just like a regular agent. The orchestrator analyzes
the question and routes it to the appropriate specialist automatically::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Technical question - routed to the technical agent
    $messages = new MessageBag(
        Message::ofUser('I get a "Call to undefined method" error in my controller.'),
    );
    $result = $multiAgent->call($messages);
    echo $result->getContent();

    // General question - routed to the fallback agent
    $messages = new MessageBag(
        Message::ofUser('Can you recommend a good pasta recipe?'),
    );
    $result = $multiAgent->call($messages);
    echo $result->getContent();

.. tip::

    You can add as many specialist agents as you need. Each handoff is evaluated independently,
    so the orchestrator can route to any number of domains. For debugging, pass a PSR-3 logger to
    the ``MultiAgent`` constructor to see which agent handles each request.

Learn More
----------

* :doc:`../components/agent` - Processors, memory, and advanced agent patterns
* :doc:`../bundles/ai-bundle` - Automatic wiring in Symfony applications
