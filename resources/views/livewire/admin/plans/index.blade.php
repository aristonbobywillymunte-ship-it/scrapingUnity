<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Paket &amp; Alokasi Kuota (Plans &amp; Quota)</h2>
            <p class="mt-1 text-sm text-gray-500">Definisi paket langganan internal, batas kuota bulanan, rate limiting, dan retensi data.</p>
        </div>
    </div>

    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6">
            <p class="text-sm font-medium text-green-800">{{ $successMessage }}</p>
        </div>
    @endif

    <!-- Create Plan Form -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Buat Paket / Plan Baru</h3>
            <form wire:submit.prevent="createPlan" class="grid grid-cols-1 gap-y-4 sm:grid-cols-4 sm:gap-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Paket</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" placeholder="Pro Tier 10K" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kuota Bulanan (Item)</label>
                    <input type="number" wire:model="monthlyQuota" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Rate Limit (Req/Min)</label>
                    <input type="number" wire:model="rateLimitRpm" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Maks. Konkurensi</label>
                    <input type="number" wire:model="maxConcurrency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" required>
                </div>
                <div class="sm:col-span-4 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        + Tambah Paket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Plans Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Daftar Paket Aktif</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Paket</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durasi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batas Entitlement</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($plans as $p)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $p->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700">
                                        {{ $p->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $p->duration_days }} hari</td>
                                <td class="px-6 py-4 text-xs font-mono text-gray-700">{{ $p->limits }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-xs text-gray-500">Belum ada paket kustom yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
