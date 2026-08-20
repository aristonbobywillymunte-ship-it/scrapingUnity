<?php
namespace App\Services;

class CapabilityRegistry {
    const CAPABILITIES = [
        'facebook_posts',
        'facebook_comments',
        'instagram_posts',
        'instagram_reels',
        'instagram_comments',
        'tiktok_videos',
        'tiktok_comments',
        'youtube_videos',
        'youtube_comments',
        'x_posts',
        'x_replies',
        'news_articles',
        'web_pages'
    ];

    public static function isValid(string $capability): bool {
        return in_array($capability, self::CAPABILITIES);
    }
}
