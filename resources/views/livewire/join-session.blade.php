<div class="py-10" @if ($joined) wire:poll.10s @endif>
    <div class="mx-auto sm:px-6 lg:px-8 {{ $joined && $session && in_array($session->status, ['bidding', 'cap_reached']) ? 'max-w-3xl' : 'max-w-md' }}">
        @if (! $invite || ! $session)
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-red-600">This invite link is invalid.</p>
            </div>
        @elseif ($joined && $session->status === 'ended')
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <h1 class="text-lg font-semibold text-gray-800 mb-1">Bidding has ended</h1>
                <p class="text-sm text-gray-500 mb-4">The host has ended the session. See the final result below.</p>
                @if ($session->result_token)
                    <a href="{{ route('result', $session->result_token) }}"
                       class="inline-block px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">View result</a>
                @endif
            </div>
        @elseif ($joined && in_array($session->status, ['bidding', 'cap_reached']))
            <div class="bg-white shadow-sm sm:rounded-lg p-6" style="max-width: none;">
                <h1 class="text-lg font-semibold text-gray-800 mb-3">Bidding — Session #{{ $session->id }}</h1>
                <livewire:bidding-room :session-id="$session->id" :viewer-participant-id="$viewerParticipantId" :key="'room-member-'.$viewerParticipantId" />
            </div>
        @elseif ($joined)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h1 class="text-lg font-semibold text-gray-800 mb-1">You're in the lobby</h1>
                <p class="text-sm text-gray-500 mb-4">Mark yourself ready. The host starts the bid when everyone's ready.</p>
                <livewire:lobby :session-id="$session->id" :viewer-participant-id="$viewerParticipantId" :key="'lobby-member-'.$viewerParticipantId" />
            </div>
        @elseif ($tooLate)
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-gray-700">Bidding has already started — this link is closed.</p>
            </div>
        @elseif ($full)
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-gray-700">This session is already full.</p>
            </div>
        @else
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h1 class="text-lg font-semibold text-gray-800 mb-1">Join the bidding</h1>
                <p class="text-sm text-gray-500 mb-4">Enter a display name — no account needed.</p>
                <form wire:submit="confirmJoin" class="space-y-4">
                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700">Display name</label>
                        <input wire:model="display_name" id="display_name" type="text" autofocus
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('display_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled"
                            class="w-full px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        <span wire:loading.remove wire:target="confirmJoin">Join</span>
                        <span wire:loading wire:target="confirmJoin">Joining…</span>
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
