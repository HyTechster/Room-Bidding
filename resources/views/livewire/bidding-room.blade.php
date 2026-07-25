<div wire:poll.10s>
    @php
        $myState = $mySel?->state;              // null | pick | lock | confirm
        $myRoomId = $mySel?->room_id;
        $colourMeta = [
            'green'  => ['label' => 'Full',            'icon' => '🟢', 'ring' => 'border-green-300',  'badge' => 'bg-green-100 text-green-700'],
            'yellow' => ['label' => 'Space left',      'icon' => '🟡', 'ring' => 'border-yellow-300', 'badge' => 'bg-yellow-100 text-yellow-700'],
            'red'    => ['label' => 'Over-subscribed', 'icon' => '🔴', 'ring' => 'border-red-300',    'badge' => 'bg-red-100 text-red-700'],
        ];
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Round {{ $round?->round_no ?? '—' }}</h3>
        <span class="text-xs text-gray-500">{{ $confirmedCount }} of {{ $totalActive }} confirmed</span>
    </div>

    {{-- Room grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($rooms as $room)
            @php
                $d = $roomData[$room->id] ?? ['occupancy' => 0, 'price_cents' => 0, 'colour' => null];
                $cval = $d['colour']?->value ?? 'yellow';
                $meta = $colourMeta[$cval];
                $selected = $myRoomId === $room->id;
                $locked = $myState === 'confirm';
            @endphp
            <button type="button"
                    aria-pressed="{{ $selected ? 'true' : 'false' }}"
                    aria-label="{{ ($room->label ?: 'Room '.($room->position + 1)).': '.$meta['label'].', RM '.number_format($d['price_cents'] / 100, 2).' per person, '.$d['occupancy'].' of '.$room->max_occupants.' occupants' }}"
                    @if (! $locked) wire:click="pickRoom({{ $room->id }})" @endif
                    @class([
                        'text-left rounded-lg border-2 p-4 transition',
                        $meta['ring'],
                        'ring-2 ring-indigo-500 border-indigo-400' => $selected,
                        'cursor-not-allowed opacity-90' => $locked,
                        'hover:shadow-sm' => ! $locked,
                    ])>
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-800">{{ $room->label ?: 'Room '.($room->position + 1) }}</span>
                    <span class="text-xs px-2 py-0.5 rounded {{ $meta['badge'] }}">
                        <span aria-hidden="true">{{ $meta['icon'] }}</span> {{ $meta['label'] }}
                    </span>
                </div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">RM {{ number_format($d['price_cents'] / 100, 2) }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $d['occupancy'] }} / {{ $room->max_occupants }} occupants · per person</div>
                @if ($selected)
                    <div class="mt-2 text-xs font-medium text-indigo-600">Your pick</div>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Your status + controls --}}
    <div class="mt-5 rounded-lg bg-gray-50 p-4" aria-live="polite">
        @if ($myPriceCents !== null)
            <p class="text-sm text-gray-700 mb-3">If the round ended now, you pay
                <span class="font-semibold">RM {{ number_format($myPriceCents / 100, 2) }}</span>.</p>
        @else
            <p class="text-sm text-gray-500 mb-3">Pick a room to see what you'd pay.</p>
        @endif

        @if ($myState === 'confirm')
            <span class="inline-flex items-center gap-1 text-sm font-medium text-green-700"><span aria-hidden="true">✓</span> Confirmed for this round</span>
            <p class="text-xs text-gray-400 mt-1">You can't change room until the next round.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @if ($myState === 'lock')
                    <button type="button" wire:click="unlock" class="px-4 py-2 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Unlock</button>
                @else
                    <button type="button" wire:click="lock" @disabled(! $myRoomId)
                            @class(['px-4 py-2 text-sm rounded-md text-white', 'bg-indigo-600 hover:bg-indigo-700' => (bool) $myRoomId, 'bg-gray-300 cursor-not-allowed' => ! $myRoomId])>Lock</button>
                @endif
                <button type="button" wire:click="confirm" @disabled(! $myRoomId)
                        @class(['px-4 py-2 text-sm rounded-md text-white', 'bg-green-600 hover:bg-green-700' => (bool) $myRoomId, 'bg-gray-300 cursor-not-allowed' => ! $myRoomId])>Confirm</button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Lock signals your choice; you can still unlock. Confirm is final for this round.</p>
        @endif
    </div>

    {{-- Participant states --}}
    <div class="mt-5">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Participants</h4>
        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-md">
            @foreach ($roster as $r)
                <li class="flex items-center justify-between px-4 py-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-800">{{ $r['name'] }}</span>
                        @if ($r['is_host']) <span class="text-[10px] uppercase bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">Host</span> @endif
                    </div>
                    @switch($r['state'])
                        @case('confirm')
                            <span class="text-green-700">✓ Confirmed</span> @break
                        @case('lock')
                            <span class="text-indigo-600">🔒 Locked</span> @break
                        @case('pick')
                            <span class="text-gray-500">• Picking</span> @break
                        @default
                            <span class="text-gray-300">— Not chosen</span>
                    @endswitch
                </li>
            @endforeach
        </ul>
    </div>
</div>
