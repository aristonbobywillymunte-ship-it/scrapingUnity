<?php
namespace App\Livewire\Admin\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Settings extends Component {
    use AuthorizesAdmin;

    public $settings = [];
    public $successMessage = '';
    public $errorMessage = '';

    public function mount() {
        $this->authorizeAdmin();
        $this->loadSettings();
    }

    public function loadSettings() {
        $items = DB::table('system_settings')->get();
        foreach ($items as $item) {
            $this->settings[$item->key] = $item->value;
        }
    }

    public function saveSettings() {
        $this->authorizeAdmin();

        try {
            DB::beginTransaction();
            foreach ($this->settings as $key => $val) {
                DB::table('system_settings')->where('key', $key)->update([
                    'value' => (string) $val,
                    'updated_at' => now(),
                ]);
            }

            DB::table('audit_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'actor_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'SYSTEM_SETTINGS_UPDATED',
                'target' => 'system_settings',
                'safe_metadata' => json_encode(['keys_updated' => array_keys($this->settings)]),
                'created_at' => now(),
            ]);

            DB::commit();
            $this->successMessage = 'Pengaturan sistem berhasil diperbarui.';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
        }
    }

    public function render() {
        $definitions = DB::table('system_settings')->get();
        return view('livewire.admin.system.settings', [
            'definitions' => $definitions,
        ]);
    }
}
