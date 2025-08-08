<?php

namespace Symfony\AI\McpBundle\Session;

use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionFactory;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionIdentifierFactory;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\UuidV4;

readonly class SessionSubscriber implements EventSubscriberInterface
{
    public function __construct(private SessionIdentifierFactory $identifierFactory, private SessionFactory $sessionFactory) {

    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->getRequest()->headers->has('Mcp-Session-Id')) {
            return;
        }

        try {
            $uuid = UuidV4::fromString($event->getRequest()->headers->get('Mcp-Session-Id'));
        } catch (InvalidArgumentException) {
            throw new BadRequestException(sprintf('Mcp-Session-Id "%s" is not a valid uuid.', $event->getRequest()->headers->get('Mcp-Session-Id')));
        }

        $sessionIdentifier = $this->identifierFactory->get($uuid);
        $session = $this->sessionFactory->get($sessionIdentifier);
        if (!$session->exists()) {
            throw new NotFoundHttpException(sprintf('Session "%s" not found.', $sessionIdentifier));
        }

        $event->getRequest()->attributes->set('_mcp_session_id', $sessionIdentifier);
        $event->getRequest()->attributes->set('_mcp_session', $session);
    }
}
