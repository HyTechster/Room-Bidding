<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $all = \App\Models\BiddingSession::where('host_user_id', auth()->id())->latest('id')->get();
                $active = $all->whereNotIn('status', ['ended', 'expired']);
                $completed = $all->whereIn('status', ['ended', 'expired']);
                $statusBadge = [
                    'draft'       => 'bg-gray-100 text-gray-600',
                    'lobby'       => 'bg-blue-100 text-blue-700',
                    'bidding'     => 'bg-indigo-100 text-indigo-700',
                    'cap_reached' => 'bg-amber-100 text-amber-700',
                    'ended'       => 'bg-green-100 text-green-700',
                    'expired'     => 'bg-gray-100 text-gray-500',
                ];
            @endphp

            {{-- Active sessions --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 flex items-center justify-between border-b border-gray-100">
                    <span class="font-medium">Active sessions</span>
                    <a href="{{ route('sessions.create') }}" wire:navigate
                       class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">New bidding session</a>
                </div>
                @if ($active->isEmpty())
                    <div class="p-6 text-sm text-gray-500">No active sessions. Create one to get started.</div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($active as $s)
                            <li class="p-4 px-6 flex items-center justify-between">
                                <div class="text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800">Session #{{ $s->id }}</span>
                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded {{ $statusBadge[$s->status] ?? 'bg-gray-100 text-gray-600' }}">{{ str_replace('_', ' ', $s->status) }}</span>
                                    </div>
                                    <div class="text-gray-500 mt-0.5">RM {{ number_format($s->total_rent_cents / 100, 2) }} · {{ $s->num_tenants }} tenants · {{ $s->num_rooms }} rooms · expires {{ $s->expires_at->diffForHumans() }}</div>
                                </div>
                                <a href="{{ route('sessions.manage', $s) }}" wire:navigate class="text-sm text-indigo-600 hover:underline whitespace-nowrap">Resume →</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Completed / expired sessions --}}
            @if ($completed->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 border-b border-gray-100"><span class="font-medium">Completed</span></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($completed as $s)
                            <li class="p-4 px-6 flex items-center justify-between">
                                <div class="text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800">Session #{{ $s->id }}</span>
                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded {{ $statusBadge[$s->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $s->status }}</span>
                                    </div>
                                    <div class="text-gray-500 mt-0.5">RM {{ number_format($s->total_rent_cents / 100, 2) }} · {{ $s->num_tenants }} tenants
                                        @if ($s->status === 'ended' && $s->ended_at) · ended {{ $s->ended_at->diffForHumans() }} @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($s->status === 'ended' && $s->result_token)
                                        <a href="{{ route('result', $s->result_token) }}" target="_blank" class="text-sm text-indigo-600 hover:underline">Result ↗</a>
                                    @endif
                                    <a href="{{ route('sessions.manage', $s) }}" wire:navigate class="text-sm text-gray-500 hover:underline">View</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
