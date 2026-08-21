<?php
namespace App\Collectors;

class NewsCollector implements CollectorInterface {
    public function collect($task): array {
        $source = $task->payload['search_query'] ?? $task->payload['target'] ?? $task->payload['target_url'] ?? 'general';
        return [
            [
                'platform' => 'NEWS',
                'entity_type' => 'POST',
                'stable_source_id' => 'news_' . md5($source),
                'normalized_url' => 'https://news.example.com/article/' . md5($source),
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'News article for ' . $source
                ]
            ]
        ];
    }
}
