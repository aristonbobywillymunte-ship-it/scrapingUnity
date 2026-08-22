<?php
namespace App\Livewire\Admin\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;
use App\Services\SanitizerService;

#[Layout('layouts.app')]
class Logs extends Component {
    use AuthorizesAdmin;

    public $levelFilter = '';
    public $search = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $logPath = storage_path('logs/laravel.log');
        $lines = [];

        if (file_exists($logPath)) {
            // Read safely last 100 lines
            $rawLines = array_slice(file($logPath), -100);
            foreach ($rawLines as $line) {
                // Sanitize any potential secrets
                $clean = SanitizerService::sanitizeException(new \Exception($line));
                $clean = str_replace('Sanitized Exception: ', '', $clean);

                if (!empty($this->levelFilter) && !str_contains(strtoupper($clean), strtoupper($this->levelFilter))) {
                    continue;
                }
                if (!empty($this->search) && !str_contains(strtolower($clean), strtolower($this->search))) {
                    continue;
                }
                $lines[] = trim($clean);
            }
        }

        return view('livewire.admin.system.logs', [
            'logLines' => array_reverse($lines),
        ]);
    }
}
