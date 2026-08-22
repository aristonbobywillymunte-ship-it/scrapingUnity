<?php
namespace App\Livewire\Admin\Jobs;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class TestHistory extends Component {
    use WithPagination, AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $tests = DB::table('runs')
            ->whereIn('origin', ['MANUAL_LAB', 'LAB', 'MANUAL', 'DIAGNOSTIC'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.jobs.test-history', [
            'tests' => $tests,
        ]);
    }
}
