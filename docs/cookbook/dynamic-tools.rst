Dynamic Toolbox for Flexible Tools
==================================

This guide will lead you through the creation of a dynamic Toolbox for Symfony AI.
A dynamic Toolbox allows you not only to add or remove tools at runtime, but also to
customize tool names and descriptions.

Prerequisites

* Symfony AI Platform component
* Symfony AI Agent component
* A language model supporting tools (e.g., gpt-5-mini)

Example Use Cases
-----------------

The example use-cases assume that you are working with the Symfony AI demo application, where an agent named
`blog` is already defined with a set of tools.

Requirement: Set Up Dynamic Toolbox Class
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

First, create a class that implements the `ToolboxInterface` and, in its constructor, accepts
another `ToolboxInterface` instance to delegate calls to the original toolbox. This implements the decorator
pattern.


::

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

By utilizing the `AsDecorator` attribute, this class will automatically decorate the existing toolbox
for the `blog` agent, and the `AutowireDecorated` attribute will inject the original toolbox instance to
ensure that existing functionality is preserved and does not need to be reimplemented.

Case 1: Customizing Tools at Runtime
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To change a tool description dynamically, override the `getTools` method in the
`DynamicToolbox` class. Here is an example of how to modify the description of a specific tool.


Let's assume that the existing tool `similarity_search` should not have a general-purpose description,
but instead a description that blocks general questions unless someone asks very politely, in which case it
allows use of the tool.


::

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


With this implementation, whenever the `similarity_search` tool is requested, it will have the new
description that enforces the search term argument to include the word "please". For example,
a question like "Find articles about Symfony" would become "articles symfony please".

Generally, this approach to customizing tools can be utilized to let users experiment with descriptions
to optimize for their use case or minimize the tokens used for complex tools.

Case 2: Removing a Tool
~~~~~~~~~~~~~~~~~~~~~~~


To remove a tool dynamically, for example due to missing feature toggles, you can filter out the tool
in the `getTools` method. In the following example, we simulate a feature toggle that is disabled, so
the clock tool must not be available to the agent registered for the blog toolbox by default.


::

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


With this, and utilizing the blog example in the Symfony AI demo application, the agent will not be able
to tell the date or time. Only if the `toggleClockFeature` is set to `true` will the agent answer with the
current date and time again.

Case 3: Adding a Tool
~~~~~~~~~~~~~~~~~~~~~


To add a new tool dynamically, instantiate a new `Tool` object and append it to the list of tools
returned by the `getTools` method. In the following example, we add a simple echo tool that returns whatever
input it receives. Notably, this example will also intercept the requested tool execution and respond directly
with an uppercased version of the input.


::

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


With this implementation, the `echo` tool will be available to the agent alongside the existing tools.
You can test this by using the blog example again and explicitly asking the agent to utilize the `echo` tool.


Example:


    User: "What does the echo say?"

    Blog Agent: "The echo says: 'WHAT DOES THE ECHO SAY?' If you have any other questions
    or need further assistance, feel free to ask!"
