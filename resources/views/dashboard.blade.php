<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My results</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $results = \App\Models\BiddingSession::where('host_user_id', auth()->id())
                    ->where('status', 'ended')
                    ->whereNotNull('result_token')
                    ->latest('id')->get();
            @endphp

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex items-center justify-between border-b border-gray-100">
                    <span class="font-medium text-gray-900">Saved results</span>
                    <a href="{{ route('tool') }}"
                       class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">New split</a>
                </div>

                @if ($results->isEmpty())
                    <div class="p-6 text-sm text-gray-500">
                        No saved results yet. <a href="{{ route('tool') }}" class="text-indigo-600 hover:underline">Run a split</a> and save it to see it here.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($results as $s)
                            <li class="p-4 px-6 flex items-center justify-between">
                                <div class="text-sm">
                                    <span class="font-medium text-gray-800">RM {{ number_format($s->total_rent_cents / 100, 2) }}</span>
                                    <span class="text-gray-500">· {{ $s->num_tenants }} people · {{ $s->num_rooms }} rooms · {{ optional($s->ended_at)->format('d M Y, H:i') }}</span>
                                </div>
                                <a href="{{ route('result', $s->result_token) }}" target="_blank" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">View result ↗</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
