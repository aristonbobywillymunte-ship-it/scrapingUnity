<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Log Audit Keamanan &amp; Tata Kelola (Audit Logs)</h2>
            <p class="mt-1 text-sm text-gray-500">Catatan append-only seluruh aktivitas administratif, perubahan status pengguna, dan insiden keamanan.</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-4 mb-6 flex gap-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari target atau aksi..." class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5 w-64">
        <select wire:model.live="actionFilter" class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5">
            <option value="">Semua Aksi</option>
            @foreach($actions as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metadata Aman</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $l)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-indigo-600">{{ $l->action }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-700">{{ $l->actor_email ?? $l->actor_type }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-600">{{ $l->target }}</td>
                            <td class="px-6 py-4 text-xs font-mono text-gray-500 max-w-xs truncate">{{ $l->safe_metadata }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($l->created_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada riwayat audit log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200">
            {{ $logs->links() }}
        </div>
    </div>
</div>
