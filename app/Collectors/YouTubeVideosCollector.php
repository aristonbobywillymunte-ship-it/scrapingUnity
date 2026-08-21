<?php
namespace App\Collectors;

class YouTubeVideosCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['hashtag'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'YOUTUBE',
                'entity_type' => 'VIDEO',
                'stable_source_id' => 'yt_vid_' . md5($source),
                'normalized_url' => 'https://youtube.com/watch?v=' . md5($source),
                'payload' => [
                    'entity_type' => 'VIDEO',
                    'text_content' => 'YouTube Video discovered via ' . $source
                ]
            ]
        ];
    }
}
