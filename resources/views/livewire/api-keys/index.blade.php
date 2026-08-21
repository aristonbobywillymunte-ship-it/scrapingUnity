<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">API Keys</h2>
        </div>
    </div>

    @if($newKey)
        <div class="rounded-md bg-green-50 p-4 mb-8">
            <div class="flex">
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">API Key Created Successfully</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Please copy your new API key now. You won't be able to see it again!</p>
                        <p class="mt-2 font-mono bg-green-100 p-2 rounded break-all">{{ $newKey }}</p>
                    </div>
                    <div class="mt-4">
                        <div class="-mx-2 -my-1.5 flex">
                            <button type="button" wire:click="$set('newKey', null)" class="rounded-md bg-green-50 px-2 py-1.5 text-sm font-medium text-green-800 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 focus:ring-offset-green-50">Dismiss</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Generate New API Key</h3>
            <div class="mt-2 max-w-xl text-sm text-gray-500">
                <p>Create a new API key to authenticate requests from your applications.</p>
            </div>
            <form wire:submit="createKey" class="mt-5 sm:flex sm:items-center">
                <div class="w-full sm:max-w-xs">
                    <label for="name" class="sr-only">Key Name</label>
                    <input type="text" wire:model="name" id="name" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3" placeholder="e.g. Production Server">
                </div>
                <button type="submit" class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:ml-3 sm:mt-0 sm:w-auto">
                    Generate Key
                </button>
            </form>
            @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Active API Keys</h3>
            <div class="mt-5 flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($keys as $key)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $key->name }}</p>
                                    <p class="truncate text-sm text-gray-500">Prefix: {{ $key->key_prefix }}••••••••</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $key->status === 'ACTIVE' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20' }}">
                                        {{ $key->status }}
                                    </span>
                                </div>
                                <div>
                                    @if($key->status === 'ACTIVE')
                                        <button wire:click="revokeKey('{{ $key->id }}')" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            Revoke
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">
                            No API keys generated yet.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
