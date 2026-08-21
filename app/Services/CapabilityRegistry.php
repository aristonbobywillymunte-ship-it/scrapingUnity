<?php
namespace App\Services;

class CapabilityRegistry {
    const CAPABILITIES = [
        'facebook_posts' => [
            'id' => 'facebook_posts',
            'label' => 'Facebook Posts',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookPostsCollector::class
        ],
        'facebook_comments' => [
            'id' => 'facebook_comments',
            'label' => 'Facebook Comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Post Facebook',
            'cost' => 5.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookCommentsCollector::class
        ],
        'instagram_posts' => [
            'id' => 'instagram_posts',
            'label' => 'Instagram Posts',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramPostsCollector::class
        ],
        'instagram_reels' => [
            'id' => 'instagram_reels',
            'label' => 'Instagram Reels',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramReelsCollector::class
        ],
        'instagram_comments' => [
            'id' => 'instagram_comments',
            'label' => 'Instagram Comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Post/Reel Instagram',
            'cost' => 5.0,
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramCommentsCollector::class
        ],
        'tiktok_videos' => [
            'id' => 'tiktok_videos',
            'label' => 'TikTok Videos',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\TikTokWorker::class,
            'collector' => \App\Collectors\TikTokVideosCollector::class
        ],
        'tiktok_comments' => [
            'id' => 'tiktok_comments',
            'label' => 'TikTok Comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Video TikTok',
            'cost' => 5.0,
            'worker' => \App\Workers\TikTokWorker::class,
            'collector' => \App\Collectors\TikTokCommentsCollector::class
        ],
        'youtube_videos' => [
            'id' => 'youtube_videos',
            'label' => 'YouTube Videos',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\YouTubeWorker::class,
            'collector' => \App\Collectors\YouTubeVideosCollector::class
        ],
        'youtube_comments' => [
            'id' => 'youtube_comments',
            'label' => 'YouTube Comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Video YouTube',
            'cost' => 5.0,
            'worker' => \App\Workers\YouTubeWorker::class,
            'collector' => \App\Collectors\YouTubeCommentsCollector::class
        ],
        'x_posts' => [
            'id' => 'x_posts',
            'label' => 'X Posts',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'cost' => 10.0,
            'worker' => \App\Workers\XWorker::class,
            'collector' => \App\Collectors\XPostsCollector::class
        ],
        'x_replies' => [
            'id' => 'x_replies',
            'label' => 'X Replies',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Post/Thread X',
            'cost' => 5.0,
            'worker' => \App\Workers\XWorker::class,
            'collector' => \App\Collectors\XRepliesCollector::class
        ],
        'news_articles' => [
            'id' => 'news_articles',
            'label' => 'News Articles',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'target'],
            'target_label' => 'URL Sumber / Portal Berita',
            'cost' => 5.0,
            'worker' => \App\Workers\GenericWorker::class,
            'collector' => \App\Collectors\NewsCollector::class
        ],
        'web_pages' => [
            'id' => 'web_pages',
            'label' => 'Web Pages',
            'type' => 'crawl',
            'supported_modes' => ['target'],
            'target_label' => 'URL Halaman Web Target',
            'cost' => 2.0,
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
