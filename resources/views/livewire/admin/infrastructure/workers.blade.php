<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Python Scraping Workers</h2>
            <p class="mt-1 text-sm text-gray-500">Monitoring status detak jantung (heartbeat) dan alokasi konkurensi worker scraping Python.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">
        @foreach($workers as $w)
            <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $w['status'] === 'ONLINE' ? 'border-green-500' : 'border-red-500' }}">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">{{ $w['type'] }}</h3>
                        <p class="text-xs font-mono text-gray-500">ID: {{ $w['id'] }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $w['status'] === 'ONLINE' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $w['status'] }}
                    </span>
                </div>
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between"><span>Heartbeat Terakhir:</span> <span class="font-mono text-gray-900">{{ $w['last_heartbeat'] }}</span></div>
                    <div class="flex justify-between"><span>Batas Konkurensi:</span> <span class="font-bold text-gray-900">{{ $w['concurrency'] }} concurrent task</span></div>
                    <div class="flex justify-between"><span>Task Sedang Berjalan:</span> <span class="font-bold text-indigo-600">{{ $w['active_jobs'] }}</span></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
