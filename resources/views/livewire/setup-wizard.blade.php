<div class="py-10">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-800 mb-1">Create a bidding session</h1>
        <p class="text-sm text-gray-500 mb-6">Set up your house. You'll be enrolled as a tenant automatically.</p>

        {{-- Step indicator --}}
        <ol class="flex items-center gap-2 mb-8 text-sm">
            @foreach (['House basics', 'Rooms & capacity', 'Review'] as $i => $label)
                @php $n = $i + 1; @endphp
                <li class="flex items-center gap-2">
                    <span @class([
                        'flex items-center justify-center w-7 h-7 rounded-full font-medium',
                        'bg-indigo-600 text-white' => $step === $n,
                        'bg-indigo-100 text-indigo-700' => $step > $n,
                        'bg-gray-200 text-gray-500' => $step < $n,
                    ])>{{ $step > $n ? '✓' : $n }}</span>
                    <span @class(['font-medium text-gray-800' => $step === $n, 'text-gray-500' => $step !== $n])>{{ $label }}</span>
                    @if ($n < 3) <span class="text-gray-300 mx-1">—</span> @endif
                </li>
            @endforeach
        </ol>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            {{-- ================= STEP 1 ================= --}}
            @if ($step === 1)
                <div class="space-y-5">
                    <div>
                        <label for="total_rent" class="block text-sm font-medium text-gray-700">Total house rent (RM)</label>
                        <input wire:model="total_rent" id="total_rent" type="number" step="0.01" min="0"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('total_rent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="num_tenants" class="block text-sm font-medium text-gray-700">Number of tenants</label>
                            <input wire:model="num_tenants" id="num_tenants" type="number" min="1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Including you, the host.</p>
                            @error('num_tenants') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="num_rooms" class="block text-sm font-medium text-gray-700">Number of rooms</label>
                            <input wire:model.live="num_rooms" id="num_rooms" type="number" min="1" max="50"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('num_rooms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Offset unit</label>
                            <div class="mt-1 inline-flex rounded-md shadow-sm">
                                <button type="button" wire:click="$set('offset_unit', 'percentage')"
                                        @class(['px-4 py-2 text-sm rounded-l-md border', 'bg-indigo-600 text-white border-indigo-600' => $offset_unit === 'percentage', 'bg-white text-gray-700 border-gray-300' => $offset_unit !== 'percentage'])>Percentage (%)</button>
                                <button type="button" wire:click="$set('offset_unit', 'fixed')"
                                        @class(['px-4 py-2 text-sm rounded-r-md border-t border-b border-r', 'bg-indigo-600 text-white border-indigo-600' => $offset_unit === 'fixed', 'bg-white text-gray-700 border-gray-300' => $offset_unit !== 'fixed'])>Fixed (RM)</button>
                            </div>
                        </div>
                        <div>
                            <label for="offset_value" class="block text-sm font-medium text-gray-700">
                                Offset value ({{ $offset_unit === 'percentage' ? '%' : 'RM' }})
                            </label>
                            <input wire:model="offset_value" id="offset_value" type="number" step="0.01" min="0"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Step size a room's price moves each round.</p>
                            @error('offset_value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Anonymity</label>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-start gap-2">
                                <input type="radio" wire:model="anonymity" value="names_visible" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700"><span class="font-medium">Names visible</span> — everyone sees everyone's name throughout.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="radio" wire:model="anonymity" value="anonymous" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700"><span class="font-medium">Anonymous</span> — members see placeholders; real names revealed at End Bid.</span>
                            </label>
                        </div>
                        @error('anonymity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700">Your display name</label>
                        <input wire:model="display_name" id="display_name" type="text"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">The name you'll bid under.</p>
                        @error('display_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            {{-- ================= STEP 2 ================= --}}
            @if ($step === 2)
                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Set the maximum occupants for each room.</p>
                    @foreach ($rooms as $i => $room)
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end border-b border-gray-100 pb-4">
                            <div class="sm:col-span-2 text-sm font-medium text-gray-700 pt-2">Room {{ $i + 1 }}</div>
                            <div class="sm:col-span-6">
                                <label class="block text-xs text-gray-500">Label (optional)</label>
                                <input wire:model="rooms.{{ $i }}.label" type="text" placeholder="e.g. Master"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs text-gray-500">Max occupants</label>
                                <input wire:model.live="rooms.{{ $i }}.max_occupants" type="number" min="1" max="100"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @error("rooms.$i.max_occupants") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between rounded-md bg-gray-50 px-4 py-3">
                        <span class="text-sm text-gray-600">Total capacity</span>
                        <span class="text-lg font-semibold text-gray-900">{{ $this->totalCapacity }}</span>
                    </div>
                </div>
            @endif

            {{-- ================= STEP 3 ================= --}}
            @if ($step === 3)
                <div class="space-y-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Total rent</dt><dd class="font-medium text-gray-900">RM {{ number_format((float) $total_rent, 2) }}</dd></div>
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Tenants (incl. host)</dt><dd class="font-medium text-gray-900">{{ $num_tenants }}</dd></div>
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Rooms</dt><dd class="font-medium text-gray-900">{{ $num_rooms }}</dd></div>
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Total capacity</dt><dd class="font-medium text-gray-900">{{ $this->totalCapacity }}</dd></div>
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Offset</dt><dd class="font-medium text-gray-900">{{ $offset_value }}{{ $offset_unit === 'percentage' ? '%' : ' RM' }}</dd></div>
                        <div class="flex justify-between sm:block"><dt class="text-gray-500">Anonymity</dt><dd class="font-medium text-gray-900">{{ $anonymity === 'anonymous' ? 'Anonymous' : 'Names visible' }}</dd></div>
                    </dl>

                    {{-- Scope verdict: icon + text + colour (accessibility: never colour alone) --}}
                    @if ($this->scope === 'c_i')
                        <div class="flex items-start gap-3 rounded-md border border-yellow-300 bg-yellow-50 px-4 py-3">
                            <span aria-hidden="true">🟡</span>
                            <p class="text-sm text-yellow-800"><span class="font-semibold">In scope (C-i).</span> Tenants ({{ $num_tenants }}) &lt; capacity ({{ $this->totalCapacity }}) — some slots stay empty. That's fine.</p>
                        </div>
                    @elseif ($this->scope === 'c_ii')
                        <div class="flex items-start gap-3 rounded-md border border-green-300 bg-green-50 px-4 py-3">
                            <span aria-hidden="true">🟢</span>
                            <p class="text-sm text-green-800"><span class="font-semibold">In scope (C-ii).</span> Tenants ({{ $num_tenants }}) = capacity ({{ $this->totalCapacity }}) — every slot will be filled.</p>
                        </div>
                    @elseif ($this->scope === 'c_iii')
                        <div class="flex items-start gap-3 rounded-md border border-red-300 bg-red-50 px-4 py-3">
                            <span aria-hidden="true">🔴</span>
                            <p class="text-sm text-red-800"><span class="font-semibold">Not supported yet (C-iii).</span> You have more tenants ({{ $num_tenants }}) than total capacity ({{ $this->totalCapacity }}). This scenario is planned for a future release — reduce tenants or add capacity to continue.</p>
                        </div>
                    @endif
                    @error('scope') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Nav --}}
            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="prevStep"
                        @class(['px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50', 'invisible' => $step === 1])>Back</button>

                @if ($step < 3)
                    <button type="button" wire:click="nextStep"
                            class="px-5 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Next</button>
                @else
                    <button type="button" wire:click="create" wire:loading.attr="disabled" @disabled($this->isBlocked)
                            @class(['px-5 py-2 text-sm rounded-md text-white', 'bg-indigo-600 hover:bg-indigo-700' => !$this->isBlocked, 'bg-gray-300 cursor-not-allowed' => $this->isBlocked])>
                        <span wire:loading.remove wire:target="create">Create session</span>
                        <span wire:loading wire:target="create">Creating…</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
