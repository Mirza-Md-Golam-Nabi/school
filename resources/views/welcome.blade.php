<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen flex items-center justify-center p-4 lg:p-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 w-full max-w-5xl">
            <a
                href="{{ \Filament\Facades\Filament::getPanel('admin')->getUrl() }}"
                class="flex flex-col items-center text-center gap-3 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 lg:p-10 shadow-sm hover:shadow-md hover:border-black dark:hover:border-white transition-all"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 lg:w-14 lg:h-14 text-[#f53003] dark:text-[#FF4433]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <h2 class="text-sm lg:text-xl font-semibold">Admin Panel</h2>
                <p class="text-xs lg:text-sm text-[#706f6c] dark:text-[#A1A09A]">School এর সম্পূর্ণ ব্যবস্থাপনা ও নিয়ন্ত্রণ</p>
            </a>

            <a
                href="{{ \Filament\Facades\Filament::getPanel('teacher')->getUrl() }}"
                class="flex flex-col items-center text-center gap-3 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 lg:p-10 shadow-sm hover:shadow-md hover:border-black dark:hover:border-white transition-all"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 lg:w-14 lg:h-14 text-[#f53003] dark:text-[#FF4433]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                </svg>
                <h2 class="text-sm lg:text-xl font-semibold">Teacher Panel</h2>
                <p class="text-xs lg:text-sm text-[#706f6c] dark:text-[#A1A09A]">ক্লাস, মার্কস ও উপস্থিতি ব্যবস্থাপনা</p>
            </a>

            <a
                href="{{ \Filament\Facades\Filament::getPanel('student')->getUrl() }}"
                class="flex flex-col items-center text-center gap-3 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 lg:p-10 shadow-sm hover:shadow-md hover:border-black dark:hover:border-white transition-all"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 lg:w-14 lg:h-14 text-[#f53003] dark:text-[#FF4433]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25v-2.25M12 6a3.75 3.75 0 1 0 0 7.5A3.75 3.75 0 0 0 12 6ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                </svg>
                <h2 class="text-sm lg:text-xl font-semibold">Student Panel</h2>
                <p class="text-xs lg:text-sm text-[#706f6c] dark:text-[#A1A09A]">রেজাল্ট, উপস্থিতি ও নোটিশ দেখুন</p>
            </a>
        </div>
    </body>
</html>
