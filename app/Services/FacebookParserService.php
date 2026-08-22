<?php
namespace App\Services;

use DOMDocument;
use DOMXPath;

class FacebookParserService {
    /**
     * Parse HTML response into structured post records without inventing synthetic fields.
     */
    public function parsePosts(string $html, string $sourceUrl): array {
        $records = [];

        if (empty(trim($html))) {
            return $records;
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // 1. Try to extract OpenGraph / Meta post properties if available
        $ogTitle = $this->getMetaContent($xpath, 'og:title');
        $ogDesc = $this->getMetaContent($xpath, 'og:description');
        $ogUrl = $this->getMetaContent($xpath, 'og:url') ?? $sourceUrl;

        // If OpenGraph post content exists
        if ($ogTitle || $ogDesc) {
            $stableId = 'fb_' . substr(hash('sha256', $ogUrl), 0, 16);
            $records[] = [
                'platform' => 'FACEBOOK',
                'entity_type' => 'POST',
                'stable_source_id' => $stableId,
                'normalized_url' => $ogUrl,
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => $ogDesc ?: $ogTitle,
                    'author_name' => $ogTitle ? explode(' - ', $ogTitle)[0] : null,
                    'raw_extracted' => true,
                ]
            ];
            return $records;
        }

        // 2. DOM extraction: query article or user content blocks
        $nodes = $xpath->query('//article | //div[@role="article"] | //div[contains(@class, "userContent")]');
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $index => $node) {
                $textContent = trim($node->textContent);
                if (strlen($textContent) > 10) {
                    $stableId = 'fb_node_' . substr(hash('sha256', $sourceUrl . '_' . $index . '_' . substr($textContent, 0, 50)), 0, 16);
                    $records[] = [
                        'platform' => 'FACEBOOK',
                        'entity_type' => 'POST',
                        'stable_source_id' => $stableId,
                        'normalized_url' => $sourceUrl . '#' . $index,
                        'payload' => [
                            'entity_type' => 'POST',
                            'text_content' => $textContent,
                            'raw_extracted' => true,
                        ]
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * Parse HTML response for public comments/replies.
     */
    public function parseComments(string $html, string $parentUrl): array {
        $records = [];

        if (empty(trim($html))) {
            return $records;
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $commentNodes = $xpath->query('//div[@role="article"]//div[contains(@class, "comment")] | //ul[contains(@class, "comment")]/li');

        if ($commentNodes && $commentNodes->length > 0) {
            foreach ($commentNodes as $index => $node) {
                $textContent = trim($node->textContent);
                if (strlen($textContent) > 5) {
                    $stableId = 'fb_comm_' . substr(hash('sha256', $parentUrl . '_' . $index), 0, 16);
                    $records[] = [
                        'platform' => 'FACEBOOK',
                        'entity_type' => 'COMMENT',
                        'stable_source_id' => $stableId,
                        'normalized_url' => $parentUrl . '#comment_' . ($index + 1),
                        'payload' => [
                            'entity_type' => 'COMMENT',
                            'text_content' => $textContent,
                            'parent_url' => $parentUrl,
                            'raw_extracted' => true,
                        ]
                    ];
                }
            }
        }

        return $records;
    }

    private function getMetaContent(DOMXPath $xpath, string $property): ?string {
        $nodes = $xpath->query('//meta[@property="' . $property . '"]/@content | //meta[@name="' . $property . '"]/@content');
        if ($nodes && $nodes->length > 0) {
            $val = trim($nodes->item(0)->nodeValue);
            return empty($val) ? null : $val;
        }
        return null;
    }
}
