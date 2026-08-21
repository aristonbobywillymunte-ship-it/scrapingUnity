<?php
namespace App\Collectors;

class TikTokCommentsCollector implements CollectorInterface {
    public function collect($task): array {
        $parentUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://tiktok.com/@user/video/parent';
        return [
            [
                'platform' => 'TIKTOK',
                'entity_type' => 'COMMENT',
                'stable_source_id' => 'tt_comm_' . md5($parentUrl),
                'normalized_url' => $parentUrl . '#c1',
                'payload' => [
                    'entity_type' => 'COMMENT',
                    'text_content' => 'TikTok comment on ' . $parentUrl
                ]
            ]
        ];
    }
}
