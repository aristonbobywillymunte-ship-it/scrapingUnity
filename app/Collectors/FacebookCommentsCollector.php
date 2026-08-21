<?php
namespace App\Collectors;

class FacebookCommentsCollector implements CollectorInterface {
    public function collect($task): array {
        $parentUrl = $task->payload['target'] ?? $task->payload['target_url'] ?? 'https://facebook.com/post_parent';
        return [
            [
                'platform' => 'FACEBOOK',
                'entity_type' => 'COMMENT',
                'stable_source_id' => 'fb_comm_' . md5($parentUrl),
                'normalized_url' => $parentUrl . '#comment_1',
                'payload' => [
                    'entity_type' => 'COMMENT',
                    'text_content' => 'Comment on ' . $parentUrl
                ]
            ]
        ];
    }
}
