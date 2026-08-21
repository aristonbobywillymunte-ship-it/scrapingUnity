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

                @if($this->currentCapability)
                    @php
                        $supportedModes = $this->currentCapability['supported_modes'] ?? ['target'];
                        $isChild = ($this->currentCapability['type'] ?? '') === 'child';
                    @endphp

                    {{-- Mode Selector: Only show if capability supports multiple modes --}}
                    @if(count($supportedModes) > 1)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian Berdasarkan</label>
                            <div class="flex gap-4">
                                @if(in_array('search_query', $supportedModes))
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model.live="discovery_mode" value="search_query" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                        <span class="ml-2 text-sm text-gray-900 font-medium">Kata Kunci</span>
                                    </label>
                                @endif

                                @if(in_array('hashtag', $supportedModes))
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model.live="discovery_mode" value="hashtag" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                        <span class="ml-2 text-sm text-gray-900 font-medium">Hashtag</span>
                                    </label>
                                @endif

                                @if(in_array('target', $supportedModes))
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model.live="discovery_mode" value="target" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                        <span class="ml-2 text-sm text-gray-900 font-medium">Target URL</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Input Field: Search Query --}}
                    @if($discovery_mode === 'search_query' && in_array('search_query', $supportedModes))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kata Kunci</label>
                            <input type="text" wire:model="search_query" placeholder="Contoh: politik indonesia" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            <p class="mt-1 text-xs text-gray-500">Masukkan kata atau frasa untuk menemukan konten yang relevan.</p>
                            @error('search_query') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    {{-- Input Field: Hashtag --}}
                    @if($discovery_mode === 'hashtag' && in_array('hashtag', $supportedModes))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Hashtag</label>
                            <input type="text" wire:model="hashtag" placeholder="Contoh: #politik atau politik" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            <p class="mt-1 text-xs text-gray-500">Masukkan hashtag yang ingin dipantau (dengan atau tanpa tanda #).</p>
                            @error('hashtag') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    {{-- Input Field: Target Konten / URL --}}
                    @if($discovery_mode === 'target' || $isChild)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                {{ $this->currentCapability['target_label'] ?? 'Target Konten (URL atau ID)' }}
                            </label>
                            <input type="text" wire:model="target" placeholder="Contoh: https://... atau ID konten" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                            <p class="mt-1 text-xs text-gray-500">
                                @if($isChild)
                                    Masukkan URL atau ID parent konten tempat komentar/balasan akan dikumpulkan.
                                @else
                                    Masukkan URL spesifik target yang ingin di-scrape.
                                @endif
                            </p>
                            @error('target') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max Pages / Depth</label>
                        <input type="number" wire:model="max_pages" min="1" max="100" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm px-3 py-2 border">
                        @error('max_pages') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
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
