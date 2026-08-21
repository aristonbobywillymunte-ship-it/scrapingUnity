<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Results</h2>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($results as $result)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">
                                        <a href="{{ route('results.show', $result->id) }}">Result ID: {{ $result->id }}</a>
                                    </p>
                                    <p class="truncate text-sm text-gray-500">Run: {{ $result->run_id }} ({{ $result->capability }})</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-600/20">
                                        {{ $result->schema_version }}
                                    </span>
                                </div>
                                <div>
                                    <a href="{{ route('results.show', $result->id) }}" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        View Data
                                    </a>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">
                            No results found.
                        </li>
                    @endforelse
                </ul>
            </div>
            
            @if(method_exists($results, 'links'))
                <div class="mt-6">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
