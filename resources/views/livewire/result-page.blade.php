<div class="py-10">
    @if (! $session)
        <div class="max-w-md mx-auto px-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-red-600">Result not found. This link may be invalid, or the session hasn't ended yet.</p>
            </div>
        </div>
    @else
        @php
            $colourMeta = [
                'green'  => ['label' => 'Full',            'icon' => '🟢'],
                'yellow' => ['label' => 'Space left',      'icon' => '🟡'],
                'red'    => ['label' => 'Over-subscribed', 'icon' => '🔴'],
            ];
        @endphp
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" id="result-root">
            {{-- Header --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">Result — Session #{{ $session->id }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Ended {{ $session->ended_at?->format('d M Y, H:i') }}</p>
                    </div>
                    <button type="button" onclick="window.print()" class="no-print px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Print / Save as PDF</button>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3 text-sm mt-4">
                    <div><dt class="text-gray-500">Total rent</dt><dd class="font-medium text-gray-900">RM {{ number_format($session->total_rent_cents / 100, 2) }}</dd></div>
                    <div><dt class="text-gray-500">Tenants</dt><dd class="font-medium text-gray-900">{{ $session->num_tenants }}</dd></div>
                    <div><dt class="text-gray-500">Rooms</dt><dd class="font-medium text-gray-900">{{ $session->num_rooms }}</dd></div>
                    <div><dt class="text-gray-500">Rounds</dt><dd class="font-medium text-gray-900">{{ $session->current_round_no }}</dd></div>
                </dl>
            </div>

            {{-- Per-person breakdown --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Who pays what</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100">
                                <th class="py-2">Tenant</th><th>Room</th><th class="text-right">Pays</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 text-gray-800">
                                        {{ $line->participant->display_name }}
                                        @if ($line->participant->is_host)
                                            <span class="text-[10px] uppercase bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded ml-1">Host</span>
                                        @endif
                                    </td>
                                    <td class="text-gray-600">{{ $line->room->label ?: 'Room '.($line->room->position + 1) }}</td>
                                    <td class="text-right font-medium text-gray-900">RM {{ number_format($line->amount_cents / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td class="py-2" colspan="2">Total</td>
                                <td class="text-right">RM {{ number_format($total / 100, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-sm {{ $balanced ? 'text-green-700' : 'text-red-600' }}">
                    @if ($balanced)
                        <span aria-hidden="true">✓</span> The per-person amounts sum to exactly the total rent (RM {{ number_format($session->total_rent_cents / 100, 2) }}).
                    @else
                        <span aria-hidden="true">✗</span> The amounts do not match the total rent.
                    @endif
                </p>
            </div>

            {{-- Per-room totals --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Per room</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100">
                                <th class="py-2">Room</th><th>Occupancy</th><th>Per person</th><th class="text-right">Room total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roomTotals as $rt)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 text-gray-800">{{ $rt['label'] }}</td>
                                    <td class="text-gray-600">{{ $rt['occupancy'] }} / {{ $rt['capacity'] }}</td>
                                    <td class="text-gray-600">RM {{ number_format($rt['price_cents'] / 100, 2) }}</td>
                                    <td class="text-right text-gray-900">RM {{ number_format($rt['total_cents'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Round-by-round history --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Round-by-round history</h2>
                <div class="space-y-5">
                    @foreach ($history as $round)
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Round {{ $round['round_no'] }}</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-gray-400 border-b border-gray-100">
                                            <th class="py-1">Room</th><th>Occ.</th><th>Status</th><th>Per person</th><th class="text-right">Weight</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($round['rooms'] as $r)
                                            <tr class="border-b border-gray-50">
                                                <td class="py-1 text-gray-800">{{ $r['label'] }}</td>
                                                <td class="text-gray-600">{{ $r['occupancy'] }} / {{ $r['capacity'] }}</td>
                                                <td class="text-gray-600">
                                                    @if ($r['colour'])
                                                        <span aria-hidden="true">{{ $colourMeta[$r['colour']]['icon'] }}</span> {{ $colourMeta[$r['colour']]['label'] }}
                                                    @else — @endif
                                                </td>
                                                <td class="text-gray-600">RM {{ number_format($r['price_cents'] / 100, 2) }}</td>
                                                <td class="text-right text-gray-500">{{ number_format($r['weight'], 4) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
