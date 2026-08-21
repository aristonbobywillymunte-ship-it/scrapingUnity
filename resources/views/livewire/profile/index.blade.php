<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">My Profile</h2>
        </div>
    </div>
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">User Information</h3>
            <div class="mt-5 border-t border-gray-200">
                <dl class="divide-y divide-gray-200">
                    <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5">
                        <dt class="text-sm font-medium text-gray-500">Email address</dt>
                        <dd class="mt-1 flex text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                            <span class="flex-grow">{{ $user->email }}</span>
                        </dd>
                    </div>
                    <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5">
                        <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                        <dd class="mt-1 flex text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                            <span class="flex-grow">{{ $user->status }}</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
