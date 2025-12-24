Upgrade Guide
=========

0.2
----------

 * [BC BREAK] Version 0.2 introduces a breaking change to how Agent and MultiAgent services are injected.

The Agent and MultiAgent suffixes have been removed from injection aliases.

Agents are now injected using their configuration name directly, instead of appending Agent or MultiAgent.

Before (v0.1 and earlier)

Injection aliases included the Agent or MultiAgent suffix.

```
#[AsCommand('app:blog:stream', 'An example command to demonstrate streaming output.')]
final class StreamCommand
{
    public function __construct(
        private AgentInterface $blogAgent,
    ) {}
}
```

After (v0.2)

Injection aliases now match the agent configuration name only.

```
#[AsCommand('app:blog:stream', 'An example command to demonstrate streaming output.')]
final class StreamCommand
{
    public function __construct(
        private AgentInterface $blog,
    ) {}
}
```
