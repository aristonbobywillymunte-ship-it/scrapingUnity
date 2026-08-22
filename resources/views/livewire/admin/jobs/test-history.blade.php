<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Riwayat Pengujian Lab (Test History)</h2>
            <p class="mt-1 text-sm text-gray-500">Log pengujian manual Scraping Lab dan eksekusi diagnostik Admin.</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Execution / Job ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capability</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tests as $t)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-900">{{ $t->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-indigo-600">{{ $t->capability }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-purple-700 font-semibold">{{ $t->origin }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $t->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $t->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($t->created_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada riwayat pengujian manual Scraping Lab.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200">
            {{ $tests->links() }}
        </div>
    </div>
</div>
