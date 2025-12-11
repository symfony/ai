Building a Chatbot with Memory
==============================

This guide demonstrates how to build a chatbot that remembers context across conversations
using Symfony AI's memory management features.

Overview
--------

Memory providers allow your agents to access conversation history and user-specific information,
enabling more personalized and context-aware responses. In this example, we'll build a personal
trainer chatbot that remembers facts about the user.

Prerequisites
-------------

* Symfony AI Agent component installed
* OpenAI API key (or any other supported platform)
* Basic understanding of Symfony AI concepts

Implementation
--------------

The complete implementation consists of three main parts:

1. Creating a memory provider with user facts
2. Configuring the agent with memory support
3. Processing user messages with context awareness

Complete Example
~~~~~~~~~~~~~~~~

See the complete example: `static.php <https://github.com/symfony/ai/blob/main/examples/memory/static.php>`_

Key Components
--------------

Memory Provider
~~~~~~~~~~~~~~~

The :class:`Symfony\\AI\\Agent\\Memory\\StaticMemoryProvider` stores fixed information that should be consistently available
to the agent::

    use Symfony\AI\Agent\Memory\StaticMemoryProvider;

    $personalFacts = new StaticMemoryProvider(
        'My name is Wilhelm Tell',
        'I wish to be a swiss national hero',
        'I am struggling with hitting apples but want to be professional with the bow and arrow',
    );

This information is automatically injected into the system prompt, providing the agent with
context about the user without cluttering the conversation messages.

Memory Input Processor
~~~~~~~~~~~~~~~~~~~~~~

The :class:`Symfony\\AI\\Agent\\Memory\\MemoryInputProcessor` handles the injection of memory content into the agent's context::

    use Symfony\AI\Agent\Memory\MemoryInputProcessor;

    $memoryProcessor = new MemoryInputProcessor($personalFacts);

This processor works alongside other input processors like :class:`Symfony\\AI\\Agent\\InputProcessor\\SystemPromptInputProcessor`
to build a complete context for the agent.

Agent Configuration
~~~~~~~~~~~~~~~~~~~

The agent is configured with both system prompt and memory processors::

    use Symfony\AI\Agent\Agent;

    $agent = new Agent(
        $platform,
        'gpt-4o-mini',
        [$systemPromptProcessor, $memoryProcessor]
    );

Processors are applied in order, allowing you to build up the context progressively.

How It Works
------------

1. **Memory Loading**: When a user message is submitted, the ``MemoryInputProcessor`` loads
   relevant facts from the memory provider.

2. **Context Injection**: The memory content is prepended to the system prompt, giving the
   agent access to user-specific information.

3. **Response Generation**: The agent generates a personalized response based on both the
   current message and the remembered context.

4. **Conversation Flow**: The memory persists across multiple calls, enabling continuous
   personalized interactions.

Use Dynamic Memory with Embeddings
----------------------------------

For more sophisticated scenarios, use :class:`Symfony\\AI\\Agent\\Memory\\EmbeddingProvider` to retrieve relevant context
based on semantic similarity::

    use Symfony\AI\Agent\Memory\EmbeddingProvider;

    $embeddingsMemory = new EmbeddingProvider(
        $platform,
        $embeddings,  // Your embeddings model
        $store        // Your vector store
    );

This approach allows the agent to recall specific pieces of information from a large
knowledge base based on the current conversation context.

Bundle Configuration
--------------------

When using the AI Bundle, you can configure memory directly in your configuration:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        agent:
            trainer:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Provide short, motivating claims'
                memory: 'You are a professional trainer with personalized advice'

For dynamic memory using a custom service:

.. code-block:: yaml

    ai:
        agent:
            trainer:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Provide short, motivating claims'
                memory:
                    service: 'app.user_memory_provider'

Best Practices
--------------

* **Keep Static Memory Concise**: Only include essential facts to avoid overwhelming the agent
* **Separate Concerns**: Use system prompt for behavior, memory for context
* **Update Dynamically**: For user-specific applications, update memory as you learn more about the user
* **Test Without Memory**: Verify your agent works correctly both with and without memory enabled
* **Monitor Token Usage**: Memory content consumes input tokens, so balance comprehensiveness with cost

Use Cases
---------

* **Personal Assistants**: Remember user preferences, habits, and history
* **Customer Support**: Recall previous interactions and customer details
* **Educational Tools**: Track student progress and learning style
* **Healthcare Applications**: Maintain patient history and treatment context

Related Documentation
---------------------

* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration reference
* :doc:`rag-implementation` - Retrieval Augmented Generation guide
