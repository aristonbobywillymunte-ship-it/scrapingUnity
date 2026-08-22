<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Status Kesehatan Platform (Platform Health)</h2>
            <p class="mt-1 text-sm text-gray-500">Monitoring status circuit breaker, tingkat keberhasilan scraping, dan latensi per platform sosial media.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        @foreach($platformMetrics as $pm)
            <div class="bg-white rounded-lg shadow p-5 border-t-4 {{ $pm['status'] === 'HEALTHY' ? 'border-green-500' : 'border-gray-300' }}">
                <h3 class="text-base font-bold text-gray-900 mb-2">{{ $pm['platform'] }}</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-gray-500">Status:</span> <span class="font-bold {{ $pm['status'] === 'HEALTHY' ? 'text-green-700' : 'text-gray-500' }}">{{ $pm['status'] }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Circuit Breaker:</span> <span class="font-mono text-gray-700">{{ $pm['circuit_state'] }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Success Rate:</span> <span class="font-bold text-indigo-600">{{ $pm['success_rate'] }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Latensi Rata-rata:</span> <span class="text-gray-700">{{ $pm['avg_latency'] }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Sukses Terakhir:</span> <span class="text-gray-700">{{ $pm['last_success'] }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Gagal Terakhir:</span> <span class="text-gray-700">{{ $pm['last_failure'] }}</span></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
