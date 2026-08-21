<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Result Data</h2>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-x-3">
            <a href="{{ route('results.index') }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Back to Results
            </a>
        </div>
    </div>

    <div class="overflow-hidden bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-6 sm:px-6">
            <h3 class="text-base font-semibold leading-7 text-gray-900">Metadata</h3>
        </div>
        <div class="border-t border-gray-100">
            <dl class="divide-y divide-gray-100">
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Result ID</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $result->id }}</dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Run ID</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                        <a href="{{ route('runs.show', $result->run_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $result->run_id }}</a>
                    </dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Capability</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $result->capability }}</dd>
                </div>
                <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-900">Schema Version</dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{ $result->schema_version }}</dd>
                </div>
            </dl>
        </div>
    </div>
    
    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-6 sm:px-6">
            <h3 class="text-base font-semibold leading-7 text-gray-900">Extracted Data (JSON)</h3>
        </div>
        <div class="border-t border-gray-100 px-4 py-5 sm:p-6">
            <pre class="bg-gray-50 p-4 rounded-md overflow-x-auto text-sm text-gray-800">{{ json_encode(json_decode($result->data_payload), JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
</div>
