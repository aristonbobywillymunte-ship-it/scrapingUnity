<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Billing & Credits</h2>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Current Balance</h3>
            <div class="mt-2 flex items-baseline gap-x-2">
                <span class="text-4xl font-semibold tracking-tight text-gray-900">{{ number_format($balance, 2) }}</span>
                <span class="text-sm text-gray-500">credits</span>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-5">Recent Transactions</h3>
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($transactions as $txn)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $txn->transaction_type }}</p>
                                    <p class="truncate text-sm text-gray-500">{{ \Carbon\Carbon::parse($txn->created_at)->format('Y-m-d H:i') }}</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center text-sm font-medium {{ $txn->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $txn->quantity > 0 ? '+' : '' }}{{ number_format($txn->quantity, 2) }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">
                            No billing transactions found.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
