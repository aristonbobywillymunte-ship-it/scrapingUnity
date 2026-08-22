<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Seluruh Pekerjaan Scraping (Admin Jobs)</h2>
            <p class="mt-1 text-sm text-gray-500">Observasi seluruh antrian &amp; eksekusi scraping across all tenant/users.</p>
        </div>
    </div>

    <!-- Stats -->
    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-indigo-500"><dt class="text-xs text-gray-500">Total Jobs</dt><dd class="text-2xl font-bold text-indigo-600">{{ $stats['total'] }}</dd></div>
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500"><dt class="text-xs text-gray-500">Selesai (Completed)</dt><dd class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</dd></div>
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-500"><dt class="text-xs text-gray-500">Sedang Berjalan</dt><dd class="text-2xl font-bold text-yellow-600">{{ $stats['processing'] }}</dd></div>
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500"><dt class="text-xs text-gray-500">Gagal (Failed)</dt><dd class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</dd></div>
    </dl>

    <!-- Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organisasi / User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capability</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Dibuat</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($jobs as $j)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-900">{{ $j->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $j->organization_name ?? 'Internal / Default' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-indigo-600">{{ $j->capability }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $j->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : ($j->status === 'FAILED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ $j->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500">{{ $j->origin }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($j->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada riwayat pekerjaan scraping.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200">
            {{ $jobs->links() }}
        </div>
    </div>
</div>
