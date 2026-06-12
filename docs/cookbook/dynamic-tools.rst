.. card:
    title: Dynamic Tools
    description: Add, remove, and rename agent tools at runtime with a dynamic toolbox.
    icon: adjustments
    components: Agent

Dynamic Toolbox for Flexible Tools
==================================

This guide leads you through creating a dynamic Toolbox for Symfony AI. A dynamic Toolbox lets
you add or remove tools at runtime and customize tool names and descriptions. The examples assume
you are working with the Symfony AI demo application, where an agent named ``blog`` is already
defined with a set of tools.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* A language model supporting tools (e.g., gpt-5-mini)

Step 1: Install Packages
------------------------

Install the Agent component, which provides the toolbox::

    composer require symfony/ai-agent

Step 2: Create the Dynamic Toolbox
----------------------------------

Create a class that implements :class:`Symfony\\AI\\Agent\\Toolbox\\ToolboxInterface` and accepts
another ``ToolboxInterface`` instance in its constructor to delegate calls to the original toolbox.
This implements the decorator pattern::

    namespace App;

    use Symfony\AI\Agent\Toolbox\ToolboxInterface;
    use Symfony\AI\Agent\Toolbox\ToolResult;
    use Symfony\AI\Platform\Result\ToolCall;
    use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
    use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

    #[AsDecorator('ai.toolbox.blog')]
    class DynamicToolbox implements ToolboxInterface
    {
        private ToolboxInterface $innerToolbox;

        public function __construct(#[AutowireDecorated] ToolboxInterface $innerToolbox)
        {
            $this->innerToolbox = $innerToolbox;
        }

        public function getTools(): array
        {
            return $this->innerToolbox->getTools();
        }

        public function execute(ToolCall $toolCall): ToolResult
        {
            return $this->innerToolbox->execute($toolCall);
        }
    }

The ``AsDecorator`` attribute makes this class decorate the existing toolbox for the ``blog`` agent,
and ``AutowireDecorated`` injects the original toolbox instance so existing functionality is
preserved.

Step 3: Customize a Tool at Runtime
-----------------------------------

To change a tool description dynamically, override the ``getTools()`` method. Assume the existing
``similarity_search`` tool should enforce a more specific search term::

    use Symfony\AI\Platform\Tool\Tool;

    // ...existing code...
    public function getTools(): array
    {
        $tools = $this->innerToolbox->getTools();
        foreach ($tools as $index => $tool) {
            if ($tool->getName() !== 'similarity_search') {
                continue;
            }

            $tools[$index] = new Tool(
                $tool->getReference(),
                $tool->getName(),
                'Similarity search, but always add the word "please" to the searchTerm.',
                $tool->getParameters()
            );
        }

        return $tools;
    }

Whenever the ``similarity_search`` tool is requested, it now carries the new description. This
approach lets users experiment with descriptions to optimize for their use case or minimize the
tokens used for complex tools.

Step 4: Remove a Tool
---------------------

To remove a tool dynamically, for example because of a disabled feature toggle, filter it out in
``getTools()``::

    public function getTools(): array
    {
        $tools = $this->innerToolbox->getTools();

        $toggleClockFeature = false; // Simulate real feature toggle check
        if ($toggleClockFeature === false) {
            $tools = array_filter(
                $tools,
                static fn (Tool $tool) => $tool->getName() !== 'clock'
            );
        }

        return $tools;
    }

With this, the agent can no longer tell the date or time. Only when ``toggleClockFeature`` is
``true`` will the agent answer with the current date and time again.

Step 5: Add a Tool
------------------

To add a new tool dynamically, instantiate a ``Tool`` object and append it to the list returned by
``getTools()``. This example adds an ``echo`` tool and intercepts its execution to return an
uppercased version of the input::

    use Symfony\AI\Platform\Tool\ExecutionReference;
    use Symfony\AI\Platform\Tool\Tool;

    // ...existing code...
    public function getTools(): array
    {
        $tools = $this->innerToolbox->getTools();

        $tools[] = new Tool(
            new ExecutionReference(self::class), // Required, not used
            'echo',
            'Echoes the input provided to it.',
            [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'string used for similarity search',
                    ],
                ],
                'required' => ['input'],
                'additionalProperties' => false,
            ],
        );

        return $tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        if ($toolCall->getName() === 'echo') {
            $args = $toolCall->getArguments();
            return new ToolResult($toolCall, \strtoupper($args['input']));
        }

        return $this->innerToolbox->execute($toolCall);
    }

The ``echo`` tool is now available to the agent alongside the existing tools. Test it with the blog
example by asking the agent to use the ``echo`` tool:

.. code-block:: text

    User: "What does the echo say?"

    Blog Agent: "The echo says: 'WHAT DOES THE ECHO SAY?'"

Learn More
----------

* :doc:`tool-calling-with-agents` - Build and register custom tools
* :doc:`../components/agent` - Agent component documentation
