<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Manajemen Pengguna (Admin)</h2>
            <p class="mt-1 text-sm text-gray-500">Pendaftaran akun, pengaturan status, dan alokasi kuota scraping.</p>
        </div>
    </div>

    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6">
            <p class="text-sm font-medium text-green-800">{{ $successMessage }}</p>
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p>
        </div>
    @endif

    <!-- Create User Form -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Buat Pengguna Baru</h3>
            <form wire:submit.prevent="createUser" class="grid grid-cols-1 gap-y-4 sm:grid-cols-3 sm:gap-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" placeholder="user@company.com" required>
                    @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kata Sandi Awal</label>
                    <input type="password" wire:model="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" placeholder="Min. 8 karakter" required>
                    @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Alokasi Kuota Awal (Item)</label>
                    <input type="number" wire:model="initialCredits" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-2" required>
                    @error('initialCredits') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        + Daftarkan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-4">
                <div class="flex gap-2 w-full sm:w-auto">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari email pengguna..." class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5 w-64">
                    <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5">
                        <option value="">Semua Status</option>
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="SUSPENDED">SUSPENDED</option>
                        <option value="DISABLED">DISABLED</option>
                    </select>
                </div>
                <span class="text-xs text-gray-500">Total: {{ $totalUsers }} pengguna</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terdaftar</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $u)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $u->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $u->status === 'ACTIVE' ? 'bg-green-50 text-green-700 ring-green-600/20' : ($u->status === 'SUSPENDED' ? 'bg-yellow-50 text-yellow-800 ring-yellow-600/20' : 'bg-red-50 text-red-700 ring-red-600/20') }}">
                                        {{ $u->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-2">
                                    @if($u->status === 'ACTIVE')
                                        <button wire:click="requestStatusChange('{{ $u->id }}', 'suspend')" class="text-yellow-600 hover:text-yellow-900 font-semibold">Tangguhkan</button>
                                        <button wire:click="requestStatusChange('{{ $u->id }}', 'disable')" class="text-red-600 hover:text-red-900 font-semibold">Nonaktifkan</button>
                                    @else
                                        <button wire:click="requestStatusChange('{{ $u->id }}', 'activate')" class="text-green-600 hover:text-green-900 font-semibold">Aktifkan Kembali</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada pengguna ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    @if($confirmingUserId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" role="dialog" aria-modal="true">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Konfirmasi Perubahan Status</h3>
                <p class="text-sm text-gray-600 mb-1">Pengguna: <span class="font-mono font-semibold">{{ $confirmingUserEmail }}</span></p>
                <p class="text-sm text-gray-600 mb-6">Aksi: <span class="font-bold uppercase text-indigo-600">{{ $confirmingAction }}</span></p>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelConfirmation" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Batal</button>
                    <button wire:click="confirmStatusChange" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Ya, Lanjutkan</button>
                </div>
            </div>
        </div>
    @endif
</div>
