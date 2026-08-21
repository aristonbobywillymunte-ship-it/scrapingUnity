<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Admin Dashboard</h2>
        </div>
    </div>
    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Total Users</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $totalUsers }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Total Organizations</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $totalOrgs }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Total Runs</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $totalRuns }}</dd>
        </div>
    </dl>
</div>
