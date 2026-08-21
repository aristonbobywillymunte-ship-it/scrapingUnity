<?php
namespace App\Collectors;

class YouTubeCommentsCollector implements CollectorInterface {
    public function collect($task): array {
        $parentUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://youtube.com/watch?v=parent';
        return [
            [
                'platform' => 'YOUTUBE',
                'entity_type' => 'COMMENT',
                'stable_source_id' => 'yt_comm_' . md5($parentUrl),
                'normalized_url' => $parentUrl . '#c1',
                'payload' => [
                    'entity_type' => 'COMMENT',
                    'text_content' => 'YouTube comment on ' . $parentUrl
                ]
            ]
        ];
    }
}
