<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Data Center (Seluruh Hasil Stored)</h2>
            <p class="mt-1 text-sm text-gray-500">Pusat observabilitas seluruh data scraping yang dikumpulkan across seluruh pengguna &amp; origin.</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button wire:click="setTab('all')" class="{{ $activeTab === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Semua Hasil ({{ $counts['all'] ?? 0 }})
            </button>
            <button wire:click="setTab('api')" class="{{ $activeTab === 'api' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                API Results ({{ $counts['api'] ?? 0 }})
            </button>
            <button wire:click="setTab('manual')" class="{{ $activeTab === 'manual' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Manual Results ({{ $counts['manual'] ?? 0 }})
            </button>
            <button wire:click="setTab('diagnostic')" class="{{ $activeTab === 'diagnostic' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Diagnostic / Failed ({{ $counts['diagnostic'] ?? 0 }})
            </button>
        </nav>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow sm:rounded-lg p-4 mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="flex gap-2 w-full sm:w-auto">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari konten teks atau URL..." class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5 w-64">
            <select wire:model.live="platformFilter" class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5">
                <option value="">Semua Platform</option>
                <option value="facebook">Facebook</option>
                <option value="instagram">Instagram</option>
                <option value="threads">Threads</option>
                <option value="twitter">X / Twitter</option>
            </select>
        </div>
    </div>

    <!-- Results Table -->
    @if($activeTab !== 'diagnostic')
        <div class="bg-white shadow sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform &amp; Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Konten / Ringkasan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Koleksi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($results as $r)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="font-bold text-gray-900 capitalize">{{ $r->platform }}</span>
                                    <span class="text-gray-400">/ {{ $r->entity_type }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $r->origin === 'API' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ $r->origin }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700 max-w-md truncate">
                                    {{ $r->text_content ?? $r->normalized_url }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                    <button wire:click="viewDetail('{{ $r->id }}')" class="text-indigo-600 hover:text-indigo-900 font-semibold">Inspeksi</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada hasil scraping yang tersimpan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($results)
                <div class="p-4 border-t border-gray-200">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    @else
        <!-- Diagnostic / Failed Table -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capability</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Gagal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($diagnosticItems as $d)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-red-600">{{ $d->error_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-700">{{ $d->error_category ?? 'EXECUTION_FAILURE' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500">{{ $d->capability }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($d->failed_at)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada data kegagalan / DLQ.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($diagnosticItems)
                <div class="p-4 border-t border-gray-200">
                    {{ $diagnosticItems->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Detail Modal -->
    @if($selectedResult)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" role="dialog" aria-modal="true">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 overflow-y-auto max-h-[80vh]">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Inspeksi Entitas Scraping</h3>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <div class="space-y-3 text-xs">
                    <div><span class="font-bold text-gray-600">Platform:</span> <span class="uppercase font-mono">{{ $selectedResult->platform }}</span></div>
                    <div><span class="font-bold text-gray-600">Entity Type:</span> <span class="font-mono">{{ $selectedResult->entity_type }}</span></div>
                    <div><span class="font-bold text-gray-600">URL Normalisasi:</span> <a href="{{ $selectedResult->normalized_url }}" target="_blank" class="text-indigo-600 truncate block">{{ $selectedResult->normalized_url }}</a></div>
                    <div><span class="font-bold text-gray-600">Identity Hash:</span> <span class="font-mono text-gray-500">{{ $selectedResult->identity_hash }}</span></div>
                    <div><span class="font-bold text-gray-600">Konten Teks:</span> <p class="mt-1 p-2 bg-gray-50 rounded border text-gray-800 whitespace-pre-wrap">{{ $selectedResult->text_content }}</p></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="closeDetail" class="rounded-md bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
