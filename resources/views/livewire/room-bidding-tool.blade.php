<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <a href="/" class="text-sm text-indigo-600 hover:underline">← Home</a>
            <span class="text-sm text-gray-400">{{ config('app.name', 'Room Bidding') }}</span>
        </div>

        {{-- ===================== SETUP ===================== --}}
        @if ($step === 'setup')
            <h1 class="text-2xl font-semibold text-gray-800 mb-1">Set up the split</h1>
            <p class="text-sm text-gray-500 mb-6">Enter the rent, the rooms, and everyone's name. Nothing is saved unless you're signed in.</p>

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total rent (RM)</label>
                        <input wire:model="rent" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('rent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Offset</label>
                        <div class="mt-1 flex">
                            <input wire:model="offset_value" type="number" step="0.01" min="0" class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="button" wire:click="$set('offset_unit', offset_unit === 'percentage' ? 'fixed' : 'percentage')"
                                    class="px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-sm text-gray-700">
                                {{ $offset_unit === 'percentage' ? '%' : 'RM' }}
                            </button>
                        </div>
                        @error('offset_value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col justify-end text-sm text-gray-500">
                        Capacity {{ $this->totalCapacity }} · People {{ count($this->parsedNames) }}
                        @if ($this->scope === 'c_iii')
                            <span class="text-red-600">🔴 too many people</span>
                        @elseif ($this->scope)
                            <span class="text-green-600">✓ in scope</span>
                        @endif
                    </div>
                </div>

                {{-- Rooms --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Rooms & capacity</label>
                        <button type="button" wire:click="addRoom" class="text-sm text-indigo-600 hover:underline">+ Add room</button>
                    </div>
                    <div class="space-y-2">
                        @foreach ($rooms as $i => $room)
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 w-14">Room {{ $i + 1 }}</span>
                                <input wire:model="rooms.{{ $i }}.label" type="text" placeholder="Label (optional)" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <input wire:model.live="rooms.{{ $i }}.capacity" type="number" min="1" class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" title="Max occupants">
                                @if (count($rooms) > 1)
                                    <button type="button" wire:click="removeRoom({{ $i }})" class="text-gray-400 hover:text-red-600" title="Remove">✕</button>
                                @endif
                            </div>
                            @error("rooms.$i.capacity") <p class="text-sm text-red-600 ml-16">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                    @error('rooms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Names --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Participants <span class="text-gray-400">(one name per line)</span></label>
                    <textarea wire:model.live="namesText" rows="5" placeholder="Ali&#10;Bala&#10;Chong&#10;…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('namesText') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="button" wire:click="startBidding" class="px-5 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Start placing people →</button>
                </div>
            </div>

        {{-- ===================== BIDDING ===================== --}}
        @elseif ($step === 'bidding')
            @php
                $colourMeta = [
                    'green'  => ['label' => 'Full',            'icon' => '🟢', 'ring' => 'border-green-300',  'badge' => 'bg-green-100 text-green-700'],
                    'yellow' => ['label' => 'Space left',      'icon' => '🟡', 'ring' => 'border-yellow-300', 'badge' => 'bg-yellow-100 text-yellow-700'],
                    'red'    => ['label' => 'Over-subscribed', 'icon' => '🔴', 'ring' => 'border-red-300',    'badge' => 'bg-red-100 text-red-700'],
                ];
            @endphp

            <div class="flex items-center justify-between mb-2">
                <h1 class="text-2xl font-semibold text-gray-800">Round {{ $roundNo }}</h1>
                <button type="button" wire:click="backToSetup" class="text-sm text-gray-500 hover:underline">Edit setup</button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Drag each name into a room. Over-subscribed (red) rooms get pricier; quieter rooms get cheaper.</p>

            @if (session('round_note'))
                <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-800">{{ session('round_note') }}</div>
            @endif

            {{-- Unassigned pool --}}
            <div class="mb-4 rounded-lg border-2 border-dashed border-gray-200 p-3"
                 x-on:dragover.prevent x-on:drop.prevent="$wire.assign($event.dataTransfer.getData('text/plain'), null)">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Unassigned ({{ count($unassigned) }})</div>
                <div class="flex flex-wrap gap-2 min-h-[2rem]">
                    @forelse ($unassigned as $pi)
                        <div draggable="true"
                             x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $pi }}'); $event.dataTransfer.effectAllowed='move'"
                             class="cursor-grab active:cursor-grabbing select-none rounded-full bg-gray-800 text-white text-sm px-3 py-1">
                            {{ $names[$pi] }}
                        </div>
                    @empty
                        <span class="text-sm text-gray-400">Everyone is placed.</span>
                    @endforelse
                </div>
            </div>

            {{-- Room grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($rooms as $i => $room)
                    @php $d = $roomData[$i]; $cval = $d['colour']->value; $meta = $colourMeta[$cval]; @endphp
                    <div class="rounded-lg border-2 {{ $meta['ring'] }} p-4"
                         x-on:dragover.prevent x-on:drop.prevent="$wire.assign($event.dataTransfer.getData('text/plain'), {{ $i }})">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800">{{ $room['label'] ?: 'Room '.($i + 1) }}</span>
                            <span class="text-xs px-2 py-0.5 rounded {{ $meta['badge'] }}"><span aria-hidden="true">{{ $meta['icon'] }}</span> {{ $meta['label'] }}</span>
                        </div>
                        <div class="mt-2 text-xl font-semibold text-gray-900">RM {{ number_format($d['price_cents'] / 100, 2) }}</div>
                        <div class="text-xs text-gray-500">{{ $d['occupancy'] }} / {{ $room['capacity'] }} · per person</div>
                        <div class="mt-3 flex flex-wrap gap-2 min-h-[2rem]">
                            @foreach ($byRoom[$i] as $pi)
                                <div draggable="true"
                                     x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $pi }}'); $event.dataTransfer.effectAllowed='move'"
                                     class="cursor-grab active:cursor-grabbing select-none rounded-full bg-indigo-600 text-white text-sm px-3 py-1">
                                    {{ $names[$pi] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @error('board') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-6 flex items-center gap-3">
                <button type="button" wire:click="continueRound" @disabled(! $allPlaced)
                        @class([
                            'px-5 py-2 text-sm rounded-md text-white',
                            'bg-indigo-600 hover:bg-indigo-700' => $allPlaced && $anyRedNow,
                            'bg-green-600 hover:bg-green-700' => $allPlaced && ! $anyRedNow,
                            'bg-gray-300 cursor-not-allowed' => ! $allPlaced,
                        ])>
                    @if (! $allPlaced)
                        Place everyone first
                    @elseif ($anyRedNow)
                        Continue → next round
                    @else
                        Settle the split
                    @endif
                </button>
                <span class="text-xs text-gray-500">
                    @if ($allPlaced && $anyRedNow) A room is over capacity — continue to adjust prices.
                    @elseif ($allPlaced) No room is over capacity — you can settle.
                    @endif
                </span>
            </div>

        {{-- ===================== RESULT ===================== --}}
        @elseif ($step === 'result')
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-semibold text-gray-800">Who pays what</h1>
                <button type="button" wire:click="restart" class="text-sm text-gray-500 hover:underline">Start over</button>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100"><th class="py-2">Person</th><th>Room</th><th class="text-right">Pays</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($result as $row)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 text-gray-800">{{ $row['name'] }}</td>
                                    <td class="text-gray-600">{{ $row['room'] }}</td>
                                    <td class="text-right font-medium text-gray-900">RM {{ number_format($row['amount_cents'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold"><td class="py-2" colspan="2">Total</td><td class="text-right">RM {{ number_format($resultTotal / 100, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-sm text-green-700"><span aria-hidden="true">✓</span> The amounts add up to exactly the total rent (RM {{ number_format($resultTotal / 100, 2) }}).</p>

                <div class="mt-5 pt-4 border-t border-gray-100">
                    @auth
                        @if ($savedToken)
                            <p class="text-sm text-green-700"><span aria-hidden="true">✓</span> Saved to your results.
                                <a href="{{ route('result', $savedToken) }}" target="_blank" class="text-indigo-600 hover:underline">Open result page ↗</a>
                                · <a href="{{ route('dashboard') }}" wire:navigate class="text-indigo-600 hover:underline">My results</a>
                            </p>
                        @else
                            <button type="button" wire:click="saveResult" wire:loading.attr="disabled"
                                    class="px-5 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                                <span wire:loading.remove wire:target="saveResult">Save this result</span>
                                <span wire:loading wire:target="saveResult">Saving…</span>
                            </button>
                            <span class="ml-2 text-xs text-gray-500">Keeps it in your history and gives you a shareable link.</span>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">Want to keep this? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Log in</a> to save your results and get a shareable link.</p>
                    @endauth
                </div>
            </div>
        @endif
    </div>
</div>
