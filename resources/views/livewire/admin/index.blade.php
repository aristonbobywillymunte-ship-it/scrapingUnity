<div>
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Manajemen Pengguna & Sistem (Admin)</h2>
            <p class="mt-1 text-sm text-gray-500">Buat, kelola akun pengguna, dan tetapkan alokasi kuota scraping.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $successMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- System Stats -->
    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-indigo-500">
            <dt class="truncate text-sm font-medium text-gray-500">Total Pengguna Terdaftar</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $totalUsers }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-indigo-500">
            <dt class="truncate text-sm font-medium text-gray-500">Total Organisasi / Tenant</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $totalOrgs }}</dd>
        </div>
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 border-l-4 border-indigo-500">
            <dt class="truncate text-sm font-medium text-gray-500">Total Pekerjaan Scraping (Runs)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">{{ $totalRuns }}</dd>
        </div>
    </dl>

    <!-- Create User Provisioning Form -->
    <div class="bg-white shadow sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900 mb-4">Buat & Aktivasi Pengguna Baru</h3>
            <form wire:submit.prevent="createUser" class="grid grid-cols-1 gap-y-4 sm:grid-cols-3 sm:gap-x-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Pengguna</label>
                    <input type="email" wire:model="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2" placeholder="user@domain.com" required>
                    @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Kata Sandi Awal</label>
                    <input type="password" wire:model="password" id="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2" placeholder="Min. 8 karakter" required>
                    @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="initialCredits" class="block text-sm font-medium text-gray-700">Alokasi Kuota Awal (Item)</label>
                    <input type="number" wire:model="initialCredits" id="initialCredits" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2" required>
                    @error('initialCredits') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        + Daftarkan & Aktivasi Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Table -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold leading-6 text-gray-900">Daftar Pengguna Aktif & Terdaftar</h3>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan email..." class="rounded-md border-gray-300 shadow-sm sm:text-sm border px-3 py-1.5 w-64">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Pengguna</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Akun</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $u)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500">{{ $u->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $u->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $u->status === 'ACTIVE' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20' }}">
                                        {{ $u->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if($u->email !== 'admin@example.com')
                                        <button wire:click="toggleUserStatus('{{ $u->id }}')" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold">
                                            {{ $u->status === 'ACTIVE' ? 'Tangguhkan (Suspend)' : 'Aktifkan Kembali' }}
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Root Admin</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    Tidak ada pengguna yang sesuai dengan kriteria pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($users, 'links'))
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
