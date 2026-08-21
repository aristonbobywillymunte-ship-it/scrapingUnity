<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Runs</h2>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="{{ route('runs.create') }}" class="ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                New Run
            </a>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($runs as $run)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">
                                        <a href="{{ route('runs.show', $run->id) }}">{{ $run->id }}</a>
                                    </p>
                                    <p class="truncate text-sm text-gray-500">{{ $run->capability }}</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $run->status === 'COMPLETED' ? 'bg-green-50 text-green-700 ring-green-600/20' : ($run->status === 'FAILED' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-yellow-50 text-yellow-800 ring-yellow-600/20') }}">
                                        {{ $run->status }}
                                    </span>
                                </div>
                                <div>
                                    <a href="{{ route('runs.show', $run->id) }}" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        View
                                    </a>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">
                            No runs found. Start your first run.
                        </li>
                    @endforelse
                </ul>
            </div>
            
            @if(method_exists($runs, 'links'))
                <div class="mt-6">
                    {{ $runs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
