.. card:
    title: Human in the Loop
    description: Require human approval before an agent executes sensitive tool calls.
    icon: user-check
    components: Agent

Human-in-the-Loop Tool Confirmation
===================================

When AI agents execute tools, some actions — like deleting files, sending emails, or modifying
data — should require human approval. This guide shows how to build a confirmation system using the
:class:`Symfony\\AI\\Agent\\Toolbox\\Event\\ToolCallRequested` event.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* Symfony EventDispatcher component

Step 1: Install Packages
------------------------

Install the Platform and Agent components together with the EventDispatcher::

    composer require symfony/ai-platform symfony/ai-agent symfony/event-dispatcher

Step 2: Confirm Every Tool Call
-------------------------------

The :class:`Symfony\\AI\\Agent\\Toolbox\\Toolbox` dispatches a
:class:`Symfony\\AI\\Agent\\Toolbox\\Event\\ToolCallRequested` event before each tool execution.
An event listener can inspect the tool call and either:

* **Allow it** — do nothing, the tool executes normally
* **Deny it** — call ``$event->deny($reason)`` to block execution and return the reason to the LLM
* **Replace it** — call ``$event->setResult($result)`` to skip execution and return a custom result

The simplest approach asks for confirmation on every tool call::

    use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ToolCallRequested::class, function (ToolCallRequested $event): void {
        $toolCall = $event->getToolCall();

        echo \sprintf(
            "Tool '%s' wants to execute with args: %s\nAllow? [y/N] ",
            $toolCall->getName(),
            json_encode($toolCall->getArguments())
        );

        $input = strtolower(trim(fgets(\STDIN)));

        if ('y' !== $input) {
            $event->deny('User denied tool execution.');
        }
    });

Pass this dispatcher to the :class:`Symfony\\AI\\Agent\\Toolbox\\Toolbox`::

    use Symfony\AI\Agent\Toolbox\Toolbox;

    $toolbox = new Toolbox($tools, eventDispatcher: $dispatcher);

Step 3: Add a Policy
--------------------

In practice, you don't want to confirm every single call. A policy decides which tools need
confirmation and which can run automatically::

    enum PolicyDecision
    {
        case Allow;
        case Deny;
        case AskUser;
    }

    interface PolicyInterface
    {
        public function decide(ToolCall $toolCall): PolicyDecision;
    }

A simple policy could auto-allow read operations based on tool name patterns::

    use Symfony\AI\Platform\Result\ToolCall;

    class ReadAllowPolicy implements PolicyInterface
    {
        public function decide(ToolCall $toolCall): PolicyDecision
        {
            $name = strtolower($toolCall->getName());

            foreach (['read', 'get', 'list', 'search', 'find', 'show'] as $pattern) {
                if (str_contains($name, $pattern)) {
                    return PolicyDecision::Allow;
                }
            }

            return PolicyDecision::AskUser;
        }
    }

Step 4: Build a Confirmation Handler
------------------------------------

The confirmation handler prompts the user and returns a decision. Its implementation depends on
your application context — CLI, web, async, etc.::

    use Symfony\AI\Platform\Result\ToolCall;

    class CliConfirmationHandler
    {
        public function confirm(ToolCall $toolCall): bool
        {
            echo \sprintf(
                "Allow tool '%s' with args %s? [y/N] ",
                $toolCall->getName(),
                json_encode($toolCall->getArguments())
            );

            return 'y' === strtolower(trim(fgets(\STDIN)));
        }
    }

For web applications, you might store pending confirmations in a database and wait for a user
response through an HTTP endpoint or WebSocket.

.. tip::

    The event also exposes the tool's metadata via ``$event->getMetadata()``, which includes the
    tool's description and parameter schema. Use it to show the user more context before they
    decide.

Step 5: Wire the Policy and Handler Together
--------------------------------------------

Combine the policy and handler in an event listener::

    use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;

    $policy = new ReadAllowPolicy();
    $handler = new CliConfirmationHandler();

    $dispatcher->addListener(ToolCallRequested::class, function (ToolCallRequested $event) use ($policy, $handler): void {
        $decision = $policy->decide($event->getToolCall());

        if (PolicyDecision::Allow === $decision) {
            return; // Auto-approved, proceed with execution
        }

        if (PolicyDecision::Deny === $decision) {
            $event->deny('Tool blocked by policy.');

            return;
        }

        // PolicyDecision::AskUser
        if (!$handler->confirm($event->getToolCall())) {
            $event->deny('User denied tool execution.');
        }
    });

Step 6: Remember User Decisions
-------------------------------

To avoid asking the user repeatedly for the same tool, cache decisions::

    use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;

    $decisions = [];

    $dispatcher->addListener(ToolCallRequested::class, function (ToolCallRequested $event) use (&$decisions): void {
        $toolName = $event->getToolCall()->getName();

        if (isset($decisions[$toolName])) {
            if (!$decisions[$toolName]) {
                $event->deny('Tool previously denied by user.');
            }

            return;
        }

        echo \sprintf(
            "Allow tool '%s'? [y/N/always/never] ",
            $toolName
        );

        $input = strtolower(trim(fgets(\STDIN)));

        $allowed = \in_array($input, ['y', 'always'], true);

        if (\in_array($input, ['always', 'never'], true)) {
            $decisions[$toolName] = $allowed;
        }

        if (!$allowed) {
            $event->deny('User denied tool execution.');
        }
    });

Step 7: Register the Listener in Symfony
----------------------------------------

In a Symfony application, the
:class:`Symfony\\Component\\EventDispatcher\\Attribute\\AsEventListener` attribute registers the
listener automatically — no service configuration needed. The event is inferred from the
``__invoke()`` argument::

    namespace App\EventListener;

    use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    class ToolConfirmationListener
    {
        public function __invoke(ToolCallRequested $event): void
        {
            // Your confirmation logic here
        }
    }

Learn More
----------

* `Human-in-the-Loop Confirmation Example <https://github.com/symfony/ai/blob/main/examples/toolbox/confirmation.php>`_
* :doc:`tool-calling-with-agents` - Build and register custom tools
* :doc:`../components/agent` - Agent component documentation
