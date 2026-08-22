<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Pusat Kendali Admin (Dashboard)</h2>
            <p class="mt-1 text-sm text-gray-500">Ringkasan operasional menyeluruh, metrik realtime, antrian, dan integritas sistem.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-2">
            <a href="{{ route('admin.operations') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                ⚡ Buka Scraping Lab
            </a>
        </div>
    </div>

    <!-- Telemetry Cards Grid -->
    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-indigo-500">
            <dt class="truncate text-sm font-medium text-gray-500">Total Pengguna Terdaftar</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $totalUsers }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-blue-500">
            <dt class="truncate text-sm font-medium text-gray-500">Total Eksekusi (Runs)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-blue-600">{{ $totalRuns }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-emerald-500">
            <dt class="truncate text-sm font-medium text-gray-500">Hasil Scraping Tersimpan</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-emerald-600">{{ $totalResults }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-red-500">
            <dt class="truncate text-sm font-medium text-gray-500">Dead Letter Queue (DLQ)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-red-600">{{ $failedJobs }}</dd>
        </div>
    </dl>

    <!-- Operational State Grid -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
        <!-- Worker Heartbeat -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="inline-block size-2 rounded-full {{ $workerOnline ? 'bg-green-500' : 'bg-red-500' }}"></span>
                Python HTTP Worker Status
            </h3>
            <p class="text-xs text-gray-600 mb-1">Status: <span class="font-bold {{ $workerOnline ? 'text-green-700' : 'text-red-600' }}">{{ $workerOnline ? 'ACTIVE / ONLINE' : 'OFFLINE' }}</span></p>
            <p class="text-xs text-gray-400">Heartbeat: {{ $workerHb }}</p>
        </div>

        <!-- Proxy Health -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Proxy Pool Inventory</h3>
            <p class="text-xs text-gray-600 mb-1">Total Aktif: <span class="font-bold text-gray-900">{{ $totalProxies }}</span></p>
            <p class="text-xs text-gray-500">Sehat (Healthy): <span class="font-bold text-green-700">{{ $healthyProxies }}</span></p>
        </div>

        <!-- Parser Incidents -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Parser Health Incidents</h3>
            <p class="text-xs text-gray-600 mb-1">Insiden Kerusakan: <span class="font-bold {{ $totalParserFailures > 0 ? 'text-red-600' : 'text-green-700' }}">{{ $totalParserFailures }}</span></p>
            <p class="text-xs text-gray-400">Insiden struktural otomatis dicatat</p>
        </div>
    </div>

    <!-- Quick Navigation Links -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Navigasi Langsung Administrasi</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-semibold">
            <a href="{{ route('admin.users.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">👥 Manajemen Pengguna</a>
            <a href="{{ route('admin.plans.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">📦 Paket &amp; Kuota</a>
            <a href="{{ route('admin.data-center.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">🗄️ Data Center</a>
            <a href="{{ route('admin.jobs.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">📋 Pekerjaan Scraping</a>
            <a href="{{ route('admin.platforms.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">🌐 Platform Registry</a>
            <a href="{{ route('admin.parser.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">⚙️ Versi Parser &amp; Rollback</a>
            <a href="{{ route('admin.proxies.index') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">🛡️ Proxy Pool</a>
            <a href="{{ route('admin.system.audit-logs') }}" class="p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:text-indigo-600">🔒 Log Audit Sistem</a>
        </div>
    </div>
</div>
