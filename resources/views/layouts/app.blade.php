<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Data Extraction Platform' }}</title>
    <!-- Tailwind via CDN for rapid development if vite is not running -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    @auth
        <div x-data="{ sidebarOpen: false }" class="min-h-full">
            <!-- Off-canvas menu for mobile -->
            <div x-show="sidebarOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true" style="display: none;">
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/80"></div>
                <div class="fixed inset-0 flex">
                    <div x-show="sidebarOpen" x-transition class="relative mr-16 flex w-full max-w-xs flex-1">
                        <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                            <button type="button" @click="sidebarOpen = false" class="-m-2.5 p-2.5 text-white">
                                <span class="sr-only">Close sidebar</span>
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4">
                            <div class="flex h-16 shrink-0 items-center">
                                <span class="text-2xl font-bold text-indigo-600">Extract</span>
                            </div>
                            <nav class="flex flex-1 flex-col">
                                <ul role="list" class="flex flex-1 flex-col gap-y-7">
                                    <li>
                                        <ul role="list" class="-mx-2 space-y-1">
                                            <li><a href="{{ route('dashboard') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('dashboard') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Dashboard</a></li>
                                            <li><a href="{{ route('runs.index') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('runs.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Runs</a></li>
                                            <li><a href="{{ route('results.index') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('results.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Results</a></li>
                                            <li><a href="{{ route('api-keys') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('api-keys') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">API Keys</a></li>
                                            <li><a href="{{ route('billing') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('billing') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Billing</a></li>
                                            <li><a href="{{ route('organization.team') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('organization.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Team</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Static sidebar for desktop -->
            <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
                <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4">
                    <div class="flex h-16 shrink-0 items-center">
                        <span class="text-2xl font-bold text-indigo-600">Extract</span>
                    </div>
                    <nav class="flex flex-1 flex-col">
                        <ul role="list" class="flex flex-1 flex-col gap-y-7">
                            <li>
                                <ul role="list" class="-mx-2 space-y-1">
                                    <li><a href="{{ route('dashboard') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('dashboard') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Dashboard</a></li>
                                    <li><a href="{{ route('runs.index') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('runs.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Runs</a></li>
                                    <li><a href="{{ route('results.index') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('results.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Results</a></li>
                                    <li><a href="{{ route('api-keys') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('api-keys') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">API Keys</a></li>
                                    <li><a href="{{ route('billing') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('billing') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Billing</a></li>
                                    <li><a href="{{ route('organization.team') }}" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold hover:bg-gray-50 hover:text-indigo-600 {{ request()->routeIs('organization.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700' }}">Team</a></li>
                                </ul>
                            </li>
                            <li class="mt-auto">
                                <a href="#" class="group -mx-2 flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600">
                                    {{ auth()->user()?->email }}
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="group -mx-2 flex w-full gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-red-600 hover:bg-gray-50">
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="lg:pl-72">
                <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
                    <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-gray-700 lg:hidden">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6 justify-end">
                        <div class="flex items-center gap-x-4 lg:gap-x-6">
                            <span class="text-sm font-semibold text-gray-900">{{ auth()->user()?->email }}</span>
                        </div>
                    </div>
                </div>

                <main class="py-10">
                    <div class="px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    @else
        <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Sign in to your account</h2>
            </div>
            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    @endauth
</body>
</html>
