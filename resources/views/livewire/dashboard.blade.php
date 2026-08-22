<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Dashboard Ringkasan</h2>
            <p class="mt-1 text-sm text-gray-500">Statistik scraping dan status pekerjaan data Anda.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="{{ route('runs.create') }}" class="ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                + Buat Job Baru
            </a>
        </div>
    </div>

    <div>
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-4">
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Job Sedang Berjalan</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $activeRunsCount }}</dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Job Selesai</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-green-600">{{ $completedRunsCount }}</dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Job Gagal</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-red-600">{{ $failedRunsCount }}</dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Penggunaan Kuota</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ number_format($creditUsage, 0) }} item</dd>
            </div>
        </dl>
    </div>
</div>
