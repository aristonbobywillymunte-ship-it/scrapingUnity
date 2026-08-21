<?php
namespace App\Collectors;

class WebCollector implements CollectorInterface {
    public function collect($task): array {
        $targetUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://example.com';
        return [
            [
                'platform' => 'WEB',
                'entity_type' => 'POST',
                'stable_source_id' => 'web_' . md5($targetUrl),
                'normalized_url' => $targetUrl,
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'Scraped web content from ' . $targetUrl
                ]
            ]
        ];
    }
}
