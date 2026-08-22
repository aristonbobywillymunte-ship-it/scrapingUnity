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
        $user = auth()->user();
        $isInternal = DB::table('internal_user_assignments')
            ->where('user_id', $user?->id)
            ->exists();
        $isPlatformAdmin = $user?->email === 'admin@example.com';
            
        if (!$isInternal && !$isPlatformAdmin) {
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
