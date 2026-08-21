<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Run Details</h2>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-x-3">
            @if(in_array($run->status, ['QUEUED', 'IN_PROGRESS', 'LEASED']))
                <button wire:click="cancelRun" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <span wire:loading.remove wire:target="cancelRun">Cancel Run</span>
                    <span wire:loading wire:target="cancelRun">Canceling...</span>
                </button>
            @endif
            <a href="{{ route('runs.index') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Back to Runs
            </a>
        </div>
    </div>

    @if($error)
        <div class="rounded-md bg-red-50 p-4 mb-4">
            <div class="flex">
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-6 sm:px-6">
            <h3 class="text-base font-semibold leading-7 text-gray-900">Run Information</h3>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-500">Details and metadata about this extraction run.</p>
        </div>
        <div class="border-t border-gray-100">
            <dl class="divide-y divide-gray-100">
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Run ID</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $run->id }}</dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Capability</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $run->capability }}</dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Status</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $run->status === 'COMPLETED' ? 'bg-green-50 text-green-700 ring-green-600/20' : ($run->status === 'FAILED' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-yellow-50 text-yellow-800 ring-yellow-600/20') }}">
                            {{ $run->status }}
                        </span>
                    </dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Created At</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $run->created_at }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
