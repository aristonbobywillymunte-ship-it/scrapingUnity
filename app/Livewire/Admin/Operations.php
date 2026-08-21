<?php
namespace App\Livewire\Admin;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Operations extends Component {
    public function mount() {
        $internalRole = DB::table('internal_user_assignments')
            ->where('user_id', auth()->id())
            ->exists();
            
        if (!$internalRole) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function render() { 
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch(\Exception $e) {}
        
        $pendingTasks = 0;
        try {
            $pendingTasks = DB::table('tasks')->where('status', 'QUEUED')->count();
        } catch(\Exception $e) {}

        return view('livewire.admin.operations', [
            'failedJobs' => $failedJobs,
            'pendingTasks' => $pendingTasks
        ]); 
    }
}
