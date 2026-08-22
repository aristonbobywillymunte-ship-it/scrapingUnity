<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Pusat Operasional & Scraping Lab (Admin)</h2>
            <p class="mt-1 text-sm text-gray-500">Monitoring antrian eksekusi, worker runtime, pengujian manual Scraping Lab, dan log audit.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if($labSuccessMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $labSuccessMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if($labErrorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ $labErrorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Telemetry Metrics Grid -->
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
            <dt class="truncate text-sm font-medium text-gray-500">Python HTTP Worker Heartbeat</dt>
            <dd class="mt-1 text-2xl font-bold tracking-tight text-green-600">{{ $workerStatus }}</dd>
            <p class="mt-1 text-xs text-gray-400">Terakhir terlihat: {{ $workerHeartbeatTime }}</p>
        </div>
    </dl>

    <!-- Scraping Lab Form -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-2">Scraping Lab (Uji Manual & Diagnostik Admin)</h3>
            <p class="text-xs text-gray-500 mb-6">Uji eksekusi scraping langsung ke engine Python tanpa memotong kuota customer.</p>

            <form wire:submit.prevent="runScrapingLab" class="grid grid-cols-1 gap-y-4 sm:grid-cols-4 sm:gap-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Platform</label>
                    <select wire:model="labPlatform" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2">
                        <option value="facebook">Facebook (Tahap #1 POC)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Operasi Scraping</label>
                    <select wire:model="labOperation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2">
                        <option value="profile">Profile / Profil Akun</option>
                        <option value="single_post">Single Post / Postingan Tunggal</option>
                        <option value="profile_posts">Profile Posts / Linimasa Profil</option>
                        <option value="replies">Replies / Komentar Postingan</option>
                        <option value="search_posts">Search Posts / Pencarian Global</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Target / Kata Kunci</label>
                    <input type="text" wire:model="labTarget" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2" placeholder="zuck atau kata kunci..." required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Batas Item (Max)</label>
                    <input type="number" wire:model="labMaxItems" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2" required>
                </div>
                <div class="sm:col-span-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        ⚡ Jalankan Pengujian Scraping Lab
                    </button>
                </div>
            </form>

            @if($labResultPreview)
                <div class="mt-6 p-4 bg-gray-900 rounded-lg text-gray-100 font-mono text-xs overflow-x-auto">
                    <div class="text-green-400 font-bold mb-2">// Scraping Lab Dispatch Output:</div>
                    <pre>{{ json_encode($labResultPreview, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>
    </div>

    <!-- Proxy Pool Table -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Inventaris Proxy Pool (Kredensial Terenkripsi & Termasker)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host:Port</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Protokol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skor Kesehatan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latensi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username Terenkripsi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($proxies as $p)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-900">{{ $p->host }}:{{ $p->port }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 uppercase">{{ $p->protocol ?? 'HTTP' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $p->status === 'HEALTHY' ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-800' }}">
                                        {{ $p->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-semibold text-gray-900">{{ $p->health_score }}/100</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $p->latency_ms }} ms</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-400">•••••••• (Masked)</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-xs text-gray-500">
                                    Rotasi proxy internal aktif menggunakan alokasi pool default.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Logs Section -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Log Audit Keamanan & Operasional (Audit Trail)</h3>
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse($auditLogs as $log)
                        <li class="py-3">
                            <div class="flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-indigo-600">{{ $log->action }}</span>
                                    <span class="text-gray-500 ml-2 font-mono">{{ $log->target_resource }}</span>
                                </div>
                                <span class="text-gray-400">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-xs text-gray-500">
                            Belum ada entri log audit baru.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
