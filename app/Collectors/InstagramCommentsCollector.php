<?php
namespace App\Collectors;

class InstagramCommentsCollector implements CollectorInterface {
    public function collect($task): array {
        $parentUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://instagram.com/p/parent';
        return [
            [
                'platform' => 'INSTAGRAM',
                'entity_type' => 'COMMENT',
                'stable_source_id' => 'ig_comm_' . md5($parentUrl),
                'normalized_url' => $parentUrl . '#c1',
                'payload' => [
                    'entity_type' => 'COMMENT',
                    'text_content' => 'Instagram comment on ' . $parentUrl
                ]
            ]
        ];
    }
}
