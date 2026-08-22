import re

with open("tests/Feature/RateLimitTest.php", "r") as f:
    code = f.read()

# I will just simplify RateLimitTest setup.
old_setup = """        // Setup canonical user and limit
        $userId = (string) Str::uuid();
        $this->user = User::create([
            'id' => $userId,
            'name' => 'Rate Limit Test User',
            'email' => 'rl@example.com',
            'password' => bcrypt('password'),
        ]);

        $orgId = (string) Str::uuid();
        DB::table('organizations')->insert([
            'id' => $orgId,
            'name' => 'RL Org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'name' => 'Admin',
            'is_internal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_memberships')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'organization_id' => $orgId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pkgId = (string) Str::uuid();
        DB::table('packages')->insert([
            'id' => $pkgId,
            'name' => 'API Plan',
            'code' => 'api_plan',
            'interval' => 'monthly',
            'price' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('package_entitlements')->insert([
            'id' => (string) Str::uuid(),
            'package_id' => $pkgId,
            'capability' => 'api_access',
            'limits' => json_encode(['rate_limit_rpm' => 10]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'package_id' => $pkgId,
            'status' => 'ACTIVE',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);"""

new_setup = """        // Setup canonical user and limit
        $planId = DB::table('plans')->insertGetId([
            'name' => 'Test 10 RPM Plan',
            'monthly_quota' => 1000,
            'rate_limit_rpm' => 10,
            'max_concurrency' => 1,
            'allowed_modes' => json_encode(['http']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        $this->user = User::create([
            'id' => $userId,
            'name' => 'Rate Limit Test User',
            'email' => 'rl@example.com',
            'password' => bcrypt('password'),
            'plan_id' => $planId,
        ]);
        $orgId = (string) Str::uuid();"""

code = code.replace(old_setup, new_setup)

with open("tests/Feature/RateLimitTest.php", "w") as f:
    f.write(code)
