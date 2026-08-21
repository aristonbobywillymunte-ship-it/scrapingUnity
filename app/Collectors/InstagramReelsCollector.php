<?php
namespace App\Collectors;

class InstagramReelsCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['hashtag'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'INSTAGRAM',
                'entity_type' => 'VIDEO',
                'stable_source_id' => 'ig_reel_' . md5($source),
                'normalized_url' => 'https://instagram.com/reel/' . md5($source),
                'payload' => [
                    'entity_type' => 'VIDEO',
                    'text_content' => 'Instagram Reel discovered via ' . $source
                ]
            ]
        ];
    }
}
