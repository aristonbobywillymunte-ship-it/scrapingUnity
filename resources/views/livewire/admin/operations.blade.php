<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Operations</h2>
        </div>
    </div>
    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Pending Tasks (Queue)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $pendingTasks }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-red-500">Failed Jobs (DLQ)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $failedJobs }}</dd>
        </div>
    </dl>
</div>
