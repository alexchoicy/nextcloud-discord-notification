<?php

namespace OCA\DiscordNotification\Listeners;


use OCP\EventDispatcher\IEventListener;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeCreatedEvent;
use Psr\Log\LoggerInterface;

class FolderUpdateListener implements IEventListener {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
		if (!($event instanceof NodeCreatedEvent)) {
            return;
        }
        $node = $event->getNode();
        $this->logger->info('Node created: ' . $node->getPath());
        $this->logger->info(''. $node->getPath());
        $this->logger->info(''. $node->getName());
    }
}