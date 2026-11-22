<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-900">Dashboard Admin</h1>
            <p class="mt-1 text-sm text-gray-500">Halo, {{ auth()->user()->name }}.</p>
        </div>
    </div>
</x-app-layout>
