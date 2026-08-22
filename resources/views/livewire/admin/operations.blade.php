<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Pusat Operasional & Scraping Lab (Admin)</h2>
            <p class="mt-1 text-sm text-gray-500">Monitoring antrian eksekusi, worker runtime, dan analisis kegagalan sistem.</p>
        </div>
    </div>

    <!-- Metrics Grid -->
    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-indigo-500">
            <dt class="truncate text-sm font-medium text-gray-500">Antrian Eksekusi (Queue Pending)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $pendingTasks }}</dd>
            <p class="mt-1 text-xs text-gray-400">Pekerjaan menunggu alokasi worker</p>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-red-500">
            <dt class="truncate text-sm font-medium text-gray-500">Dead Letter Queue (DLQ / Gagal)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-red-600">{{ $failedJobs }}</dd>
            <p class="mt-1 text-xs text-gray-400">Eksekusi gagal yang membutuhkan audit</p>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-green-500">
            <dt class="truncate text-sm font-medium text-gray-500">Status Engine Scraping</dt>
            <dd class="mt-1 text-2xl font-bold tracking-tight text-green-600">AKTIF (Python Worker)</dd>
            <p class="mt-1 text-xs text-gray-400">Redis queue: <code class="text-indigo-600 font-mono">scrape:executions</code></p>
        </div>
    </dl>

    <!-- Operational Information Section -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Informasi Arsitektur Runtime</h3>
            <div class="space-y-3 text-sm text-gray-600">
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Platform Kontrol</span>
                    <span class="text-gray-500">Laravel 11 + Livewire 3 (Port 8000)</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Scraping Data Plane</span>
                    <span class="text-gray-500">Python 3.9 + HTTP Scraper Adapter</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-700">Dukungan Provider SaaS</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Mandiri (No Apify)</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="font-medium text-gray-700">Platform Aktif (Tahap Saat Ini)</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Facebook POC (Direct Scraping)</span>
                </div>
            </div>
        </div>
    </div>
</div>
