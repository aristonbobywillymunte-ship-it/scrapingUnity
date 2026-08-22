<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Log Operasional Sistem (System Logs)</h2>
            <p class="mt-1 text-sm text-gray-500">Pembacaan aman log aplikasi (100 baris terbaru, kredensial dan rahasia ter-sanitasi otomatis).</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-4 mb-4 flex gap-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari dalam log..." class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5 w-64">
        <select wire:model.live="levelFilter" class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5">
            <option value="">Semua Level</option>
            <option value="ERROR">ERROR</option>
            <option value="INFO">INFO</option>
            <option value="WARNING">WARNING</option>
        </select>
    </div>

    <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-100 overflow-x-auto max-h-[600px] overflow-y-auto space-y-1">
        @forelse($logLines as $line)
            <div class="{{ str_contains($line, 'ERROR') ? 'text-red-400 font-semibold' : (str_contains($line, 'WARNING') ? 'text-yellow-400' : 'text-gray-300') }}">
                {{ $line }}
            </div>
        @empty
            <div class="text-gray-500 text-center py-4">Tidak ada entri log yang cocok.</div>
        @endforelse
    </div>
</div>
