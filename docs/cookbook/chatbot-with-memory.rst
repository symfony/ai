.. card:
    title: Chatbot with Memory
    description: Give an agent memory of user facts for personalized conversations.
    icon: messages
    components: Agent

Building a Chatbot with Memory
==============================

Memory providers allow your agents to access conversation history and user-specific information,
enabling more personalized and context-aware responses. In this guide you will build a personal
trainer chatbot that remembers facts about the user across conversations.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* OpenAI API key (or any other supported platform)

Step 1: Install Packages
------------------------

Install the Platform and Agent components via Composer::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Create a Memory Provider
--------------------------------

The :class:`Symfony\\AI\\Agent\\Memory\\StaticMemoryProvider` stores fixed information that should
be consistently available to the agent::

    use Symfony\AI\Agent\Memory\StaticMemoryProvider;

    $personalFacts = new StaticMemoryProvider([
        'My name is Wilhelm Tell',
        'I wish to be a swiss national hero',
        'I am struggling with hitting apples but want to be professional with the bow and arrow',
    ]);

This information is automatically injected into the system prompt, providing the agent with
context about the user without cluttering the conversation messages.

Step 3: Add the Memory Processor
--------------------------------

The :class:`Symfony\\AI\\Agent\\Context\\Processor\\MemoryProcessor` handles the injection of memory
content into the agent's context::

    use Symfony\AI\Agent\Context\Processor\MemoryProcessor;

    $memoryProcessor = new MemoryProcessor([$personalFacts]);

This processor works alongside the agent's ``instruction`` to build a complete context for the agent.

Step 4: Configure the Agent
---------------------------

The agent is configured with the instruction and the memory processor. Context processors are applied
in order, allowing you to build up the context progressively::

    use Symfony\AI\Agent\Agent;

    $agent = new Agent(
        $platform,
        'gpt-4o-mini',
        [$memoryProcessor],
        instruction: 'You are a personal assistant.',
    );

When a user message is submitted, the ``MemoryProcessor`` loads relevant facts from the
memory provider and prepends them to the system prompt. The agent then generates a personalized
response based on both the current message and the remembered context. Because the memory
persists across calls, the conversation stays personalized over time.

Step 5: Recall Facts Semantically (Optional)
--------------------------------------------

To recall facts from a large knowledge base instead of a fixed list, swap the static provider for
:class:`Symfony\\AI\\Agent\\Memory\\EmbeddingProvider`, which retrieves relevant context by
semantic similarity::

    use Symfony\AI\Agent\Memory\EmbeddingProvider;

    $model = $platform->getModelCatalog()->getModel('text-embedding-3-small');
    $embeddingsMemory = new EmbeddingProvider($platform, $model, $store);

Pass it to the ``MemoryProcessor`` just like the static provider.

Using the AI Bundle?
--------------------

If you use the AI Bundle, configure memory declaratively on the agent:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        agent:
            trainer:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Provide short, motivating claims'
                memory: 'You are a professional trainer with personalized advice'

For a dynamic provider, point ``memory`` at a service instead. See :doc:`../bundles/ai-bundle`.

Best Practices
--------------

* **Keep Static Memory Concise**: Only include essential facts to avoid overwhelming the agent
* **Separate Concerns**: Use the system prompt for behavior, memory for context
* **Mind Token Usage**: Memory content consumes input tokens, so balance comprehensiveness with cost

Learn More
----------

* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration reference
* :doc:`rag-implementation` - Retrieval Augmented Generation guide
