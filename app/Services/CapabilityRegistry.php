<?php
namespace App\Services;

class CapabilityRegistry {
    const CAPABILITIES = [
        'facebook_posts' => [
            'id' => 'facebook_posts',
            'label' => 'Facebook Posts',
            'cost' => 10.0,
            'fields' => ['target_url', 'max_pages'],
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookPostsCollector::class
        ],
        'facebook_comments' => [
            'id' => 'facebook_comments',
            'label' => 'Facebook Comments',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookCommentsCollector::class
        ],
        'instagram_posts' => [
            'id' => 'instagram_posts',
            'label' => 'Instagram Posts',
            'cost' => 10.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramPostsCollector::class
        ],
        'instagram_reels' => [
            'id' => 'instagram_reels',
            'label' => 'Instagram Reels',
            'cost' => 10.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramReelsCollector::class
        ],
        'instagram_comments' => [
            'id' => 'instagram_comments',
            'label' => 'Instagram Comments',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramCommentsCollector::class
        ],
        'tiktok_videos' => [
            'id' => 'tiktok_videos',
            'label' => 'TikTok Videos',
            'cost' => 10.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\TikTokWorker::class,
            'collector' => \App\Collectors\TikTokVideosCollector::class
        ],
        'tiktok_comments' => [
            'id' => 'tiktok_comments',
            'label' => 'TikTok Comments',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\TikTokWorker::class,
            'collector' => \App\Collectors\TikTokCommentsCollector::class
        ],
        'youtube_videos' => [
            'id' => 'youtube_videos',
            'label' => 'YouTube Videos',
            'cost' => 10.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\YouTubeWorker::class,
            'collector' => \App\Collectors\YouTubeVideosCollector::class
        ],
        'youtube_comments' => [
            'id' => 'youtube_comments',
            'label' => 'YouTube Comments',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\YouTubeWorker::class,
            'collector' => \App\Collectors\YouTubeCommentsCollector::class
        ],
        'x_posts' => [
            'id' => 'x_posts',
            'label' => 'X Posts',
            'cost' => 10.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\XWorker::class,
            'collector' => \App\Collectors\XPostsCollector::class
        ],
        'x_replies' => [
            'id' => 'x_replies',
            'label' => 'X Replies',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\XWorker::class,
            'collector' => \App\Collectors\XRepliesCollector::class
        ],
        'news_articles' => [
            'id' => 'news_articles',
            'label' => 'News Articles',
            'cost' => 5.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\GenericWorker::class,
            'collector' => \App\Collectors\NewsCollector::class
        ],
        'web_pages' => [
            'id' => 'web_pages',
            'label' => 'Web Pages',
            'cost' => 2.0,
            'fields' => ['target_url'],
            'worker' => \App\Workers\GenericWorker::class,
            'collector' => \App\Collectors\WebCollector::class
        ]
    ];

    public static function isValid(string $capability): bool {
        return array_key_exists($capability, self::CAPABILITIES);
    }
    
    public static function get(string $capability): ?array {
        return self::CAPABILITIES[$capability] ?? null;
    }
    
    public static function all(): array {
        return self::CAPABILITIES;
    }
}
