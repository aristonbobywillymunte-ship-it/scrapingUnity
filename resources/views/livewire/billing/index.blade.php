<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Kuota & Penggunaan Data</h2>
            <p class="mt-1 text-sm text-gray-500">Pantau saldo kuota scraping dan riwayat konsumsi data.</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Sisa Kuota Tersedia</h3>
            <div class="mt-2 flex items-baseline gap-x-2">
                <span class="text-4xl font-semibold tracking-tight text-indigo-600">{{ number_format($balance, 2) }}</span>
                <span class="text-sm text-gray-500">item data</span>
            </div>
            <p class="mt-2 text-xs text-gray-400">Kuota dialokasikan oleh Administrator sesuai paket langganan Anda.</p>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-5">Riwayat Penggunaan Kuota</h3>
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($transactions as $txn)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $txn->transaction_type === 'USAGE' ? 'Penggunaan Scraping' : 'Penambahan Kuota' }}</p>
                                    <p class="truncate text-sm text-gray-500">{{ \Carbon\Carbon::parse($txn->created_at)->format('Y-m-d H:i') }}</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center text-sm font-medium {{ $txn->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $txn->quantity > 0 ? '+' : '' }}{{ number_format($txn->quantity, 0) }} item
                                    </span>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-gray-500">
                            Belum ada riwayat penggunaan kuota.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
