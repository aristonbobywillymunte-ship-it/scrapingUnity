<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Security Settings</h2>
        </div>
    </div>

    @if($message)
        <div class="rounded-md bg-green-50 p-4 mb-6">
            <h3 class="text-sm font-medium text-green-800">{{ $message }}</h3>
        </div>
    @endif
    
    @if($error)
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
        </div>
    @endif

    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Change Password</h3>
            <form wire:submit="updatePassword" class="mt-5 space-y-4 max-w-xl">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                    <input wire:model="current_password" type="password" id="current_password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input wire:model="new_password" type="password" id="new_password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
                    @error('new_password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input wire:model="new_password_confirmation" type="password" id="new_password_confirmation" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
                </div>
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Update Password
                </button>
            </form>
        </div>
    </div>
    
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Active Sessions</h3>
            <ul role="list" class="-my-5 divide-y divide-gray-200">
                @forelse($sessions as $session)
                    <li class="py-4 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">IP: {{ $session->ip_address }}</p>
                            <p class="text-sm text-gray-500">Created: {{ $session->created_at }}</p>
                        </div>
                        <button wire:click="revokeSession('{{ $session->id }}')" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Revoke
                        </button>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-500">No active sessions.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
