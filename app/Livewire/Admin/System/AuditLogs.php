<?php
namespace App\Livewire\Admin\System;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class AuditLogs extends Component {
    use WithPagination, AuthorizesAdmin;

    public $actionFilter = '';
    public $search = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $query = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.actor_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.email as actor_email');

        if (!empty($this->actionFilter)) {
            $query->where('audit_logs.action', $this->actionFilter);
        }
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('audit_logs.target', 'like', '%' . $this->search . '%')
                  ->orWhere('audit_logs.action', 'like', '%' . $this->search . '%');
            });
        }

        $logs = $query->orderBy('audit_logs.created_at', 'desc')->paginate(20);

        $actions = DB::table('audit_logs')->distinct()->pluck('action');

        return view('livewire.admin.system.audit-logs', [
            'logs' => $logs,
            'actions' => $actions,
        ]);
    }
}
