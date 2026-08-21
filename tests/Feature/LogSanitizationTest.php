<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use App\Services\SanitizerService;

class LogSanitizationTest extends TestCase {
    public function test_secret_is_sanitized_from_exception_message() {
        $sensitiveMessage = "Connection failed with password 'super_secret_password_123' at host";
        $sanitizedMessage = "password [REDACTED]";
        
        $e = new \Exception($sensitiveMessage);
        
        $message = SanitizerService::sanitizeException($e);
        
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function($loggedMsg) use ($sanitizedMessage) {
                return str_contains($loggedMsg, $sanitizedMessage) && !str_contains($loggedMsg, 'super_secret_password_123');
            });
            
        Log::error($message);
    }
}
