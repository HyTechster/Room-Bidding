<div class="py-6 md:py-10 print:py-0">
    @if (! $session)
        <div class="max-w-md mx-auto px-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-red-600">Result not found. This link may be invalid, or the session hasn't ended yet.</p>
            </div>
        </div>
    @else
        @php
            $statusMeta = [
                'green'  => ['label' => 'Full',           'badge' => 'bg-green-100 text-green-800'],
                'yellow' => ['label' => 'Space left',     'badge' => 'bg-yellow-100 text-yellow-800'],
                'red'    => ['label' => 'Over capacity',  'badge' => 'bg-red-100 text-red-800'],
            ];
        @endphp

        <div class="max-w-4xl mx-auto px-4 md:px-8">
            {{-- Print action --}}
            <div class="flex justify-end mb-6 print:hidden no-print">
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 shadow-sm transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print / Save as PDF
                </button>
            </div>

            {{-- Receipt --}}
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden print:shadow-none print:border-none print:rounded-none">
                {{-- Document header --}}
                <div class="bg-gray-900 text-white p-6 md:p-8 print:bg-white print:text-gray-900 print:border-b-2 print:border-gray-300">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold">Bidding session results</h1>
                            <p class="text-gray-400 mt-1 print:text-gray-600">Finalized on {{ $session->ended_at?->format('l, F j, Y') }}</p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="text-sm font-medium text-gray-400 uppercase tracking-wider print:text-gray-500">Total rent</p>
                            <p class="text-3xl font-bold mt-1">{{ $symbol }} {{ number_format($session->total_rent_cents / 100, 2) }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3 text-sm text-gray-300 print:text-gray-700">
                        <span class="bg-gray-800 px-3 py-1 rounded print:bg-gray-100 print:border print:border-gray-200">{{ $session->num_tenants }} people</span>
                        <span class="bg-gray-800 px-3 py-1 rounded print:bg-gray-100 print:border print:border-gray-200">{{ $session->num_rooms }} rooms</span>
                        <span class="bg-gray-800 px-3 py-1 rounded print:bg-gray-100 print:border print:border-gray-200">Ended in round {{ $session->current_round_no }}</span>
                    </div>
                </div>

                <div class="p-6 md:p-8 space-y-10">
                    {{-- Proof of balance --}}
                    <div class="rounded-lg p-5 flex flex-col md:flex-row items-center justify-between gap-4 border {{ $balanced ? 'bg-green-50 border-green-200 print:border-green-600' : 'bg-red-50 border-red-200' }} print:bg-transparent">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-0.5">
                                @if ($balanced)
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @else
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @endif
                            </div>
                            <div>
                                <h3 class="{{ $balanced ? 'text-green-800' : 'text-red-800' }} font-bold text-lg">{{ $balanced ? 'Perfectly balanced' : 'Does not balance' }}</h3>
                                <p class="{{ $balanced ? 'text-green-700' : 'text-red-700' }} text-sm mt-1">The sum of all individual payments matches the total rent exactly.</p>
                            </div>
                        </div>
                        <div class="bg-white px-4 py-2 rounded-md border border-green-200 shadow-sm text-center print:border-none print:shadow-none">
                            <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Sum of room totals</p>
                            <p class="text-xl font-bold text-gray-900 mt-0.5">{{ $symbol }} {{ number_format($total / 100, 2) }}</p>
                        </div>
                    </div>

                    {{-- Per-person breakdown --}}
                    <section>
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Who pays what</h2>

                        {{-- Desktop table --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50 rounded-tl-lg">Person</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50">Room assigned</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50 rounded-tr-lg">Amount to pay</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($lines as $line)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $line->participant->display_name }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ $line->room->label ?: 'Room '.($line->room->position + 1) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">{{ $symbol }} {{ number_format($line->amount_cents / 100, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile stacked cards --}}
                        <div class="md:hidden space-y-3">
                            @foreach ($lines as $line)
                                <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm flex flex-col gap-2">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-gray-900 text-sm">{{ $line->participant->display_name }}</span>
                                        <span class="text-lg font-bold text-gray-900">{{ $symbol }} {{ number_format($line->amount_cents / 100, 2) }}</span>
                                    </div>
                                    <div class="text-sm text-gray-600 flex justify-between">
                                        <span>Room</span>
                                        <span class="font-medium text-gray-800">{{ $line->room->label ?: 'Room '.($line->room->position + 1) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Per-room summary --}}
                    <section>
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Room summary</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach ($roomTotals as $rt)
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 print:bg-white print:border-gray-300">
                                    <h4 class="font-bold text-gray-900 text-sm">{{ $rt['label'] }}</h4>
                                    <dl class="mt-3 space-y-1.5 text-sm">
                                        <div class="flex justify-between text-gray-600"><dt>Final occupants</dt><dd class="font-medium text-gray-900">{{ $rt['occupancy'] }} / {{ $rt['capacity'] }}</dd></div>
                                        <div class="flex justify-between text-gray-600"><dt>Per person</dt><dd class="font-medium text-gray-900">{{ $symbol }} {{ number_format($rt['price_cents'] / 100, 2) }}</dd></div>
                                        <div class="pt-2 mt-2 border-t border-gray-200 flex justify-between"><dt class="font-bold text-gray-900">Room total</dt><dd class="font-bold text-gray-900">{{ $symbol }} {{ number_format($rt['total_cents'] / 100, 2) }}</dd></div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Round-by-round history (collapsible; always open in print) --}}
                    <section x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between text-left text-lg font-bold text-gray-900 mb-4 border-b pb-2 print:hidden">
                            <span>Audit log: round history</span>
                            <svg class="w-5 h-5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <h2 class="hidden print:block text-lg font-bold text-gray-900 mb-4 border-b pb-2">Audit log: round history</h2>

                        <div x-show="open" x-cloak class="print-history space-y-8">
                            @foreach ($history as $round)
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm print:border-none print:shadow-none">
                                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200"><h4 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Round {{ $round['round_no'] }}</h4></div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-white">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Room</th>
                                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Occupancy</th>
                                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Status</th>
                                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Weight</th>
                                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Price</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach ($round['rooms'] as $r)
                                                    @php $m = $statusMeta[$r['colour']] ?? $statusMeta['yellow']; @endphp
                                                    <tr>
                                                        <td class="px-4 py-2 text-gray-900 font-medium whitespace-nowrap">{{ $r['label'] }}</td>
                                                        <td class="px-4 py-2 text-gray-600 text-center">{{ $r['occupancy'] }} / {{ $r['capacity'] }}</td>
                                                        <td class="px-4 py-2">
                                                            @if ($r['colour'])
                                                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full {{ $m['badge'] }}">{{ $m['label'] }}</span>
                                                            @else
                                                                <span class="text-gray-300">n/a</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-2 text-gray-500 text-right">{{ number_format($r['weight'], 3) }}</td>
                                                        <td class="px-4 py-2 text-gray-900 font-bold text-right">{{ $symbol }} {{ number_format($r['price_cents'] / 100, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            </div>
        </div>
    @endif
</div>
