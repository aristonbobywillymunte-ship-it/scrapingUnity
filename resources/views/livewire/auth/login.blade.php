<div>
    <form wire:submit="login" class="space-y-6">
        @if($error)
            <div class="rounded-md bg-red-50 p-4 mb-4">
                <div class="flex">
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
                    </div>
                </div>
            </div>
        @endif

        <div>
            <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email address</label>
            <div class="mt-2">
                <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
            </div>
            @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label>
            <div class="mt-2">
                <input wire:model="password" id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
            </div>
            @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between">
            <div class="text-sm leading-6">
                <a href="{{ route('password.recovery') }}" class="font-semibold text-indigo-600 hover:text-indigo-500">Forgot password?</a>
            </div>
        </div>

        <div>
            <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in...</span>
            </button>
        </div>
    </form>
</div>
