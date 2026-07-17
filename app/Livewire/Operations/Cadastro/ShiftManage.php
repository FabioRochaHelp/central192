<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Cadastro;

use App\Domain\Operations\Actions\SaveShiftChecklistAction;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Domain\Operations\Events\ShiftUpdated;
use App\Models\Municipio;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Models\VehicleChecklistResource;
use App\Support\Operations\OperationalMunicipioSelection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Abertura e encerramento de turnos de serviço (docs/migracao/entidades.md — turno). */
#[Layout('layouts.app')]
#[Title('Turnos de serviço')]
final class ShiftManage extends Component
{
    public ?string $vehicle_id = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $status = '';

    /** @var list<int|string> */
    public array $staffIds = [];

    public string $message = '';

    public bool $showChecklistModal = false;

    public ?int $checklistShiftId = null;

    /** @var array<string, string> */
    public array $checklistQuantities = [];

    /** @var list<int|string> */
    public array $checklistSelectedResourceIds = [];

    public string $checklistObservation = '';

    public ?string $selectedOperationalMunicipioId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Shift::class);
        $user = Auth::user();
        if ($user !== null && $user->isOperationalCentral()) {
            $this->selectedOperationalMunicipioId = session('operational_municipio_id') !== null
                ? (string) session('operational_municipio_id')
                : null;
        }
        $this->status = ShiftStatus::Disponivel->value;
        $this->starts_at = now()->format('Y-m-d\TH:i');
    }

    public function updatedSelectedOperationalMunicipioId(?string $value): void
    {
        if ($value === null || $value === '') {
            session()->forget('operational_municipio_id');
        } else {
            session(['operational_municipio_id' => (int) $value]);
        }
    }

    private function municipioId(): ?int
    {
        $user = Auth::user();
        if ($user?->municipio_id !== null) {
            return (int) $user->municipio_id;
        }
        if ($user?->isOperationalCentral()) {
            return $this->selectedOperationalMunicipioId !== null && $this->selectedOperationalMunicipioId !== ''
                ? (int) $this->selectedOperationalMunicipioId
                : null;
        }

        return OperationalMunicipioSelection::current($user);
    }

    public function resetForm(): void
    {
        $this->vehicle_id = '';
        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->ends_at = '';
        $this->status = ShiftStatus::Disponivel->value;
        $this->staffIds = [];
    }

    public function updatedStatus(string $value): void
    {
        $status = ShiftStatus::tryFrom($value);
        if ($status !== null && ! $status->requiresStaffOnOpen()) {
            $this->staffIds = [];
        }
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $mid = $this->municipioId();
        if ($mid === null) {
            $this->addError('scope', __('Defina a base (município) para criar turnos.'));

            return;
        }

        $validated = $this->validate([
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('municipio_id', $mid)),
            ],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'status' => ['required', Rule::enum(ShiftStatus::class)],
            'staffIds' => ShiftStatus::from($this->status)->requiresStaffOnOpen()
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'staffIds.*' => [
                'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('municipio_id', $mid)),
            ],
        ]);

        // Início é sempre o momento atual do servidor
        $starts = CarbonImmutable::now(config('app.timezone'));
        $ends = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone'));

        if ($ends->isPast()) {
            $this->addError('ends_at', __('A data/hora de fim deve ser futura.'));

            return;
        }

        $vehicleId = (int) $validated['vehicle_id'];
        if ($this->vehicleOverlapsAnotherShift($vehicleId, $starts, $ends)) {
            $this->addError('vehicle_id', __('Esta viatura já possui turno com período sobreposto.'));

            return;
        }

        $shiftStatus = ShiftStatus::from($validated['status']);
        $staffPivotIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $validated['staffIds'] ?? [])));

        if ($shiftStatus->requiresStaffOnOpen() && $staffPivotIds !== []) {
            $overlappingNames = Staff::query()
                ->whereIn('id', $staffPivotIds)
                ->whereHas('shifts', fn ($q) => $q
                    ->where('starts_at', '<', $ends)
                    ->where('ends_at', '>', $starts))
                ->pluck('name')
                ->implode(', ');

            if ($overlappingNames !== '') {
                $this->addError('staffIds', __('Efetivo já escalado em outro turno neste período: :names.', ['names' => $overlappingNames]));

                return;
            }
        }

        $this->authorize('create', Shift::class);
        $shift = Shift::query()->create([
            'municipio_id' => $mid,
            'vehicle_id' => $vehicleId,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => $shiftStatus,
            'status_legacy' => match ($shiftStatus) {
                ShiftStatus::Disponivel => 1,
                ShiftStatus::Empenhado => 2,
                default => null,
            },
            'available_at' => $shiftStatus === ShiftStatus::Disponivel ? $starts : null,
        ]);
        $shift->staff()->sync($staffPivotIds);

        ShiftUpdated::dispatch($shift->fresh());

        $this->message = __('Turno criado.');

        $this->resetForm();

        if ($shiftStatus->requiresChecklistOnOpen()) {
            $this->openChecklistModal($shift->id);
        }
    }

    public function openChecklistModal(int $shiftId): void
    {
        $shift = Shift::query()->with(['checklistItems', 'vehicle'])->findOrFail($shiftId);
        $this->authorize('update', $shift);

        $this->checklistShiftId = $shift->id;
        $this->checklistObservation = (string) ($shift->checklist_observation ?? '');
        $this->checklistQuantities = [];
        $this->checklistSelectedResourceIds = [];

        $existing = $shift->checklistItems->keyBy('vehicle_checklist_resource_id');
        VehicleChecklistResource::query()
            ->where('municipio_id', $shift->municipio_id)
            ->orderBy('name')
            ->get()
            ->each(function (VehicleChecklistResource $resource) use ($existing): void {
                $item = $existing->get($resource->id);
                if ($item !== null) {
                    $this->checklistSelectedResourceIds[] = $resource->id;
                    $this->checklistQuantities[(string) $resource->id] = (string) (int) $item->quantity;
                }
            });

        $this->showChecklistModal = true;
    }

    public function closeChecklistModal(): void
    {
        $this->showChecklistModal = false;
        $this->checklistShiftId = null;
        $this->checklistQuantities = [];
        $this->checklistSelectedResourceIds = [];
        $this->checklistObservation = '';
    }

    public function saveChecklist(bool $markComplete, SaveShiftChecklistAction $action): void
    {
        $this->resetErrorBag();

        if ($this->checklistShiftId === null) {
            return;
        }

        $shift = Shift::query()->findOrFail($this->checklistShiftId);
        $this->authorize('update', $shift);

        try {
            $action->execute(
                $shift,
                $this->checklistSelectedResourceIds,
                $this->checklistQuantities,
                $markComplete,
                $this->checklistObservation !== '' ? $this->checklistObservation : null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        ShiftUpdated::dispatch($shift->fresh());

        $this->message = $markComplete
            ? __('Check-list do turno #:id concluído.', ['id' => $shift->id])
            : __('Check-list do turno #:id salvo com observação.', ['id' => $shift->id]);

        $this->closeChecklistModal();
    }

    public function closeShift(int $id): void
    {
        $this->message = '';
        $this->resetErrorBag();

        $shift = Shift::query()->findOrFail($id);
        $this->authorize('update', $shift);

        if (! $shift->starts_at->isPast() || ! $shift->ends_at->isFuture()) {
            $this->addError('close', __('Este turno não está ativo no momento.'));

            return;
        }

        if ($shift->incidentDispatches()->exists()) {
            $this->addError('close', __('Não é possível encerrar: viatura está em despacho ativo.'));

            return;
        }

        $shift->update([
            'ends_at' => now(),
            'status' => ShiftStatus::Baixado,
        ]);

        ShiftUpdated::dispatch($shift->fresh());

        $this->message = __('Turno #:id encerrado antecipadamente.', ['id' => $id]);
    }

    public function render(): View
    {
        $mid = $this->municipioId();

        // Viaturas e efetivo já em turno ativo ficam indisponíveis para novo turno
        $activeVehicleIds = Shift::query()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->pluck('vehicle_id')
            ->filter()
            ->all();

        $activeStaffIds = DB::table('shift_staff')
            ->join('shifts', 'shifts.id', '=', 'shift_staff.shift_id')
            ->where('shifts.starts_at', '<=', now())
            ->where('shifts.ends_at', '>', now())
            ->pluck('shift_staff.staff_id')
            ->unique()
            ->all();

        $vehicleQuery = Vehicle::query()->orderBy('prefix')->whereNotIn('id', $activeVehicleIds);
        $staffQuery = Staff::query()->orderBy('name')->whereNotIn('id', $activeStaffIds);

        if ($mid !== null) {
            $vehicleQuery->where('municipio_id', $mid);
            $staffQuery->where('municipio_id', $mid);
        }

        $shiftQuery = Shift::query()->with(['vehicle', 'staff', 'checklistItems'])->orderByDesc('starts_at');
        if ($mid !== null) {
            $shiftQuery->where('municipio_id', $mid);
        }

        return view('livewire.operations.cadastro.shift-manage', [
            'scopeMunicipioId' => $mid,
            'vehicles' => $vehicleQuery->get(),
            'staffMembers' => $staffQuery->get(),
            'shifts' => $shiftQuery->limit(120)->get(),
            'statusCases' => ShiftStatus::openableCases(),
            'checklistResources' => $mid !== null
                ? VehicleChecklistResource::query()->where('municipio_id', $mid)->orderBy('name')->get()
                : collect(),
            'operationalMunicipios' => Auth::user()?->isOperationalCentral()
                ? Municipio::query()->where('active', true)->orderBy('razao_social')->get()
                : collect(),
        ]);
    }

    private function vehicleOverlapsAnotherShift(int $vehicleId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return Shift::query()
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }
}
