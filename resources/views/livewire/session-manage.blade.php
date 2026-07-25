<div class="py-10" wire:poll.10s>
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Session #{{ $session->id }}</h1>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Dashboard</a>
        </div>

        {{-- Summary --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                <div><dt class="text-gray-500">Total rent</dt><dd class="font-medium text-gray-900">RM {{ number_format($session->total_rent_cents / 100, 2) }}</dd></div>
                <div><dt class="text-gray-500">Tenants</dt><dd class="font-medium text-gray-900">{{ $session->num_tenants }}</dd></div>
                <div><dt class="text-gray-500">Rooms</dt><dd class="font-medium text-gray-900">{{ $session->num_rooms }}</dd></div>
                <div><dt class="text-gray-500">Capacity</dt><dd class="font-medium text-gray-900">{{ $session->total_capacity }}</dd></div>
                <div><dt class="text-gray-500">Offset</dt><dd class="font-medium text-gray-900">{{ $session->offset_unit === 'percentage' ? rtrim(rtrim($session->offset_percent, '0'), '.').'%' : 'RM '.number_format($session->offset_fixed_cents / 100, 2) }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $session->status)) }}</dd></div>
            </dl>
        </div>

        @if ($isExpired)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">Session expired</h2>
                <p class="text-sm text-gray-500">This session's invite link expired (7 days after creation) before it was completed. It can no longer be started or joined.</p>
            </div>
        @elseif (in_array($session->status, ['draft', 'lobby']))
            {{-- Payment gate --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Payment</h2>
                @if ($isPaid)
                    <p class="inline-flex items-center gap-1 text-sm text-green-700"><span aria-hidden="true">✓</span> Paid — session unlocked.</p>
                @else
                    <p class="text-sm text-gray-600 mb-3">
                        <span aria-hidden="true">🔒</span>
                        This session is locked. Pay {{ $currency }} {{ number_format($priceCents / 100, 2) }} to unlock it.
                    </p>
                    <button type="button" wire:click="pay" wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        <span wire:loading.remove wire:target="pay">Pay (sandbox)</span>
                        <span wire:loading wire:target="pay">Processing…</span>
                    </button>
                    <p class="mt-2 text-xs text-gray-400">Sandbox gate — no real charge. A live provider (ToyyibPay / Billplz / Stripe) drops in later.</p>
                @endif
            </div>

            {{-- Invite link --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Invite link</h2>
                @if ($joinUrl)
                    <p class="text-xs text-gray-500 mb-3">Share with your {{ max(0, $session->num_tenants - 1) }} housemates. Expires {{ $session->expires_at->diffForHumans() }} (or at End Bid).</p>
                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <input type="text" readonly value="{{ $joinUrl }}"
                               class="flex-1 rounded-md border-gray-300 bg-gray-50 text-sm text-gray-700"
                               x-ref="link" onclick="this.select()">
                        <button type="button"
                                @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)"
                                class="px-3 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Lobby + Start --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($hostPid)
                    <livewire:lobby :session-id="$session->id" :viewer-participant-id="$hostPid" :key="'lobby-host-'.$session->id" />
                @endif
                @if (session('start_error'))
                    <p class="mt-4 text-sm text-red-600">{{ session('start_error') }}</p>
                @endif
                <div class="mt-6 pt-4 border-t border-gray-100 flex items-center gap-3">
                    <button type="button" wire:click="startBid" @disabled(! $canStart)
                            @class(['px-5 py-2 text-sm rounded-md text-white', 'bg-indigo-600 hover:bg-indigo-700' => $canStart, 'bg-gray-300 cursor-not-allowed' => ! $canStart])>Start bid</button>
                    <span class="text-xs text-gray-500">
                        {{ $joinedCount }}/{{ $session->num_tenants }} joined · {{ $readyCount }} ready
                        @unless ($canStart)
                            @if (! $isPaid) — pay to unlock the session @else — everyone must join and be ready @endif
                        @endunless
                    </span>
                </div>
            </div>

        @elseif (in_array($session->status, ['bidding', 'cap_reached']))
            {{-- Host uses the identical bidding grid + a separate control strip --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($hostPid)
                    <livewire:bidding-room :session-id="$session->id" :viewer-participant-id="$hostPid" :key="'room-host-'.$session->id" />
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6 sticky bottom-4">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Host controls</h2>
                @if (session('start_error'))
                    <p class="mb-3 text-sm text-red-600">{{ session('start_error') }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-3">
                    @if ($capReached)
                        <button type="button" wire:click="forceEnd" class="px-5 py-2 text-sm rounded-md bg-amber-600 text-white hover:bg-amber-700">Force end</button>
                        <span class="text-xs text-amber-700">Round cap reached with a room still over-subscribed. Force-end settles at the current occupancy.</span>
                    @else
                        <button type="button" wire:click="endBid" @disabled(! $canEnd)
                                @class(['px-5 py-2 text-sm rounded-md text-white', 'bg-green-600 hover:bg-green-700' => $canEnd, 'bg-gray-300 cursor-not-allowed' => ! $canEnd])>End Bid</button>
                        <span class="text-xs text-gray-500">
                            @if ($anyRed)
                                A room is over-subscribed — the round advances automatically when everyone confirms.
                            @elseif ($allConfirmed)
                                Everyone confirmed and no room is red. You can End Bid.
                            @else
                                Waiting for all participants to confirm.
                            @endif
                        </span>
                    @endif
                </div>
            </div>

        @elseif ($session->status === 'ended' && $settlement)
            {{-- Settlement summary (permanent, printable result comes in Phase 9) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-sm font-semibold text-gray-700">Final result</h2>
                    @if ($session->result_token)
                        <a href="{{ route('result', $session->result_token) }}" target="_blank"
                           class="text-sm text-indigo-600 hover:underline">Open permanent result page ↗</a>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mb-4">Bidding ended {{ $session->ended_at?->diffForHumans() }}. Names are revealed.</p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2">Tenant</th><th>Room</th><th class="text-right">Pays</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $sum = 0; @endphp
                        @foreach ($settlement->lines as $line)
                            @php $sum += $line->amount_cents; @endphp
                            <tr class="border-b border-gray-50">
                                <td class="py-2 text-gray-800">{{ $line->participant->display_name }}</td>
                                <td class="text-gray-600">{{ $line->room->label ?: 'Room '.($line->room->position + 1) }}</td>
                                <td class="text-right font-medium text-gray-900">RM {{ number_format($line->amount_cents / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td class="py-2" colspan="2">Total</td>
                            <td class="text-right">RM {{ number_format($sum / 100, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="mt-3 text-xs {{ $sum === (int) $session->total_rent_cents ? 'text-green-700' : 'text-red-600' }}">
                    {{ $sum === (int) $session->total_rent_cents ? '✓ Sums exactly to the total rent (RM '.number_format($session->total_rent_cents / 100, 2).').' : '✗ Does not match total rent!' }}
                </p>
            </div>
        @endif
    </div>
</div>
