<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Manajemen Parser &amp; Siklus Hidup (Parser Lifecycle)</h2>
            <p class="mt-1 text-sm text-gray-500">Versi parser, deteksi kegagalan struktural, kandidat perbaikan AI, dan rollback.</p>
        </div>
    </div>

    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6"><p class="text-sm font-medium text-green-800">{{ $successMessage }}</p></div>
    @endif
    @if($errorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6"><p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p></div>
    @endif

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button wire:click="setTab('versions')" class="{{ $activeTab === 'versions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Versi Parser ({{ $counts['versions'] ?? 0 }})
            </button>
            <button wire:click="setTab('failures')" class="{{ $activeTab === 'failures' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Kegagalan Parser / Incidents ({{ $counts['failures'] ?? 0 }})
            </button>
            <button wire:click="setTab('candidates')" class="{{ $activeTab === 'candidates' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                Kandidat AI (0)
            </button>
        </nav>
    </div>

    @if($activeTab === 'versions')
        <div class="bg-white shadow sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform &amp; Scraper</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versi Tag</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Pembaruan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($versions as $v)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-gray-900">{{ $v->platform }} - {{ $v->scraper }} ({{ $v->page_type }})</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-indigo-600">{{ $v->version_tag }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $v->status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $v->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($v->updated_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                    @if($v->status !== 'ACTIVE')
                                        <button wire:click="requestRollback('{{ $v->id }}', '{{ $v->version_tag }}')" class="text-indigo-600 hover:text-indigo-900 font-semibold">Rollback ke Versi Ini</button>
                                    @else
                                        <span class="text-xs text-gray-400">Aktif Saat Ini</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada versi parser terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $versions->links() }}
            </div>
        </div>
    @elseif($activeTab === 'failures')
        <div class="bg-white shadow sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform &amp; Operasi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versi Parser</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas Kegagalan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesan Error</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($failures as $f)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-gray-900">{{ $f->platform }} - {{ $f->operation }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-600">{{ $f->parser_version ?? 'v1' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-red-600 font-semibold">{{ $f->failure_class }}</td>
                                <td class="px-6 py-4 text-xs text-gray-600 max-w-sm truncate">{{ $f->error_message }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($f->created_at)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada insiden kegagalan parser yang tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $failures->links() }}
            </div>
        </div>
    @else
        <div class="bg-white shadow sm:rounded-lg p-8 text-center">
            <p class="text-sm font-semibold text-gray-900">Belum Ada Kandidat Perbaikan AI</p>
            <p class="text-xs text-gray-500 mt-1">Kandidat perbaikan selector dihasilkan otomatis ketika terjadi kegagalan parser berulang dan telah divalidasi oleh Python engine.</p>
        </div>
    @endif

    <!-- Rollback Confirmation Modal -->
    @if($confirmingRollbackId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" role="dialog" aria-modal="true">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Konfirmasi Rollback Parser</h3>
                <p class="text-sm text-gray-600 mb-6">Anda akan mengaktifkan kembali versi parser <span class="font-mono font-semibold">{{ $confirmingRollbackTag }}</span>. Parser versi aktif saat ini akan dinonaktifkan.</p>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelRollback" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Batal</button>
                    <button wire:click="confirmRollback" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Ya, Rollback</button>
                </div>
            </div>
        </div>
    @endif
</div>
