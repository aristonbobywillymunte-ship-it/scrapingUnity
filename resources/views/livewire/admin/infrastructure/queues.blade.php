<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Antrian &amp; Dead Letter Queue (Queues / DLQ)</h2>
            <p class="mt-1 text-sm text-gray-500">Observasi kedalaman antrian Redis dan riwayat eksekusi gagal di Dead Letter Queue.</p>
        </div>
    </div>

    <!-- Queues Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">
        @foreach($queues as $q)
            <div class="bg-white rounded-lg shadow p-5 border-l-4 border-indigo-500">
                <h3 class="text-sm font-bold text-gray-900 font-mono mb-2">{{ $q['name'] }}</h3>
                <div class="flex justify-between text-xs text-gray-600 mb-1"><span>Tipe:</span> <span class="font-semibold">{{ $q['type'] }}</span></div>
                <div class="flex justify-between text-xs text-gray-600 mb-1"><span>Target Worker:</span> <span class="font-mono text-indigo-600">{{ $q['target'] }}</span></div>
                <div class="flex justify-between text-xs text-gray-600"><span>Pending Tasks:</span> <span class="text-lg font-bold text-gray-900">{{ $q['pending'] }}</span></div>
            </div>
        @endforeach
    </div>

    <!-- DLQ Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Dead Letter Queue (DLQ) Records (Total: {{ $totalDlq }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capability</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Gagal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($dlqRecords as $d)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-red-600">{{ $d->error_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-indigo-600">{{ $d->capability }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($d->failed_at)->format('Y-m-d H:i:s') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                    <button wire:click="viewDetail('{{ $d->id }}')" class="text-indigo-600 hover:text-indigo-900 font-semibold">Diagnostik</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Dead Letter Queue bersih (tidak ada kegagalan).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $dlqRecords->links() }}
            </div>
        </div>
    </div>

    <!-- Diagnostic Modal -->
    @if($selectedRecord)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" role="dialog" aria-modal="true">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Detail Rekaman DLQ</h3>
                <div class="space-y-2 text-xs">
                    <div><span class="font-bold">Error Code:</span> <span class="text-red-600 font-mono font-bold">{{ $selectedRecord->error_code }}</span></div>
                    <div><span class="font-bold">Capability:</span> <span class="font-mono">{{ $selectedRecord->capability }}</span></div>
                    <div><span class="font-bold">Diagnostik:</span> <pre class="mt-1 p-2 bg-gray-900 text-green-400 font-mono text-xs rounded overflow-x-auto">{{ $selectedRecord->safe_diagnostics }}</pre></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="closeDetail" class="rounded-md bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
