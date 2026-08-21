<?php
namespace App\Collectors;

class FacebookPostsCollector implements CollectorInterface {
    public function collect($task): array {
        // Deterministic fixture for testing boundaries
        return [
            [
                'platform' => 'FACEBOOK',
                'entity_type' => 'POST',
                'stable_source_id' => '12345_TEST',
                'normalized_url' => 'https://facebook.com/12345_TEST',
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'Actual execution data from collector boundary'
                ]
            ]
        ];
    }
}
