<?php
namespace App\Services;

class SanitizerService {
    public static function sanitizeException(\Exception $e): string {
        $message = $e->getMessage();
        // Redact potential passwords, tokens, API keys
        $message = preg_replace('/(password|token|key|secret)\s*[:=]\s*[\'"][^\'"]+[\'"]/i', '$1=[REDACTED]', $message);
        $message = preg_replace('/(password|token|key|secret)\s*[:=]\s*[^\s]+/i', '$1=[REDACTED]', $message);
        $message = preg_replace('/password\s+[\'"][^\'"]+[\'"]/i', 'password [REDACTED]', $message);

        return "Sanitized Exception: " . $message;
    }
}
