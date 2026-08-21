<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">New Run</h2>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <form wire:submit="submit" class="space-y-6 max-w-2xl">
                @if($error)
                    <div class="rounded-md bg-red-50 p-4 mb-4">
                        <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
                    </div>
                @endif
                
                <div>
                    <label for="capability" class="block text-sm font-medium text-gray-700">Capability</label>
                    <select wire:model.live="capability" id="capability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
                        @foreach($this->capabilities as $capId => $cap)
                            <option value="{{ $capId }}">{{ $cap['label'] }} ({{ $cap['cost'] }} cr)</option>
                        @endforeach
                    </select>
                </div>

                @if(isset($this->capabilities[$capability]))
                    @php $fields = $this->capabilities[$capability]['fields']; @endphp
                    
                    @if(in_array('target_url', $fields))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Target URL</label>
                            <input type="text" wire:model="target_url" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            @error('target_url') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    
                    @if(in_array('search_query', $fields))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Search Query</label>
                            <input type="text" wire:model="search_query" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            @error('search_query') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    
                    @if(in_array('keyword', $fields))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Keyword</label>
                            <input type="text" wire:model="keyword" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            @error('keyword') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    
                    @if(in_array('max_pages', $fields))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Pages</label>
                            <input type="number" wire:model="max_pages" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border w-32">
                            @error('max_pages') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif
                @endif
                
                <div class="pt-5 flex justify-end gap-3">
                    <a href="{{ route('runs.index') }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Start Run
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
