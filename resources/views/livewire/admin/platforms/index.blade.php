<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Platform Capability Registry</h2>
            <p class="mt-1 text-sm text-gray-500">Daftar kapabilitas platform, metode scraping yang didukung (HTTP / Browser), batas item, dan versi parser aktif.</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform &amp; Operasi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Engine Dukungan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Maks. Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cache TTL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parser Aktif</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($capabilities as $c)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <span class="font-bold text-gray-900">{{ $c['platform'] }}</span>
                                <span class="text-gray-500">({{ $c['operation'] }})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $c['status'] === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $c['status'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                @if($c['http_supported']) <span class="text-indigo-600 font-semibold">HTTP</span> @endif
                                @if($c['browser_supported']) <span class="text-blue-600 font-semibold">Browser</span> @endif
                                @if(!$c['http_supported'] && !$c['browser_supported']) <span class="text-gray-400">N/A</span> @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-700">{{ $c['max_items'] > 0 ? $c['max_items'] . ' items' : '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $c['cache_ttl_sec'] > 0 ? ($c['cache_ttl_sec'] / 60) . ' mnt' : '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-700">{{ $c['active_parser'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
