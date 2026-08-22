<?php
namespace App\Services;

class CapabilityRegistry {
    /**
     * Canonical MVP Capabilities Registry (Facebook Only).
     * Non-MVP platforms are strictly disabled / deferred from production execution.
     */
    const CAPABILITIES = [
        'facebook_posts' => [
            'id' => 'facebook_posts',
            'label' => 'Facebook Posts',
            'platform' => 'facebook',
            'operation' => 'posts',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'enabled' => true,
            'status' => 'ACTIVE',
            'http_supported' => true,
            'browser_supported' => true,
            'session_required' => false,
            'max_items' => 100,
            'max_pages' => 1,
            'timeout' => 15,
            'cache_ttl' => 3600,
            'cost' => 10.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookPostsCollector::class
        ],
        'facebook_comments' => [
            'id' => 'facebook_comments',
            'label' => 'Facebook Comments',
            'platform' => 'facebook',
            'operation' => 'comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'target_label' => 'URL atau ID Post Facebook',
            'enabled' => true,
            'status' => 'ACTIVE',
            'http_supported' => true,
            'browser_supported' => true,
            'session_required' => false,
            'max_items' => 50,
            'max_pages' => 1,
            'timeout' => 15,
            'cache_ttl' => 3600,
            'cost' => 5.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookCommentsCollector::class
        ],
        'facebook_profile' => [
            'id' => 'facebook_profile',
            'label' => 'Facebook Profile',
            'platform' => 'facebook',
            'operation' => 'profile',
            'type' => 'discovery',
            'supported_modes' => ['target'],
            'target_label' => 'Username atau URL Profil Facebook',
            'enabled' => true,
            'status' => 'ACTIVE',
            'http_supported' => true,
            'browser_supported' => true,
            'session_required' => false,
            'max_items' => 1,
            'max_pages' => 1,
            'timeout' => 15,
            'cache_ttl' => 86400,
            'cost' => 5.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookPostsCollector::class
        ],
        'instagram_reels' => [
            'id' => 'instagram_reels',
            'label' => 'Instagram Reels',
            'platform' => 'instagram',
            'operation' => 'reels',
            'type' => 'discovery',
            'supported_modes' => ['search_query', 'hashtag', 'target'],
            'enabled' => false,
            'status' => 'DEFERRED',
            'cost' => 10.0,
            'worker' => \App\Workers\InstagramWorker::class,
            'collector' => \App\Collectors\InstagramReelsCollector::class
        ],
        'web_pages' => [
            'id' => 'web_pages',
            'label' => 'Web Pages',
            'platform' => 'web',
            'operation' => 'pages',
            'type' => 'discovery',
            'supported_modes' => ['target'],
            'enabled' => false,
            'status' => 'DEFERRED',
            'cost' => 5.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookPostsCollector::class
        ],
        'youtube_comments' => [
            'id' => 'youtube_comments',
            'label' => 'YouTube Comments',
            'platform' => 'youtube',
            'operation' => 'comments',
            'type' => 'child',
            'supported_modes' => ['target'],
            'enabled' => false,
            'status' => 'DEFERRED',
            'cost' => 5.0,
            'worker' => \App\Workers\FacebookWorker::class,
            'collector' => \App\Collectors\FacebookCommentsCollector::class
        ]
    ];

    public static function all(): array {
        return self::CAPABILITIES;
    }

    public static function get(string $id): ?array {
        return self::CAPABILITIES[$id] ?? null;
    }

    public static function isValid(string $id): bool {
        return isset(self::CAPABILITIES[$id]) && (self::CAPABILITIES[$id]['enabled'] ?? false);
    }
}
