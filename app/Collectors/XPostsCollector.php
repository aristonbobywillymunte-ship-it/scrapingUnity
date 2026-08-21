<?php
namespace App\Collectors;

class XPostsCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['hashtag'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'X',
                'entity_type' => 'POST',
                'stable_source_id' => 'x_post_' . md5($source),
                'normalized_url' => 'https://x.com/user/status/' . md5($source),
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'X post discovered via ' . $source
                ]
            ]
        ];
    }
}
