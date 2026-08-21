<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Team Members</h2>
        </div>
    </div>
    
    @if($message)
        <div class="rounded-md bg-green-50 p-4 mb-4">
            <h3 class="text-sm font-medium text-green-800">{{ $message }}</h3>
        </div>
    @endif

    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Invite Member</h3>
            <form wire:submit="inviteMember" class="flex gap-4">
                <div class="flex-1">
                    <input type="email" wire:model="email" placeholder="Email address" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Invite
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flow-root">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    @forelse ($members as $member)
                        <li class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $member->user->email ?? 'Unknown User' }}</p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-gray-50 text-gray-600 ring-gray-500/10">
                                        {{ $member->role_id }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">
                            No members found.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
