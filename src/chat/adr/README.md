# Chat ADRs

Architecture Decision Records of the Chat component; the process and template live in
the repository's root [`adr/README.md`](../../../adr/README.md).

| ADR | Decision |
| --- | -------- |
| [0001](0001-chat-is-a-thin-conversation-layer-over-an-agent.md) | **A chat is a thin conversation layer over an agent and a message store.** `Chat` loads the history, calls any `AgentInterface` and persists the answer, also for streamed answers, without AI logic of its own |
| [0002](0002-message-store-contract-split-into-storage-and-management.md) | **The message store contract is split into storage and management.** `MessageStoreInterface` saves and loads one conversation per store instance, `ManagedStoreInterface` sets up and drops its infrastructure, driven by console commands |
| [0003](0003-stores-serialize-messages-with-a-shared-normalizer.md) | **Stores serialize messages with one shared `MessageNormalizer`.** The bridges that do not store PHP objects use one Serializer normalizer that keeps ids, metadata and the ordered parts of assistant messages |
