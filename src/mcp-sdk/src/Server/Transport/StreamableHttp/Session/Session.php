<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session;

use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionIdentifier;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\Uid\Uuid;

class Session
{
    private const MAX_EVENTS_PER_STREAM = 10;

    /**
     * @var array<int, array{id: Uuid, clientInitiated: bool, events: <int, array{id: string, event: string}>}>
     */
    private array $streams;

    private bool $clientNotificationInitializedReceived = false;

    /**
     * @var array{string, int}
     */
    private array $eventsIdToStreamId;
    public function __construct(public readonly SessionIdentifier $sessionIdentifier, private readonly SessionStorageInterface $sessionStorage, array $data = []) {
        $this->streams = $data['streams'] ?? [];
        $this->eventsIdToStreamId = $data['eventsIdToStreamId'] ?? [];
    }

    public function exists(): bool
    {
        return $this->sessionStorage->exists($this->sessionIdentifier);
    }

    public function save(): void
    {
        $this->sessionStorage->save($this->sessionIdentifier, $this);
    }

    public function getData(): array
    {
        return [
            'streams' => $this->streams,
            'eventsIdToStreamId' => $this->eventsIdToStreamId,
        ];
    }

    public function getStreamUuid(int $streamId): Uuid
    {
        $this->refreshData();
        if (!isset($this->streams[$streamId])) {
            throw new \InvalidArgumentException(sprintf('Stream with id "%s" does not exist', $streamId));
        }
        return $this->streams[$streamId]['id'];
    }

    public function getEventsOnStream(int $streamId): array
    {
        $this->refreshData();
        if (!isset($this->streams[$streamId])) {
            throw new \InvalidArgumentException(sprintf('Stream with id "%s" does not exist', $streamId));
        }
        return $this->streams[$streamId]['messages'] ?? [];
    }

    public function addEventOnStream(int $streamId, string $eventId, string $event): void
    {
        $this->refreshData();
        if (!isset($this->streams[$streamId])) {
            throw new \InvalidArgumentException(sprintf('Stream with id "%s" does not exist', $streamId));
        }
        $this->streams[$streamId]['events'][] = [
            'id' => $eventId,
            'event' => $event
        ];
        if (count($this->streams[$streamId]['events']) > self::MAX_EVENTS_PER_STREAM) {
            array_shift($this->streams[$streamId]['events']);
        }
        $this->eventsIdToStreamId[$eventId] = $streamId;
        $this->save();
    }

    public function getStreamIdForEvent(string $eventId): int
    {
        $this->refreshData();
        if (!isset($this->eventsIdToStreamId[$eventId])) {
            throw new \InvalidArgumentException(sprintf('Event with id "%s" does not exist', $eventId));
        }
        return $this->eventsIdToStreamId[$eventId];
    }

    public function getEventsAfterId(string $eventId): array
    {
        $streamId = $this->getStreamIdForEvent($eventId);
        $events = $this->streams[$streamId]['events'];
        $eventOffset = array_search($eventId, array_column($events, 'id'));
        if ($eventOffset === false) {
            return [];
        }
        return array_slice($events, (int) $eventOffset + 1);
    }

    public function addNewStream(bool $clientInitiated = false): int
    {
        $this->refreshData();
        $streamId = count($this->streams);
        $this->streams[$streamId] = [
            'id' => Uuid::v4(),
            'clientInitiated' => $clientInitiated,
            'events' => [],
        ];
        $this->save();

        return $streamId;
    }

    public function isClientNotificationInitializedReceived(): bool
    {
        $this->refreshData();
        return $this->clientNotificationInitializedReceived;
    }

    public function setClientNotificationInitializedReceived(): void
    {
        $this->refreshData();
        $this->clientNotificationInitializedReceived = true;
        $this->save();
    }

    private function refreshData(): void
    {
        if (!$this->exists()) {
            throw new SessionNotFoundException();
        }
        $session = $this->sessionStorage->get($this->sessionIdentifier);
        $this->streams = $session->streams;
        $this->eventsIdToStreamId = $session->eventsIdToStreamId;
    }

    public function delete(): void
    {
        $this->sessionStorage->remove($this->sessionIdentifier);
    }
}
