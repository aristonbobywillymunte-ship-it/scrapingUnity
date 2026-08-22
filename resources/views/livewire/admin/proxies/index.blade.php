<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Manajemen Proxy Pool</h2>
            <p class="mt-1 text-sm text-gray-500">Pengelolaan server proxy, pengujian kesehatan langsung, dan rotasi otomatis untuk scraper.</p>
        </div>
    </div>

    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6"><p class="text-sm font-medium text-green-800">{{ $successMessage }}</p></div>
    @endif
    @if($errorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6"><p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p></div>
    @endif

    <!-- Add Proxy Form -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Tambah Proxy Baru ke Pool</h3>
            <form wire:submit.prevent="addProxy" class="grid grid-cols-1 gap-y-4 sm:grid-cols-4 sm:gap-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Host (IP / Domain)</label>
                    <input type="text" wire:model="host" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" placeholder="192.168.1.100" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Port</label>
                    <input type="number" wire:model="port" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipe Proxy</label>
                    <select wire:model="proxyType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2">
                        <option value="datacenter">Datacenter</option>
                        <option value="residential">Residential</option>
                        <option value="mobile">Mobile</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kode Negara (2 Huruf)</label>
                    <input type="text" wire:model="countryCode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" placeholder="US" maxlength="2">
                </div>
                <div class="sm:col-span-4 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        + Tambah Proxy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Proxies Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Inventaris Proxy Pool (Total: {{ $stats['total'] }}, Sehat: {{ $stats['healthy'] }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host:Port</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skor (0-100)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latensi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Negara</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($proxies as $p)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-900">{{ $p->host }}:{{ $p->port }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 uppercase">{{ $p->proxy_type ?? 'datacenter' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $p->health_status === 'HEALTHY' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $p->health_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-gray-900">{{ $p->health_score }}/100</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $p->avg_latency_ms > 0 ? $p->avg_latency_ms . ' ms' : '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 uppercase">{{ $p->country_code ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-2">
                                    <button wire:click="testHealth('{{ $p->id }}')" class="text-indigo-600 hover:text-indigo-900 font-semibold">Uji Latensi</button>
                                    <button wire:click="toggleProxyStatus('{{ $p->id }}')" class="text-gray-600 hover:text-gray-900 font-semibold">Ubah Status</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada proxy terdaftar di pool.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $proxies->links() }}
            </div>
        </div>
    </div>
</div>
