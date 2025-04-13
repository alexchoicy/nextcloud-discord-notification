<?php

declare(strict_types=1);

namespace OCA\DiscordNotification\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\DiscordNotification\Listeners\FolderUpdateListener;

use OCP\Files\Events\Node\NodeWrittenEvent;


class Application extends App implements IBootstrap {
	public const APP_ID = 'discordnotification';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		include_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerEventListener(NodeWrittenEvent::class,FolderUpdateListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
