<div class="py-6 md:py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6 no-print">
            <a href="/" class="text-sm text-blue-600 hover:underline">Home</a>
            <span class="text-sm text-gray-400">{{ config('app.name', 'Room Bidding') }}</span>
        </div>

        @php
            $sym = \App\Support\Currency::symbol($currency);
            $statusMeta = [
                'green'  => ['label' => 'Full',          'chip' => 'text-green-800 bg-green-50 border-green-200',  'ring' => 'border-green-200',  'svg' => 'M5 13l4 4L19 7'],
                'yellow' => ['label' => 'Space left',    'chip' => 'text-yellow-800 bg-yellow-50 border-yellow-200','ring' => 'border-yellow-200', 'svg' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                'red'    => ['label' => 'Over capacity',  'chip' => 'text-red-800 bg-red-50 border-red-200',        'ring' => 'border-red-200',    'svg' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ];
        @endphp

        {{-- ===================== SETUP ===================== --}}
        @if ($step === 'setup')
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-5 md:p-8 space-y-8">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Set up the split</h1>
                    <p class="text-sm text-gray-500 mt-1">Enter the rent, the rooms, and everyone's name. Nothing is saved unless you are signed in.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total rent</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-500 sm:text-sm">{{ $sym }}</span></div>
                            <input wire:model="rent" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00"
                                   class="pl-12 block w-full rounded-md border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        @error('rent') <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Currency</label>
                        <select wire:model.live="currency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @foreach ($this->currencyOptions() as $code => $s)
                                <option value="{{ $code }}">{{ $code }} ({{ $s }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Offset --}}
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price offset each round</label>
                        <div class="flex rounded-md shadow-sm" role="group">
                            <button type="button" wire:click="$set('offset_unit', 'percentage')"
                                    @class(['flex-1 py-2 px-4 text-sm font-medium rounded-l-md border transition-colors', 'bg-blue-50 border-blue-600 text-blue-700 z-10' => $offset_unit === 'percentage', 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' => $offset_unit !== 'percentage'])>Percentage (%)</button>
                            <button type="button" wire:click="$set('offset_unit', 'fixed')"
                                    @class(['flex-1 py-2 px-4 text-sm font-medium rounded-r-md border-t border-b border-r transition-colors', 'bg-blue-50 border-blue-600 text-blue-700 z-10' => $offset_unit === 'fixed', 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' => $offset_unit !== 'fixed'])>Fixed ({{ $sym }})</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">A percentage of the equal split, or a fixed amount in {{ $currency }}.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Offset value</label>
                        <div class="relative rounded-md shadow-sm">
                            @if ($offset_unit === 'fixed')
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-500 sm:text-sm">{{ $sym }}</span></div>
                            @endif
                            <input wire:model="offset_value" type="number" step="0.01" min="0" inputmode="decimal"
                                   class="{{ $offset_unit === 'fixed' ? 'pl-12' : 'pr-8' }} block w-full rounded-md border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @if ($offset_unit === 'percentage')
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"><span class="text-gray-500 sm:text-sm">%</span></div>
                            @endif
                        </div>
                        @error('offset_value') <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Rooms --}}
                <div>
                    <div class="flex items-center justify-between mb-3 border-b pb-2">
                        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Rooms &amp; capacity</h2>
                        <div class="text-right">
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total capacity </span>
                            <span class="text-lg font-bold text-blue-600">{{ $this->totalCapacity }}</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach ($rooms as $i => $room)
                            <div class="flex flex-col sm:flex-row gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="flex-1">
                                    <input wire:model="rooms.{{ $i }}.label" type="text" placeholder="Room {{ $i + 1 }} label (optional)"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div class="flex items-center gap-2">
                                    <input wire:model.live="rooms.{{ $i }}.capacity" type="number" min="1" max="1000" step="1" inputmode="numeric" title="Max occupants (whole number)"
                                           class="w-full sm:w-28 rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    @if (count($rooms) > 1)
                                        <button type="button" wire:click="removeRoom({{ $i }})" class="text-gray-400 hover:text-red-600 shrink-0 px-1" title="Remove room">✕</button>
                                    @endif
                                </div>
                                @error("rooms.$i.capacity") <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addRoom" class="mt-3 text-sm font-medium text-blue-600 hover:underline">+ Add room</button>
                    @error('rooms') <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Names --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Participants <span class="font-normal text-gray-500">(one name per line)</span></label>
                    <textarea wire:model.live="namesText" rows="5" placeholder="Ali&#10;Bala&#10;Chong"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                    @error('namesText') <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Scope verdict banner --}}
                @if ($this->scope)
                    @php
                        $isC3 = $this->scope === 'c_iii';
                        $isExact = $this->scope === 'c_ii';
                    @endphp
                    <div @class([
                            'p-4 rounded-lg border flex items-start gap-3',
                            'bg-red-50 border-red-300' => $isC3,
                            'bg-yellow-50 border-yellow-300' => $isExact,
                            'bg-green-50 border-green-300' => ! $isC3 && ! $isExact,
                        ]) role="alert">
                        <svg class="w-6 h-6 flex-shrink-0 {{ $isC3 ? 'text-red-600' : ($isExact ? 'text-yellow-600' : 'text-green-600') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $isC3 ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : ($isExact ? 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z') }}"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-bold {{ $isC3 ? 'text-red-800' : ($isExact ? 'text-yellow-800' : 'text-green-800') }}">
                                {{ $isC3 ? 'Too many people' : ($isExact ? 'Exact fit' : 'In scope') }}
                            </h4>
                            <p class="mt-1 text-sm {{ $isC3 ? 'text-red-700' : ($isExact ? 'text-yellow-700' : 'text-green-700') }}">
                                {{ count($this->parsedNames) }} people, {{ $this->totalCapacity }} capacity.
                                {{ $isC3 ? 'Add room capacity or remove people to continue.' : ($isExact ? 'Every slot will be filled.' : 'Some slots will stay empty, and that is fine.') }}
                            </p>
                        </div>
                    </div>
                @endif

                <div class="pt-2 border-t border-gray-200">
                    <button type="button" wire:click="startBidding"
                            class="w-full sm:w-auto px-6 py-2.5 text-sm font-medium rounded-md bg-blue-600 text-white shadow-sm hover:bg-blue-700 transition-colors">
                        Start placing people
                    </button>
                </div>
            </div>

        {{-- ===================== BIDDING ===================== --}}
        @elseif ($step === 'bidding')
            {{-- Header --}}
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-5 rounded-xl border border-gray-200 shadow-sm mb-6">
                <div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-bold uppercase tracking-wide mb-2">Round {{ $roundNo }}</span>
                    <h1 class="text-2xl font-bold text-gray-900">Place people into rooms</h1>
                    <p class="text-sm text-gray-500 mt-1">Tap a name, then tap a room. On a computer you can also drag. Prices update when you press Next.</p>
                </div>
                <button type="button" wire:click="backToSetup" class="text-sm text-gray-500 hover:underline shrink-0">Edit setup</button>
            </header>

            @if (session('round_note'))
                <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">{{ session('round_note') }}</div>
            @endif

            {{-- Unassigned pool --}}
            <div class="mb-6 rounded-xl border-2 border-dashed border-gray-200 bg-white p-4"
                 wire:click="tapPool"
                 x-on:dragover.prevent x-on:drop.prevent="$wire.assign($event.dataTransfer.getData('text/plain'), null)">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Not placed yet ({{ count($unassigned) }})</div>
                <div class="flex flex-wrap gap-2 min-h-[2.5rem]">
                    @forelse ($unassigned as $pi)
                        <button type="button" wire:click.stop="selectChip({{ $pi }})" draggable="true"
                                x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $pi }}'); $event.dataTransfer.effectAllowed='move'"
                                @class([
                                    'cursor-grab active:cursor-grabbing select-none rounded-full text-sm px-3 py-1.5 font-medium',
                                    'bg-gray-800 text-white' => $selectedPi !== $pi,
                                    'bg-yellow-400 text-gray-900 ring-2 ring-yellow-500' => $selectedPi === $pi,
                                ])>{{ $names[$pi] }}</button>
                    @empty
                        <span class="text-sm text-gray-400">Everyone is placed.</span>
                    @endforelse
                </div>
            </div>

            {{-- Room grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($rooms as $i => $room)
                    @php $d = $roomData[$i]; $cval = $d['colour']->value; $meta = $statusMeta[$cval]; @endphp
                    <div wire:click="tapRoom({{ $i }})"
                         x-on:dragover.prevent x-on:drop.prevent="$wire.assign($event.dataTransfer.getData('text/plain'), {{ $i }})"
                         @class([
                             'relative flex flex-col p-5 rounded-xl border-2 bg-white transition-all cursor-pointer',
                             $meta['ring'],
                             'ring-2 ring-blue-400 border-blue-400' => $selectedPi !== null,
                         ])>
                        <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold border {{ $meta['chip'] }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['svg'] }}"/></svg>
                            <span>{{ $meta['label'] }}</span>
                        </div>

                        <h3 class="text-lg font-bold text-gray-900 pr-28">{{ $room['label'] ?: 'Room '.($i + 1) }}</h3>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-sm font-medium text-gray-500">Occupancy</span>
                            <span class="text-lg font-bold {{ $d['occupancy'] > $room['capacity'] ? 'text-red-600' : 'text-gray-900' }}">{{ $d['occupancy'] }} <span class="text-sm font-normal text-gray-500">/ {{ $room['capacity'] }}</span></span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Current price</p>
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-2xl font-bold text-gray-900">{{ $this->money($d['price_cents']) }}</span>
                                @if (($d['previous_cents'] ?? null) !== null && $d['previous_cents'] !== $d['price_cents'])
                                    @php $up = $d['price_cents'] > $d['previous_cents']; @endphp
                                    <span class="text-xs font-medium {{ $up ? 'text-red-600' : 'text-green-600' }}" title="Previous price">{{ $up ? '▲' : '▼' }} was {{ $this->money($d['previous_cents']) }}</span>
                                @endif
                            </div>
                            @if (($d['committedColour'] ?? null) === 'green')
                                @if (($d['weightState'] ?? 'same') === 'up')
                                    <p class="mt-1.5 text-[11px] leading-snug text-amber-700">In demand. This room was over capacity earlier, so its price was pushed up. Whoever takes it pays that premium.</p>
                                @elseif (($d['weightState'] ?? 'same') === 'down')
                                    <p class="mt-1.5 text-[11px] leading-snug text-green-700">Discount. This room had spare space earlier, so its price was pushed down.</p>
                                @elseif ($d['price_cents'] !== $equalSplit)
                                    <p class="mt-1.5 text-[11px] leading-snug text-gray-500">Adjusted so the payments still add up to exactly the rent.</p>
                                @endif
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 min-h-[2rem]">
                            @foreach ($byRoom[$i] as $pi)
                                <button type="button" wire:click.stop="selectChip({{ $pi }})" draggable="true"
                                        x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $pi }}'); $event.dataTransfer.effectAllowed='move'"
                                        @class([
                                            'cursor-grab active:cursor-grabbing select-none rounded-full text-sm px-3 py-1.5 font-medium',
                                            'bg-blue-600 text-white' => $selectedPi !== $pi,
                                            'bg-yellow-400 text-gray-900 ring-2 ring-yellow-500' => $selectedPi === $pi,
                                        ])>{{ $names[$pi] }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @error('board') <p class="mt-4 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror

            {{-- Sticky action footer --}}
            <div class="sticky bottom-4 z-20 bg-white border border-gray-200 rounded-xl shadow-lg p-4 flex flex-col sm:flex-row justify-between items-center gap-4 mt-8">
                <p class="text-sm text-gray-600 order-2 sm:order-1">
                    @if (! $allPlaced) Everyone must be in a room before you can continue.
                    @elseif ($readyToSettle) Prices are set for this placement. Finish, or move someone to keep adjusting.
                    @elseif ($anyRedNow) A room is over capacity. Press Next to update prices, then move people out of it.
                    @else Press Next to calculate the prices for this placement.
                    @endif
                </p>
                <div class="order-1 sm:order-2 w-full sm:w-auto">
                    @if (! $allPlaced)
                        <button type="button" disabled class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold rounded-lg text-white bg-gray-300 cursor-not-allowed">Place everyone first</button>
                    @elseif ($readyToSettle)
                        <button type="button" wire:click="finish" class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-700 shadow-sm transition-colors">Finish and see who pays</button>
                    @else
                        <button type="button" wire:click="continueRound" class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-colors">Next: recalculate prices</button>
                    @endif
                </div>
            </div>

        {{-- ===================== RESULT ===================== --}}
        @elseif ($step === 'result')
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-gray-900">Who pays what</h1>
                <div class="flex items-center gap-3 no-print">
                    <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print / Save as PDF
                    </button>
                    <button type="button" wire:click="restart" class="text-sm text-gray-500 hover:underline">Start over</button>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-5 md:p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100"><th class="py-2">Person</th><th>Room</th><th class="text-right">Pays</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($result as $row)
                                <tr>
                                    <td class="py-2.5 text-gray-800 font-medium">{{ $row['name'] }}</td>
                                    <td class="text-gray-600">{{ $row['room'] }}</td>
                                    <td class="text-right font-bold text-gray-900">{{ $this->money($row['amount_cents']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold border-t border-gray-200"><td class="py-2.5" colspan="2">Total</td><td class="text-right">{{ $this->money($resultTotal) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-sm text-green-700 flex items-center gap-1.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    The amounts add up to exactly the total rent ({{ $this->money($resultTotal) }}).
                </p>

                <div class="mt-5 pt-4 border-t border-gray-100 no-print">
                    @auth
                        @if ($savedToken)
                            <p class="text-sm text-green-700">Saved to your results.
                                <a href="{{ route('result', $savedToken) }}" target="_blank" class="text-blue-600 hover:underline">Open result page</a>,
                                <a href="{{ route('dashboard') }}" wire:navigate class="text-blue-600 hover:underline">my results</a>.
                            </p>
                        @else
                            <button type="button" wire:click="saveResult" wire:loading.attr="disabled"
                                    class="w-full sm:w-auto px-5 py-2.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                <span wire:loading.remove wire:target="saveResult">Save this result</span>
                                <span wire:loading wire:target="saveResult">Saving</span>
                            </button>
                            <span class="ml-2 text-xs text-gray-500">Keeps it in your history with a shareable link.</span>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">Want to keep this? <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Log in</a> to save your results and get a shareable link.</p>
                    @endauth
                </div>
            </div>
        @endif
    </div>
</div>
