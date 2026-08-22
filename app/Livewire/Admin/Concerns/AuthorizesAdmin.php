<?php
namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\DB;

trait AuthorizesAdmin {
    protected function authorizeAdmin(): void {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        // Must explicitly hold the canonical Admin internal role
        $isAdmin = DB::table('internal_user_assignments')
            ->where('user_id', $user->id)
            ->where('role_is_internal', true)
            ->whereIn('role_id', ['admin', 'internal_admin'])
            ->exists();

        if (!$isAdmin) {
            abort(403, 'Unauthorized access.');
        }
    }
}
