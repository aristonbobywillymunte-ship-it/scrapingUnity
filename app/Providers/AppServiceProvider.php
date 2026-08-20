<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Organization;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OtpDeliveryService::class, function($app) {
            if ($app->environment('testing')) return new \App\Services\FakeOtpDeliveryService();
            return new class implements \App\Services\OtpDeliveryService {
                public function send(string $channel, string $address, string $otp): void {}
            };
        });
    }

    public function boot(): void
    {
        Gate::define('access-org', function (User $user, $orgId) {
            if ($user->status !== 'ACTIVE') return false;
            $org = Organization::find($orgId);
            if (!$org || $org->status !== 'ACTIVE') return false;
            return \App\Models\OrganizationMembership::where('user_id', $user->id)
                ->where('organization_id', $orgId)->exists();
        });
    }
}
