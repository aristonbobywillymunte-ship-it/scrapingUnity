<div>
    <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Reset Password</h2>
    @if($error)
        <div class="rounded-md bg-red-50 p-4 mt-8 mb-4">
            <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
        </div>
    @endif
    @if($sent)
        <div class="rounded-md bg-green-50 p-4 mt-8">
            <h3 class="text-sm font-medium text-green-800">If an account exists, a recovery link has been sent.</h3>
        </div>
        <div class="mt-4 text-center">
            <a href="{{ route('password.reset') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Continue to Enter OTP</a>
        </div>
    @else
        <form wire:submit="recover" class="mt-8 space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email address</label>
                <input wire:model="email" id="email" type="email" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 px-3">
                @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="channel" class="block text-sm font-medium leading-6 text-gray-900">Delivery Channel</label>
                <select wire:model="channel" id="channel" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 px-3">
                    <option value="EMAIL">Email</option>
                    <option value="WHATSAPP">WhatsApp</option>
                </select>
                @error('channel') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="recover">Send Recovery OTP</span>
                <span wire:loading wire:target="recover">Sending...</span>
            </button>
        </form>
    @endif
</div>
