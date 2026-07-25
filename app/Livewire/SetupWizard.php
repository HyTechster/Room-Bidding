<?php

namespace App\Livewire;

use App\Models\BiddingSession;
use App\Models\Participant;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SetupWizard extends Component
{
    public int $step = 1;

    // Step 1 — house basics
    public ?string $total_rent = null;         // MYR, decimal string
    public ?int $num_tenants = null;           // N, includes host
    public int $num_rooms = 1;                  // M
    public string $offset_unit = 'percentage'; // 'percentage' | 'fixed'
    public ?string $offset_value = null;       // percent or MYR, depending on unit
    public string $anonymity = 'names_visible';// 'names_visible' | 'anonymous'
    public string $display_name = '';

    // Step 2 — rooms & capacity: array of ['label' => ?string, 'max_occupants' => ?int]
    public array $rooms = [];

    public function mount(): void
    {
        $this->display_name = Auth::user()->name ?? '';
        $this->syncRooms();
    }

    /** Keep the rooms array length in step with num_rooms, preserving entered values. */
    public function updatedNumRooms(): void
    {
        $this->num_rooms = max(1, min(50, (int) $this->num_rooms));
        $this->syncRooms();
    }

    private function syncRooms(): void
    {
        $current = count($this->rooms);
        if ($this->num_rooms > $current) {
            for ($i = $current; $i < $this->num_rooms; $i++) {
                $this->rooms[$i] = ['label' => null, 'max_occupants' => 1];
            }
        } elseif ($this->num_rooms < $current) {
            $this->rooms = array_slice($this->rooms, 0, $this->num_rooms);
        }
        $this->rooms = array_values($this->rooms);
    }

    // ---- Derived values ---------------------------------------------------

    public function getTotalCapacityProperty(): int
    {
        return array_sum(array_map(
            fn ($r) => max(0, (int) ($r['max_occupants'] ?? 0)),
            $this->rooms
        ));
    }

    /** 'c_i' | 'c_ii' | 'c_iii' | null (when tenants unknown). */
    public function getScopeProperty(): ?string
    {
        if ($this->num_tenants === null || $this->totalCapacity <= 0) {
            return null;
        }
        return match (true) {
            $this->num_tenants < $this->totalCapacity => 'c_i',
            $this->num_tenants === $this->totalCapacity => 'c_ii',
            default => 'c_iii',
        };
    }

    public function getIsBlockedProperty(): bool
    {
        return $this->scope === 'c_iii';
    }

    // ---- Validation -------------------------------------------------------

    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'total_rent'   => ['required', 'numeric', 'min:0.01'],
                'num_tenants'  => ['required', 'integer', 'min:1', 'max:1000'],
                'num_rooms'    => ['required', 'integer', 'min:1', 'max:50'],
                'offset_unit'  => ['required', 'in:percentage,fixed'],
                'offset_value' => array_merge(
                    ['required', 'numeric', 'gt:0'],
                    $this->offset_unit === 'percentage' ? ['max:100'] : []
                ),
                'anonymity'    => ['required', 'in:names_visible,anonymous'],
                'display_name' => ['required', 'string', 'max:255'],
            ],
            2 => [
                'rooms'                   => ['required', 'array', 'min:1'],
                'rooms.*.label'           => ['nullable', 'string', 'max:255'],
                'rooms.*.max_occupants'   => ['required', 'integer', 'min:1', 'max:100'],
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'offset_value.max' => 'A percentage offset cannot exceed 100%.',
            'rooms.*.max_occupants.required' => 'Set a max occupancy for every room.',
        ];
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step));
        $this->step = min(3, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // ---- Persist ----------------------------------------------------------

    public function create()
    {
        // Re-validate everything before writing.
        $this->validate($this->rulesForStep(1) + $this->rulesForStep(2));

        if ($this->isBlocked) {
            $this->addError('scope', 'This scenario (more tenants than capacity) is not supported yet.');
            return;
        }

        $rentCents = (int) round(((float) $this->total_rent) * 100);
        $offsetPercent = $this->offset_unit === 'percentage' ? (float) $this->offset_value : null;
        $offsetFixedCents = $this->offset_unit === 'fixed' ? (int) round(((float) $this->offset_value) * 100) : null;

        $session = DB::transaction(function () use ($rentCents, $offsetPercent, $offsetFixedCents) {
            $session = BiddingSession::create([
                'host_user_id'       => Auth::id(),
                'total_rent_cents'   => $rentCents,
                'num_tenants'        => $this->num_tenants,
                'num_rooms'          => $this->num_rooms,
                'offset_unit'        => $this->offset_unit,
                'offset_percent'     => $offsetPercent,
                'offset_fixed_cents' => $offsetFixedCents,
                'anonymity'          => $this->anonymity,
                'currency'           => 'MYR',
                'total_capacity'     => $this->totalCapacity,
                'scope'              => $this->scope,
                'status'             => 'draft',
                'current_round_no'   => 0,
                'round_cap'          => 20,
                'expires_at'         => now()->addDays(7),
            ]);

            foreach ($this->rooms as $i => $room) {
                Room::create([
                    'bidding_session_id' => $session->id,
                    'position'           => $i,
                    'label'              => $room['label'] ?: null,
                    'max_occupants'      => (int) $room['max_occupants'],
                ]);
            }

            // Host is auto-enrolled as a tenant (Part 3.2).
            Participant::create([
                'bidding_session_id' => $session->id,
                'user_id'            => Auth::id(),
                'is_host'            => true,
                'display_name'       => $this->display_name,
                'join_order'         => 1,
                'participant_token'  => Str::random(40),
                'status'             => 'joined',
                'is_ready'           => false,
            ]);

            return $session;
        });

        session()->flash('status', "Session #{$session->id} created. You're enrolled as the host.");

        return $this->redirect(route('sessions.manage', $session), navigate: true);
    }

    public function render()
    {
        return view('livewire.setup-wizard');
    }
}
