<?php
namespace App\Collectors;

class InstagramPostsCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['hashtag'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'INSTAGRAM',
                'entity_type' => 'POST',
                'stable_source_id' => 'ig_post_' . md5($source),
                'normalized_url' => 'https://instagram.com/p/' . md5($source),
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'Instagram Post discovered via ' . $source
                ]
            ]
        ];
    }
}
