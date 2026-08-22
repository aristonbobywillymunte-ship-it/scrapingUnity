<?php
namespace App\Livewire\Admin\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Providers extends Component {
    use AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $providers = DB::table('provider_configs')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.admin.system.providers', [
            'providers' => $providers,
        ]);
    }
}
