<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} &mdash; Central</title>

        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <main class="flex w-full max-w-xl flex-col items-center text-center">
            <span class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#19140035] px-4 py-1.5 text-xs font-medium tracking-wider text-[#706f6c] uppercase dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                Central Domain
            </span>

            <h1 class="mb-4 text-3xl font-semibold sm:text-4xl">
                Welcome to {{ config('app.name', 'Laravel') }}
            </h1>

            <p class="mb-8 text-[#706f6c] dark:text-[#A1A09A]">
                This is the <strong class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">central application</strong>.
                It manages tenants and their custom domains &mdash; each tenant is served from its own subdomain
                or domain, completely isolated from this one.
            </p>

            <a
                href="{{ url('/admin') }}"
                class="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
            >
                Go to Admin Panel
            </a>
        </main>
    </body>
</html>
