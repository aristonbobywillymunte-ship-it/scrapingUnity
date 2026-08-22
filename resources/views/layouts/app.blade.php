<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Social Data Service' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased font-sans text-gray-900">
    @auth
        @php
            $isAdmin = auth()->user()->organizationMemberships()->where('role_id', 'owner')->exists() ||
                       auth()->user()->email === 'admin@example.com';
        @endphp
        <div x-data="{ sidebarOpen: false }" class="min-h-full">
            <!-- Mobile Off-canvas Sidebar -->
            <div x-show="sidebarOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true" style="display: none;">
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/80"></div>
                <div class="fixed inset-0 flex">
                    <div x-show="sidebarOpen" x-transition class="relative mr-16 flex w-full max-w-xs flex-1">
                        <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                            <button type="button" @click="sidebarOpen = false" class="-m-2.5 p-2.5 text-white">
                                <span class="sr-only">Tutup menu</span>
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4">
                            <div class="flex h-16 shrink-0 items-center border-b border-gray-100">
                                <span class="text-xl font-black tracking-tight text-indigo-600">ScrapingPlatform</span>
                            </div>
                            <nav class="flex flex-1 flex-col">
                                <ul role="list" class="flex flex-1 flex-col gap-y-6">
                                    <li>
                                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Aplikasi Pengguna</div>
                                        <ul role="list" class="-mx-2 space-y-1">
                                            <li><a href="{{ route('dashboard') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Dashboard</a></li>
                                            <li><a href="{{ route('runs.index') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('runs.*') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Pekerjaan / Jobs</a></li>
                                            <li><a href="{{ route('results.index') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('results.*') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Hasil Scraping</a></li>
                                            <li><a href="{{ route('api-keys') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('api-keys') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Kunci API (API Keys)</a></li>
                                            <li><a href="{{ route('billing') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('billing') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Kuota & Penggunaan</a></li>
                                        </ul>
                                    </li>

                                    @if($isAdmin)
                                        <li>
                                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Pusat Kontrol Admin</div>
                                            <ul role="list" class="-mx-2 space-y-1">
                                                <li><a href="{{ route('admin') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('admin') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Admin Overview</a></li>
                                                <li><a href="{{ route('admin.operations') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('admin.operations') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Scraping Lab & Ops</a></li>
                                            </ul>
                                        </li>
                                    @endif

                                    <li class="mt-auto border-t border-gray-100 pt-4">
                                        <div class="text-xs text-gray-500 font-medium truncate mb-2">{{ auth()->user()?->email }}</div>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left rounded-lg p-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                                                Keluar (Logout)
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Static Sidebar -->
            <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-64 lg:flex-col">
                <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4">
                    <div class="flex h-16 shrink-0 items-center border-b border-gray-100">
                        <span class="text-xl font-black tracking-tight text-indigo-600">ScrapingPlatform</span>
                    </div>
                    <nav class="flex flex-1 flex-col">
                        <ul role="list" class="flex flex-1 flex-col gap-y-6">
                            <li>
                                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Aplikasi Pengguna</div>
                                <ul role="list" class="-mx-2 space-y-1">
                                    <li><a href="{{ route('dashboard') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Dashboard</a></li>
                                    <li><a href="{{ route('runs.index') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('runs.*') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Pekerjaan / Jobs</a></li>
                                    <li><a href="{{ route('results.index') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('results.*') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Hasil Scraping</a></li>
                                    <li><a href="{{ route('api-keys') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('api-keys') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Kunci API (API Keys)</a></li>
                                    <li><a href="{{ route('billing') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('billing') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Kuota & Penggunaan</a></li>
                                </ul>
                            </li>

                            @if($isAdmin)
                                <li>
                                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Pusat Kontrol Admin</div>
                                    <ul role="list" class="-mx-2 space-y-1">
                                        <li><a href="{{ route('admin') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('admin') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Admin Overview</a></li>
                                        <li><a href="{{ route('admin.operations') }}" class="group flex gap-x-3 rounded-lg p-2 text-sm font-semibold hover:bg-indigo-50 hover:text-indigo-600 {{ request()->routeIs('admin.operations') ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' }}">Scraping Lab & Ops</a></li>
                                    </ul>
                                </li>
                            @endif

                            <li class="mt-auto border-t border-gray-100 pt-4">
                                <div class="text-xs text-gray-500 font-medium truncate mb-2">{{ auth()->user()?->email }}</div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left rounded-lg p-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                                        Keluar (Logout)
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Top Header & Main Content -->
            <div class="lg:pl-64">
                <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
                    <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-gray-700 lg:hidden">
                        <span class="sr-only">Buka menu</span>
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-x-3">
                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Sistem Aktif</span>
                    </div>
                    <div class="flex items-center gap-x-4">
                        <span class="text-sm font-medium text-gray-700">{{ auth()->user()?->email }}</span>
                    </div>
                </div>

                <main class="py-8">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    @else
        <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-md w-full space-y-8">
                {{ $slot }}
            </div>
        </div>
    @endauth

    @livewireScripts
</body>
</html>
