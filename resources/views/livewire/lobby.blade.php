<div wire:poll.10s>
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Lobby</h3>
        <span class="text-xs text-gray-500">
            {{ $readyCount }} of {{ $total }} ready
            @if ($remaining > 0)
                · {{ $remaining }} {{ \Illuminate\Support\Str::plural('slot', $remaining) }} open
            @endif
        </span>
    </div>

    @if ($total === 0)
        <p class="text-sm text-gray-500">No one has joined yet — share the invite link.</p>
    @else
        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-md">
            @foreach ($participants as $p)
                <li class="flex items-center justify-between px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-800">{{ $this->displayNameFor($p, $viewer) }}</span>
                        @if ($p->is_host)
                            <span class="text-[10px] uppercase tracking-wide bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">Host</span>
                        @endif
                        @if ($p->id === $viewer->id)
                            <span class="text-[10px] uppercase tracking-wide bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">You</span>
                        @endif
                    </div>
                    @if ($p->is_ready)
                        <span class="inline-flex items-center gap-1 text-sm text-green-700"><span aria-hidden="true">✓</span> Ready</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-sm text-gray-400"><span aria-hidden="true">—</span> Not ready</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if (! $hostReady)
            <p class="mt-3 text-xs text-amber-600">Waiting for the host to be ready.</p>
        @endif

        @php $me = $participants->firstWhere('id', $viewer->id) ?? $viewer; @endphp
        <div class="mt-4">
            <button type="button" wire:click="toggleReady" wire:loading.attr="disabled"
                    @class([
                        'px-4 py-2 text-sm rounded-md',
                        'bg-green-600 text-white hover:bg-green-700' => ! $me->is_ready,
                        'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' => $me->is_ready,
                    ])>
                {{ $me->is_ready ? "I'm not ready" : "I'm ready" }}
            </button>
        </div>
    @endif
</div>
