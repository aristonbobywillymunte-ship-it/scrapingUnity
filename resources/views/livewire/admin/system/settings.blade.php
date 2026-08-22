<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Pengaturan Sistem (System Settings)</h2>
            <p class="mt-1 text-sm text-gray-500">Pengaturan retensi data, batas konkurensi default, dan parameter batas scraping.</p>
        </div>
    </div>

    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6"><p class="text-sm font-medium text-green-800">{{ $successMessage }}</p></div>
    @endif
    @if($errorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6"><p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p></div>
    @endif

    <div class="bg-white shadow sm:rounded-lg">
        <form wire:submit.prevent="saveSettings" class="px-4 py-5 sm:p-6 space-y-6">
            @foreach($definitions as $d)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center pb-4 border-b border-gray-100 last:border-0">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 font-mono">{{ $d->key }}</label>
                        <span class="text-xs text-gray-500">{{ $d->description }}</span>
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" wire:model="settings.{{ $d->key }}" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2">
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    💾 Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
