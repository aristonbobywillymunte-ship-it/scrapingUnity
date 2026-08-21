<?php
namespace App\Collectors;

class TikTokVideosCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['hashtag'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'TIKTOK',
                'entity_type' => 'VIDEO',
                'stable_source_id' => 'tt_video_' . md5($source),
                'normalized_url' => 'https://tiktok.com/@user/video/' . md5($source),
                'payload' => [
                    'entity_type' => 'VIDEO',
                    'text_content' => 'TikTok Video discovered via ' . $source
                ]
            ]
        ];
    }
}
