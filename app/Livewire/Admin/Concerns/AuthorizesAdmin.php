<?php
namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\DB;

trait AuthorizesAdmin {
    protected function authorizeAdmin(): void {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        $hasAssignment = DB::table('internal_user_assignments')
            ->where('user_id', $user->id)
            ->where('role_is_internal', true)
            ->exists();

        if (!$hasAssignment) {
            abort(403, 'Unauthorized access.');
        }
    }
}
