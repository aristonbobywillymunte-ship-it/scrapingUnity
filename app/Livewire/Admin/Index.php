<?php
namespace App\Livewire\Admin;
use Livewire\Component;
use App\Models\User;
use App\Models\Organization;
use App\Models\Run;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Index extends Component {
    public function mount() {
        $internalRole = DB::table('internal_user_assignments')
            ->where('user_id', auth()->id())
            ->exists();
            
        if (!$internalRole) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function render() { 
        return view('livewire.admin.index', [
            'totalUsers' => User::count(),
            'totalOrgs' => Organization::count(),
            'totalRuns' => Run::count(),
        ]); 
    }
}
