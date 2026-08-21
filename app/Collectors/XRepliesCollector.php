<?php
namespace App\Collectors;

class XRepliesCollector implements CollectorInterface {
    public function collect($task): array {
        $parentUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://x.com/user/status/parent';
        return [
            [
                'platform' => 'X',
                'entity_type' => 'POST',
                'stable_source_id' => 'x_reply_' . md5($parentUrl),
                'normalized_url' => $parentUrl . '#r1',
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'X reply on ' . $parentUrl
                ]
            ]
        ];
    }
}
