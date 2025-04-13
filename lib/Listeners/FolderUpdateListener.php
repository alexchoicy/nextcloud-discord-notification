<?php

namespace OCA\DiscordNotification\Listeners;

use OCP\EventDispatcher\IEventListener;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeWrittenEvent;
use Psr\Log\LoggerInterface;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

class FolderUpdateListener implements IEventListener {
    private LoggerInterface $logger;
    private IFilesMetadataManager $metadataManager;
    private IURLGenerator $urlGenerator;
    private IClientService $clientService;
    private IAppConfig $appConfig;

    private String $name;
    private String $avatar_url;
    private String $webhook;
    private String $clientId;

    public function __construct(LoggerInterface $logger, IFilesMetadataManager $metadataManager, IURLGenerator $urlGenerator, IConfig $config, IClientService $clientService) {
        $this->logger = $logger;
        $this->metadataManager = $metadataManager;
        $this->urlGenerator = $urlGenerator;
        $this->config = $config;
        $this->clientService = $clientService;

        $this->name = $this->config->getSystemValue('discordnotification_name', 'CLOUD BRO');
        $this->avatar_url = $this->config->getSystemValue('discordnotification_avatar_url', '');
        $this->webhook = $this->config->getSystemValue('discordnotification_WEBHOOK', '');
        $this->clientId = $this->config->getSystemValue('discordnotification_Imgur_Key', '');
    }

    public function handle(Event $event): void {
        if (!($event instanceof NodeWrittenEvent)) {
            return;
        }
        
        $node = $event->getNode();
        $path = $node->getPath();

        //i dunno what i doing so i just hardcode everything
        //HEHE
        if(!str_contains($path, '/files/Music')) {
            return;
        }


        $musicExtensions = ['.mp3', '.wav', '.flac'];
        $isMusicFile = false;
        foreach ($musicExtensions as $extension) {
            if (str_ends_with($path, $extension)) {
                $isMusicFile = true;
                break;
            }
        }
        if (!$isMusicFile) {
            return;
        }
        
        $fileID = $node->getId();
        $this->logger->info('Music file created: ' . $path);
        $this->logger->info('Music file created: ' . $fileID);

        $url = $this->urlGenerator-> linkToRouteAbsolute('files.view.showfile', [
            'fileid' => $fileID
        ]);

        $this->logger->info('Folder URL: ' . $url);

        $getID3 = new \getID3;
        $storage = $node->getStorage();

        $internalPath = $node->getInternalPath();

        $localPath = $storage->getLocalFile($internalPath);
        $this->logger->info('localPath: ' . $localPath);

        $data = $getID3->analyze($localPath);
        $this->logger->info('File: ' . $data['filename']);

        $picture = $data['comments']['picture'][0] ?? null;
        $imgurUrl = '';

        if ($picture) {
            $imageData = $picture['data'];
            $base64 = base64_encode($imageData);
            $imgurUrl = $this->uploadImageToImgur($base64);
        
            if ($imgurUrl) {
                $this->logger->info('Uploaded cover to Imgur: ' . $imgurUrl, ['app' => 'discordnotification']);
            }
        }

        $tags = $data['tags']['vorbiscomment'] ?? [];

        $title = $tags['title'][0] ?? 'Unknown Title';
        $artist = $tags['artist'][0] ?? 'Unknown Artist';
        $album = $tags['album'][0] ?? 'Unknown Album';
        
        $trackNumber = $tags['tracknumber'][0] ?? 'Unknown';
        $trackTotal = $tags['tracktotal'][0] ?? '?';
        
        $discNumber = $tags['discnumber'][0] ?? 'Unknown';
        $discTotal = $tags['disctotal'][0] ?? $discNumber;

        $payload = [
            'content' => null,
            'username' => $this->name,
            'avatar_url' => $this->avatar_url,
            'embeds' => [
                [
                    'title' => $title,
                    'url' => $url,
                    'color' => null,
                    'fields' => [
                        [
                            'name' => 'Album',
                            'value' => $album,
                            'inline' => true,
                        ],
                        [
                            'name' => 'Artist',
                            'value' => $artist,
                            'inline' => true,
                        ],
                        [
                            'name' => 'Track',
                            'value' => $trackNumber . ' of ' . $trackTotal,
                            'inline' => true,
                        ],
                        [
                            'name' => 'Disc',
                            'value' => $discNumber . ' of ' . $discTotal,
                            'inline' => true,
                        ],
                    ],
                    'author' => [
                        'name' => 'A new Music is Uploaded',
                    ],
                    'image' => [
                        'url' => $imgurUrl,
                    ],
                ],
            ],
            'attachments' => [],
        ];

        $this->sendToDiscord($payload);

    }

    
    public function sendToDiscord(array $payload): void {

        try {
            $client = $this->clientService->newClient();
    
            $response = $client->post($this->webhook , [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payload),
            ]);
    
            $this->logger->info('Discord message sent. Response: ' . $response->getBody(), ['app' => 'discordnotification']);
    
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Discord message: ' . $e->getMessage(), ['app' => 'discordnotification']);
        }
    }

    public function uploadImageToImgur(string $base64): ?string {
        try {
            $client = $this->clientService->newClient();
            $response = $client->post('https://api.imgur.com/3/image', [
                'headers' => [
                    'Authorization' => 'Client-ID ' . $this->clientId,
                ],
                'body' => [
                    'image' => $base64,
                    'type' => 'base64',
                ],
            ]);
    
            $json = json_decode($response->getBody(), true);
            return $json['data']['link'] ?? '';
    
        } catch (\Exception $e) {
            $this->logger->error('Imgur upload error: ' . $e->getMessage(), ['app' => 'discordnotification']);
            return '';
        }
    }
}