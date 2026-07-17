<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Actions\AdvanceDispatchStageAction;
use App\Domain\Operations\Actions\AppendIncidentDescriptionAction;
use App\Domain\Operations\Actions\CancelDispatchAtSceneAction;
use App\Domain\Operations\Actions\CancelIncidentAction;
use App\Domain\Operations\Actions\CreateOperationalIncidentAction;
use App\Domain\Operations\Actions\DispatchUnitAction;
use App\Domain\Operations\Actions\FinalizeIncidentClosureAction;
use App\Domain\Operations\Actions\RegisterDispatchContactAttemptAction;
use App\Domain\Operations\Actions\ReleaseUnitAction;
use App\Domain\Operations\DTOs\AdvanceDispatchStageDTO;
use App\Domain\Operations\DTOs\CreateIncidentDTO;
use App\Domain\Operations\DTOs\DispatchContactAttemptDTO;
use App\Domain\Operations\DTOs\DispatchUnitDTO;
use App\Domain\Operations\DTOs\FinalizeIncidentClosureDTO;
use App\Domain\Operations\DTOs\ReleaseUnitDTO;
use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\DispatchSceneCancelReason;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\IncidentEvent;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Operations\DispatchFairQueueShiftSorter;
use App\Support\Operations\DispatchNoteSignal;
use App\Support\Operations\DispatchProximityShiftSorter;
use App\Support\Operations\DispatchQueueIncidentSorter;
use App\Support\Operations\DispatchReleaseRules;
use App\Support\Operations\IncidentOperationalState;
use App\Support\Operations\NearestVehicleResolver;
use App\Support\Operations\OperationalCallAlertGrouper;
use App\Support\Operations\OperationalIncidentVisibility;
use App\Support\Operations\OperationalMunicipioSelection;
use App\Support\Operations\TacticalMapVehicleQuery;
use App\Support\Operations\VehicleTrackerStatusQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Central operacional')]
final class DispatchBoard extends Component
{
    /** Modal de empenho: ocorrência escolhida na fila. */
    public bool $showDispatchModal = false;

    public ?int $dispatchingIncidentId = null;

    public ?int $modalVehicleId = null;

    public bool $dispatchModalIsSupport = false;

    public string $dispatchContactMethod = '';

    public string $dispatchContactDetails = '';

    public bool $dispatchContactSuccessful = true;

    public string $dispatchContactReason = '';

    public const DISPATCH_CONTACT_METHODS = [
        'ramal' => 'Ramal',
        'telefone' => 'Telefone',
        'whatsapp' => 'WhatsApp',
    ];

    /** Encerramento: última viatura na base — escolher principal. */
    public bool $showClosureModal = false;

    public ?int $closureIncidentId = null;

    public ?int $closurePrimaryShiftId = null;

    /** Modais de ação na fila: cancelar, observação, detalhe. */
    public bool $showCancelModal = false;

    public bool $showObservationModal = false;

    public bool $showDetailModal = false;

    public ?int $actionIncidentId = null;

    public string $cancelReason = '';

    public string $observationText = '';

    /** Modal de check-list do turno (coluna de turnos disponíveis). */
    public bool $showShiftChecklistModal = false;

    public ?int $shiftChecklistShiftId = null;

    /** Modal do card no kanban operacional. */
    public bool $showKanbanModal = false;

    public ?int $kanbanDispatchId = null;

    public bool $showKanbanSupportPanel = false;

    public ?int $kanbanSupportVehicleId = null;

    /** ID numérico em `municipios` para usuários centrais (sessão). */
    public ?string $selectedOperationalMunicipioId = null;

    public string $boardMessage = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasOperationalAbility('dispatch.view'), 403);

        $user = Auth::user();
        if ($user !== null && $user->hasMultiMunicipioAccess()) {
            $this->selectedOperationalMunicipioId = session('operational_municipio_id') !== null
                ? (string) session('operational_municipio_id')
                : null;
        }
    }

    public function updatedSelectedOperationalMunicipioId(?string $value): void
    {
        if ($value === null || $value === '') {
            session()->forget('operational_municipio_id');
        } else {
            session(['operational_municipio_id' => (int) $value]);
        }
    }

    public function resolveOperationalMunicipioId(): ?int
    {
        return OperationalMunicipioSelection::current(Auth::user());
    }

    public function createDemoIncident(CreateOperationalIncidentAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        $nature = Nature::query()->orderBy('id')->first();
        if ($nature === null) {
            $this->addError('tenant', 'Cadastre ao menos uma natureza.');

            return;
        }

        Gate::authorize('createOperational');

        $dto = new CreateIncidentDTO(
            municipioId: null,
            natureId: $nature->id,
            description: 'Ocorrência demonstrativa (CCO)',
            addressLine: null,
            number: null,
            district: null,
            city: null,
            callerName: 'Central',
            callerPhone: null,
            patientAge: null,
            patientSex: null,
            latitude: null,
            longitude: null,
            referenceNotes: null,
            callType: CallType::Normal,
            expectedVictimTotal: null,
            createdByUserId: Auth::id(),
        );

        try {
            $action->execute($dto);
            $this->boardMessage = 'Ocorrência registrada.';
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function openDispatchModal(int $incidentId, bool $support = false): void
    {
        $this->resetErrorBag();
        $this->resetDispatchContactFields();

        /** @var Incident|null $incident */
        $incident = Incident::query()->with(['dispatches.shift.vehicle', 'municipio'])->find($incidentId);
        if ($incident === null) {
            return;
        }

        if ($support) {
            if (! in_array($incident->status, [IncidentStatus::Dispatched, IncidentStatus::InProgress], true)) {
                return;
            }
        } elseif ($incident->status !== IncidentStatus::Open) {
            return;
        }

        Gate::authorize('dispatchUnit', $incident);

        $shiftQuery = Shift::query()
            ->when(
                $support,
                fn ($q) => $q->operationalForDispatch(),
                fn ($q) => $q->operationalAvailability(),
            )
            ->when($incident->municipio_id !== null, fn ($q) => $q->where('municipio_id', $incident->municipio_id));

        $firstShift = DispatchProximityShiftSorter::sort($shiftQuery->get(), $incident)->first();

        $this->dispatchingIncidentId = $incident->id;
        $this->dispatchModalIsSupport = $support;
        $this->modalVehicleId = $firstShift?->vehicle_id;
        $this->showDispatchModal = true;
    }

    public function openSupportDispatchModal(int $incidentId): void
    {
        $this->openDispatchModal($incidentId, support: true);
    }

    public function closeDispatchModal(): void
    {
        $this->showDispatchModal = false;
        $this->dispatchingIncidentId = null;
        $this->modalVehicleId = null;
        $this->dispatchModalIsSupport = false;
        $this->resetDispatchContactFields();
    }

    private function resetDispatchContactFields(): void
    {
        $this->dispatchContactMethod = '';
        $this->dispatchContactDetails = '';
        $this->dispatchContactSuccessful = true;
        $this->dispatchContactReason = '';
    }

    private function resolveNearestDispatchShift(Incident $incident): ?Shift
    {
        $query = Shift::query()
            ->with(['vehicle', 'staff'])
            ->when(
                $this->dispatchModalIsSupport,
                fn ($q) => $q->operationalForDispatch(),
                fn ($q) => $q->operationalAvailability(),
            );

        if ($incident->municipio_id !== null) {
            $query->where('municipio_id', $incident->municipio_id);
        }

        return DispatchProximityShiftSorter::sort($query->get(), $incident)->first();
    }

    private function nearestVehiclePayload(?Shift $shift): ?array
    {
        if ($shift === null) {
            return null;
        }

        return [
            'shift_id' => $shift->id,
            'vehicle_id' => $shift->vehicle_id,
            'vehicle_prefix' => $shift->vehicle?->prefix,
            'vehicle_plate' => $shift->vehicle?->plate,
            'responsible_staff' => $shift->staff->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'cargo' => $member->cargo,
            ])->values()->all(),
        ];
    }

    /** Reverb: qualquer evento operacional no canal operations.dispatch força re-render. */
    #[On('dispatch-board-refresh')]
    public function refreshFromBroadcast(): void
    {
        // O re-render acontece automaticamente ao receber o evento.
    }

    /** Modal PBX é tratado por `OperationalCallIntakeBridge` no layout; aqui só atualizamos o aviso da Central. */
    #[On('call-intake-incident-saved')]
    public function onCallIntakeIncidentSaved(int $incidentId): void
    {
        $this->boardMessage = __('Ocorrência registrada (#:id).', ['id' => $incidentId]);
    }

    #[On('call-intake-request-attached')]
    public function onCallIntakeRequestAttached(int $incidentId): void
    {
        $this->boardMessage = __('Solicitação somada à ocorrência (#:id).', ['id' => $incidentId]);
    }

    public function confirmDispatch(DispatchUnitAction $action, RegisterDispatchContactAttemptAction $registerContact): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        $rules = [
            'modalVehicleId' => ['required', 'integer'],
            'dispatchContactMethod' => ['required', 'string', Rule::in(array_keys(self::DISPATCH_CONTACT_METHODS))],
            'dispatchContactDetails' => ['required', 'string'],
            'dispatchContactSuccessful' => ['boolean'],
        ];

        if (! $this->dispatchContactSuccessful) {
            $rules['dispatchContactReason'] = ['required', 'string'];
        }

        $this->validate(
            $rules,
            [
                'modalVehicleId.required' => __('Selecione a viatura em turno.'),
                'dispatchContactMethod.required' => __('Selecione o método de contato.'),
                'dispatchContactDetails.required' => __('Informe os detalhes do contato.'),
                'dispatchContactReason.required' => __('Informe o motivo quando o contato não for bem-sucedido.'),
            ],
        );

        if ($this->dispatchingIncidentId === null) {
            $this->closeDispatchModal();

            return;
        }

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($this->dispatchingIncidentId);
        if ($incident === null) {
            $this->closeDispatchModal();

            return;
        }

        $allowed = $this->dispatchModalIsSupport
            ? [IncidentStatus::Dispatched, IncidentStatus::InProgress]
            : [IncidentStatus::Open];

        if (! in_array($incident->status, $allowed, true)) {
            $this->closeDispatchModal();

            return;
        }

        Gate::authorize('dispatchUnit', $incident);

        $nearestShift = $this->resolveNearestDispatchShift($incident);
        $registerContact->execute(new DispatchContactAttemptDTO(
            incidentId: $incident->id,
            vehicleId: $this->modalVehicleId,
            contactMethod: $this->dispatchContactMethod,
            contactDetails: $this->dispatchContactDetails,
            successful: $this->dispatchContactSuccessful,
            reason: $this->dispatchContactSuccessful ? null : $this->dispatchContactReason,
            nearestVehicle: $this->nearestVehiclePayload($nearestShift),
        ));

        if (! $this->dispatchContactSuccessful) {
            $this->boardMessage = __('Contato registrado. Viatura não empenhada e responsável pela viatura mais próxima foi acionado.');
            $this->closeDispatchModal();

            return;
        }

        try {
            $action->execute(new DispatchUnitDTO(
                incidentId: $incident->id,
                vehicleId: $this->modalVehicleId,
                note: null,
                operatorUserId: Auth::id(),
            ));
            $this->boardMessage = $this->dispatchModalIsSupport
                ? __('Viatura de apoio empenhada.')
                : __('Equipe empenhada.');
            $this->closeDispatchModal();
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function advanceStage(int $dispatchId, AdvanceDispatchStageAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->find($dispatchId);
        if ($dispatch === null) {
            return;
        }

        Gate::authorize('advanceStage', $dispatch->incident);

        $target = $dispatch->stage->next();
        if ($target === null) {
            return;
        }

        try {
            $action->execute(new AdvanceDispatchStageDTO(
                incidentDispatchId: $dispatch->id,
                targetStage: $target,
                operatorUserId: Auth::id(),
            ));
            $this->boardMessage = __('Etapa atualizada.');
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function moveKanbanDispatch(int $dispatchId, int $position, ?string $targetStageId = null): void
    {
        if ($targetStageId === null) {
            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->find($dispatchId);
        if ($dispatch === null) {
            return;
        }

        if ($targetStageId === $dispatch->stage->value) {
            return;
        }

        Gate::authorize('advanceStage', $dispatch->incident);

        $target = DispatchStage::tryFrom($targetStageId);
        if ($target === null) {
            return;
        }

        if ($target->index() !== $dispatch->stage->index() + 1) {
            $this->addError('board', __('Só é permitido avançar uma etapa por vez no kanban.'));

            return;
        }

        $this->advanceStage($dispatchId, app(AdvanceDispatchStageAction::class));
    }

    public function openKanbanModal(int $dispatchId): void
    {
        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->with('incident')->find($dispatchId);
        if ($dispatch === null) {
            return;
        }

        Gate::authorize('view', $dispatch->incident);

        $this->kanbanDispatchId = $dispatchId;
        $this->showKanbanSupportPanel = false;
        $this->kanbanSupportVehicleId = null;
        $this->showKanbanModal = true;
    }

    public function closeKanbanModal(): void
    {
        $this->showKanbanModal = false;
        $this->kanbanDispatchId = null;
        $this->showKanbanSupportPanel = false;
        $this->kanbanSupportVehicleId = null;
    }

    public function toggleKanbanSupportPanel(): void
    {
        if ($this->kanbanDispatchId === null) {
            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->with('incident')->find($this->kanbanDispatchId);
        if ($dispatch === null || $dispatch->incident === null) {
            return;
        }

        Gate::authorize('dispatchUnit', $dispatch->incident);

        if ($this->showKanbanSupportPanel) {
            $this->showKanbanSupportPanel = false;
            $this->kanbanSupportVehicleId = null;

            return;
        }

        $firstShift = $this->resolveKanbanSupportShifts($dispatch->incident)->first();
        $this->kanbanSupportVehicleId = $firstShift?->vehicle_id;
        $this->showKanbanSupportPanel = true;
    }

    public function confirmKanbanSupportDispatch(DispatchUnitAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        $this->validate(
            [
                'kanbanSupportVehicleId' => ['required', 'integer'],
            ],
            [
                'kanbanSupportVehicleId.required' => __('Selecione a viatura de apoio.'),
            ],
        );

        if ($this->kanbanDispatchId === null) {
            $this->showKanbanSupportPanel = false;
            $this->kanbanSupportVehicleId = null;

            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->with('incident')->find($this->kanbanDispatchId);
        if ($dispatch === null || $dispatch->incident === null) {
            $this->showKanbanSupportPanel = false;
            $this->kanbanSupportVehicleId = null;

            return;
        }

        $incident = $dispatch->incident;

        if (! in_array($incident->status, [IncidentStatus::Dispatched, IncidentStatus::InProgress], true)) {
            $this->showKanbanSupportPanel = false;
            $this->kanbanSupportVehicleId = null;

            return;
        }

        Gate::authorize('dispatchUnit', $incident);

        try {
            $action->execute(new DispatchUnitDTO(
                incidentId: $incident->id,
                vehicleId: (int) $this->kanbanSupportVehicleId,
                note: null,
                operatorUserId: Auth::id(),
            ));
            $this->boardMessage = __('Viatura de apoio empenhada.');
            $this->showKanbanSupportPanel = false;
            $this->kanbanSupportVehicleId = null;
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    /**
     * @return Collection<int, Shift>
     */
    private function resolveKanbanSupportShifts(Incident $incident): Collection
    {
        $activeShiftIds = IncidentDispatch::query()
            ->where('incident_id', $incident->id)
            ->whereNull('deleted_at')
            ->pluck('shift_id');

        return DispatchFairQueueShiftSorter::sort(
            Shift::query()
                ->with(['vehicle', 'staff:id,name,cargo', 'checklistItems.resource'])
                ->withCount('staff')
                ->operationalForDispatch()
                ->when($incident->municipio_id !== null, fn ($q) => $q->where('municipio_id', $incident->municipio_id))
                ->when($activeShiftIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $activeShiftIds))
                ->get(),
        );
    }

    public function advanceKanbanStage(AdvanceDispatchStageAction $action): void
    {
        if ($this->kanbanDispatchId === null) {
            return;
        }

        $this->advanceStage($this->kanbanDispatchId, $action);
    }

    public function releaseKanbanUnit(ReleaseUnitAction $action): void
    {
        if ($this->kanbanDispatchId === null) {
            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->with('shift')->find($this->kanbanDispatchId);
        if ($dispatch === null || $dispatch->shift?->vehicle_id === null) {
            return;
        }

        $this->releaseIncident($dispatch->incident_id, (int) $dispatch->shift->vehicle_id, $action);
        $this->closeKanbanModal();
    }

    public function cancelDispatchAtScene(string $reason, CancelDispatchAtSceneAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        if ($this->kanbanDispatchId === null) {
            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->find($this->kanbanDispatchId);
        if ($dispatch === null) {
            $this->closeKanbanModal();

            return;
        }

        Gate::authorize('advanceStage', $dispatch->incident);

        $cancelReason = DispatchSceneCancelReason::tryFrom($reason);
        if ($cancelReason === null) {
            $this->addError('board', __('Motivo de cancelamento inválido.'));

            return;
        }

        try {
            $action->execute($dispatch, $cancelReason, (int) Auth::id());
            $this->boardMessage = __('Ocorrência cancelada — saída do local registrada.');
            $this->closeKanbanModal();
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function releaseIncident(int $incidentId, int $vehicleId, ReleaseUnitAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            return;
        }

        Gate::authorize('releaseUnit', $incident);

        try {
            $result = $action->execute(new ReleaseUnitDTO(
                incidentId: $incident->id,
                vehicleId: $vehicleId,
                operatorUserId: Auth::id(),
            ));

            if ($result->requiresIncidentClosure) {
                $candidates = IncidentOperationalState::closureCandidateDispatches($result->incident);
                $this->closureIncidentId = $result->incident->id;
                $this->closurePrimaryShiftId = $candidates->count() === 1
                    ? (int) $candidates->first()->shift_id
                    : null;
                $this->showClosureModal = true;
                $this->boardMessage = __('Última viatura na base. Escolha a viatura principal para encerrar.');
            } else {
                $this->boardMessage = IncidentOperationalState::activeDispatchCount($result->incident) > 0
                    ? __('Viatura liberada. Ocorrência continua em atendimento.')
                    : ($result->incident->status === IncidentStatus::Qta
                        ? __('Viatura liberada. Ocorrência encerrada como QTA.')
                        : __('Viatura liberada.'));
            }
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function closeClosureModal(): void
    {
        $this->showClosureModal = false;
        $this->closureIncidentId = null;
        $this->closurePrimaryShiftId = null;
    }

    public function finalizeIncidentClosure(FinalizeIncidentClosureAction $action): void
    {
        $this->resetErrorBag();

        $this->validate(
            ['closurePrimaryShiftId' => ['required', 'integer']],
            ['closurePrimaryShiftId.required' => __('Selecione a viatura principal.')],
        );

        if ($this->closureIncidentId === null) {
            $this->closeClosureModal();

            return;
        }

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($this->closureIncidentId);
        if ($incident === null) {
            $this->closeClosureModal();

            return;
        }

        Gate::authorize('releaseUnit', $incident);

        try {
            $action->execute(new FinalizeIncidentClosureDTO(
                incidentId: $incident->id,
                primaryShiftId: (int) $this->closurePrimaryShiftId,
                operatorUserId: Auth::id(),
            ));
            $this->boardMessage = __('Ocorrência encerrada.');
            $this->closeClosureModal();
            $this->closeKanbanModal();
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());
        }
    }

    public function openCancelModal(int $incidentId): void
    {
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            return;
        }

        Gate::authorize('cancel', $incident);

        $this->actionIncidentId = $incidentId;
        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showCancelModal = true;
    }

    public function cancelIncident(CancelIncidentAction $action): void
    {
        $this->resetErrorBag();

        $this->validate(
            ['cancelReason' => ['required', 'string', 'min:5', 'max:500']],
            ['cancelReason.required' => __('Informe o motivo do cancelamento.'), 'cancelReason.min' => __('O motivo deve ter ao menos 5 caracteres.')],
        );

        if ($this->actionIncidentId === null) {
            $this->showCancelModal = false;

            return;
        }

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($this->actionIncidentId);
        if ($incident === null) {
            $this->showCancelModal = false;

            return;
        }

        Gate::authorize('cancel', $incident);

        /** @var User $user */
        $user = Auth::user();
        $action->execute($incident, $this->cancelReason, $user);

        $this->boardMessage = __('Ocorrência cancelada.');
        $this->showCancelModal = false;
        $this->actionIncidentId = null;
        $this->cancelReason = '';
    }

    public function openObservationModal(int $incidentId): void
    {
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            return;
        }

        Gate::authorize('addObservation', $incident);

        $this->actionIncidentId = $incidentId;
        $this->observationText = '';
        $this->resetErrorBag();
        $this->showObservationModal = true;
    }

    public function saveObservation(AppendIncidentDescriptionAction $action): void
    {
        $this->resetErrorBag();

        $this->validate(
            ['observationText' => ['required', 'string', 'min:3', 'max:2000']],
            ['observationText.required' => __('Informe o texto da nova anotação.')],
        );

        if ($this->actionIncidentId === null) {
            $this->showObservationModal = false;

            return;
        }

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($this->actionIncidentId);
        if ($incident === null) {
            $this->showObservationModal = false;

            return;
        }

        Gate::authorize('addObservation', $incident);

        /** @var User $user */
        $user = Auth::user();
        $action->execute($incident, $this->observationText, $user);

        $this->boardMessage = __('Descrição atualizada.');
        $this->showObservationModal = false;
        $this->actionIncidentId = null;
        $this->observationText = '';
    }

    public function openDetailModal(int $incidentId): void
    {
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            return;
        }

        Gate::authorize('view', $incident);

        $this->actionIncidentId = $incidentId;
        $this->showDetailModal = true;
    }

    public function openDispatchFromDetail(): void
    {
        if ($this->actionIncidentId === null) {
            return;
        }

        $incidentId = $this->actionIncidentId;
        $this->showDetailModal = false;
        $this->openDispatchModal($incidentId);
    }

    public function openObservationFromDetail(): void
    {
        if ($this->actionIncidentId === null) {
            return;
        }

        $incidentId = $this->actionIncidentId;
        $this->showDetailModal = false;
        $this->openObservationModal($incidentId);
    }

    public function openCancelFromDetail(): void
    {
        if ($this->actionIncidentId === null) {
            return;
        }

        $incidentId = $this->actionIncidentId;
        $this->showDetailModal = false;
        $this->openCancelModal($incidentId);
    }

    /**
     * Balão do card: abre a descrição e marca as anotações como repassadas à guarnição.
     *
     * Só este empenho é marcado — cada viatura da ocorrência tem seu próprio sinal.
     */
    public function openNotesFromKanbanCard(int $dispatchId): void
    {
        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->with('incident')->find($dispatchId);
        if ($dispatch?->incident === null) {
            return;
        }

        Gate::authorize('addObservation', $dispatch->incident);

        DispatchNoteSignal::markSeen($dispatch);

        $this->openObservationModal((int) $dispatch->incident_id);
    }

    public function openObservationFromKanban(): void
    {
        if ($this->kanbanDispatchId === null) {
            return;
        }

        /** @var IncidentDispatch|null $dispatch */
        $dispatch = IncidentDispatch::query()->find($this->kanbanDispatchId);
        if ($dispatch === null) {
            return;
        }

        $incidentId = (int) $dispatch->incident_id;
        $this->showKanbanModal = false;
        $this->kanbanDispatchId = null;
        $this->openObservationModal($incidentId);
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;

        if (! $this->showObservationModal && ! $this->showCancelModal) {
            $this->actionIncidentId = null;
        }
    }

    public function closeObservationModal(): void
    {
        $this->showObservationModal = false;
        $this->observationText = '';

        if (! $this->showDetailModal && ! $this->showCancelModal) {
            $this->actionIncidentId = null;
        }
    }

    public function closeCancelModalOnly(): void
    {
        $this->showCancelModal = false;
        $this->cancelReason = '';

        if (! $this->showDetailModal && ! $this->showObservationModal) {
            $this->actionIncidentId = null;
        }
    }

    public function closeActionModals(): void
    {
        $this->showCancelModal = false;
        $this->showObservationModal = false;
        $this->showDetailModal = false;
        $this->actionIncidentId = null;
        $this->cancelReason = '';
        $this->observationText = '';
    }

    public function openShiftChecklistModal(int $shiftId): void
    {
        $shift = Shift::query()->with(['vehicle', 'checklistItems.resource'])->find($shiftId);
        if ($shift === null) {
            return;
        }

        Gate::authorize('view', $shift);

        $this->shiftChecklistShiftId = $shiftId;
        $this->showShiftChecklistModal = true;
    }

    public function closeShiftChecklistModal(): void
    {
        $this->showShiftChecklistModal = false;
        $this->shiftChecklistShiftId = null;
    }

    public function render(): View
    {
        $municipioOptions = Auth::user()?->hasMultiMunicipioAccess()
            ? Municipio::query()->orderBy('razao_social')->get()
            : collect();

        $mid = OperationalMunicipioSelection::current(Auth::user());

        $openIncidentsQuery = Incident::query()
            ->with(['nature', 'municipio', 'regulation'])
            ->withCount('callRequests')
            ->where('status', IncidentStatus::Open);

        OperationalIncidentVisibility::constrainListing($openIncidentsQuery, Auth::user());

        $openIncidents = DispatchQueueIncidentSorter::sort($openIncidentsQuery->clone()->get());

        $availableShiftsQuery = Shift::query()
            ->with(['vehicle', 'municipio', 'staff:id,name,cargo', 'checklistItems.resource'])
            ->withCount('staff')
            ->operationalAvailability()
            ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid));

        $availableShifts = DispatchFairQueueShiftSorter::sort($availableShiftsQuery->clone()->get());

        $trackerStatusByVehicleId = VehicleTrackerStatusQuery::forVehicles(
            $availableShifts->map(fn (Shift $shift) => $shift->vehicle),
        );

        /** Viaturas cadastradas sem turno ainda vigente (`ends_at >= now()`). */
        $vehiclesWithoutShiftQuery = Vehicle::query()
            ->with('municipio')
            ->whereDoesntHave(
                'shifts',
                fn ($q) => $q->where('ends_at', '>=', now()),
            )
            ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid));

        $vehiclesWithoutShift = $vehiclesWithoutShiftQuery->orderBy('prefix')->limit(120)->get();

        $operationalIdleShiftsQuery = Shift::query()
            ->with(['vehicle', 'municipio'])
            ->operationalIdle()
            ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid));

        $operationalIdleShifts = $operationalIdleShiftsQuery
            ->orderBy('status')
            ->orderBy('id')
            ->limit(120)
            ->get();

        $kanbanDispatches = IncidentDispatch::query()
            ->with(['incident.nature', 'shift.vehicle'])
            ->whereNull('deleted_at')
            ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (IncidentDispatch $d) => $d->stage->value);

        $dispatchUnseenNoteCounts = DispatchNoteSignal::unseenCountsByDispatchId($kanbanDispatches->flatten());

        $dispatchFireMeta = $kanbanDispatches->flatten()->mapWithKeys(function (IncidentDispatch $d): array {
            $incident = $d->incident;
            $modality = $incident?->nature?->report_modality;
            $closesAtLeftScene = $modality instanceof IncidentReportModality && $modality->closesAtLeftScene();

            return [$d->id => [
                'closesAtLeftScene' => $closesAtLeftScene,
                'releaseStage' => $incident !== null
                    ? DispatchReleaseRules::requiredReleaseStage($incident)
                    : DispatchStage::ReleasedHospital,
                'canRelease' => $incident !== null && DispatchReleaseRules::canReleaseVehicle($d, $incident),
            ]];
        });

        $recentTimeline = IncidentEvent::query()
            ->with(['incident', 'actor'])
            ->when($mid !== null, fn ($q) => $q->where(static function ($w) use ($mid): void {
                $w->where('municipio_id', $mid)->orWhereNull('municipio_id');
            }))
            ->latest('recorded_at')
            ->limit(25)
            ->get();

        $stats = [
            'open_incidents' => $openIncidentsQuery->clone()->count(),
            'active_dispatches' => IncidentDispatch::query()
                ->whereNull('deleted_at')
                ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid))
                ->count(),
            'available_units' => $availableShiftsQuery->clone()->count(),
            'idle_vehicles' => $vehiclesWithoutShiftQuery->clone()->count() + $operationalIdleShiftsQuery->clone()->count(),
            'pending_call_alerts' => OperationalCallAlertGrouper::activeOnMapQuery()->count(),
        ];

        $modalIncident = $this->dispatchingIncidentId !== null
            ? Incident::query()->with(['municipio', 'nature', 'dispatches.shift.vehicle'])->find($this->dispatchingIncidentId)
            : null;

        $modalShiftsQuery = Shift::query()
            ->with(['vehicle', 'municipio', 'staff:id,name,cargo', 'checklistItems.resource'])
            ->withCount('staff')
            ->when(
                $this->dispatchModalIsSupport,
                fn ($q) => $q->operationalForDispatch(),
                fn ($q) => $q->operationalAvailability(),
            );

        $modalShifts = $modalIncident !== null
            ? DispatchProximityShiftSorter::sort(
                $modalShiftsQuery
                    ->when(
                        $modalIncident->municipio_id !== null,
                        fn ($q) => $q->where('municipio_id', $modalIncident->municipio_id),
                    )
                    ->get(),
                $modalIncident,
            )
            : collect();

        $modalShiftDistances = $modalIncident !== null
            ? NearestVehicleResolver::distancesByVehicleId($modalIncident)
            : collect();

        // Sugestões de contato pré-despacho por viatura (base/município do turno selecionado).
        $modalContactSuggestions = $modalShifts
            ->filter(fn (Shift $shift) => $shift->vehicle_id !== null)
            ->mapWithKeys(fn (Shift $shift) => [
                (int) $shift->vehicle_id => [
                    'ramal' => $shift->municipio?->dispatch_contact_ramal,
                    'telefone' => $shift->municipio?->dispatch_contact_phone,
                    'whatsapp' => $shift->municipio?->dispatch_contact_whatsapp,
                ],
            ])
            ->all();

        $actionIncident = $this->actionIncidentId !== null
            ? Incident::query()
                ->with(['nature', 'municipio', 'callRequests.creator'])
                ->withCount('callRequests')
                ->find($this->actionIncidentId)
            : null;

        $shiftChecklistShift = $this->shiftChecklistShiftId !== null
            ? Shift::query()
                ->with(['vehicle', 'checklistItems.resource', 'municipio'])
                ->find($this->shiftChecklistShiftId)
            : null;

        $kanbanDispatch = $this->kanbanDispatchId !== null
            ? IncidentDispatch::query()
                ->with(['incident.nature', 'incident.municipio', 'incident.dispatches.shift.vehicle', 'shift.vehicle'])
                ->find($this->kanbanDispatchId)
            : null;

        $kanbanSupportShifts = $kanbanDispatch !== null
            && $kanbanDispatch->incident !== null
            && $this->showKanbanSupportPanel
            ? $this->resolveKanbanSupportShifts($kanbanDispatch->incident)
            : collect();

        $kanbanFireMeta = $kanbanDispatch !== null && $kanbanDispatch->incident !== null
            ? [
                'closesAtLeftScene' => ($modality = $kanbanDispatch->incident->nature?->report_modality) instanceof IncidentReportModality
                    && $modality->closesAtLeftScene(),
                'releaseStage' => DispatchReleaseRules::requiredReleaseStage($kanbanDispatch->incident),
                'canRelease' => DispatchReleaseRules::canReleaseVehicle($kanbanDispatch, $kanbanDispatch->incident),
            ]
            : null;

        $closureIncident = $this->closureIncidentId !== null
            ? Incident::query()->find($this->closureIncidentId)
            : null;

        $closureCandidates = $closureIncident !== null
            ? IncidentOperationalState::closureCandidateDispatches($closureIncident)
            : collect();

        // --- dados do mapa tático ---
        $allActiveIncidents = $openIncidents->merge(
            $kanbanDispatches->flatten()->map(fn (IncidentDispatch $d) => $d->incident)->filter()
        )->unique('id');

        $mapIncidents = $allActiveIncidents
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->map(fn (Incident $i) => [
                'id' => $i->id,
                'lat' => (float) $i->latitude,
                'lng' => (float) $i->longitude,
                'talao' => $i->talao,
                'year' => $i->dispatch_year,
                'nature' => $i->nature?->name ?? '—',
                'status' => $i->status->value,
                'url' => route('operations.incidents.show', $i),
            ])
            ->values();

        try {
            $mapVehicles = TacticalMapVehicleQuery::mapPayload($mid);
        } catch (\Throwable) {
            $mapVehicles = collect();
        }

        return view('livewire.operations.dispatch-board', [
            'municipioOptions' => $municipioOptions,
            'openIncidents' => $openIncidents,
            'availableShifts' => $availableShifts,
            'trackerStatusByVehicleId' => $trackerStatusByVehicleId,
            'vehiclesWithoutShift' => $vehiclesWithoutShift,
            'operationalIdleShifts' => $operationalIdleShifts,
            'modalIncident' => $modalIncident,
            'modalShifts' => $modalShifts,
            'modalShiftDistances' => $modalShiftDistances,
            'modalContactSuggestions' => $modalContactSuggestions,
            'kanbanDispatches' => $kanbanDispatches,
            'dispatchFireMeta' => $dispatchFireMeta,
            'dispatchUnseenNoteCounts' => $dispatchUnseenNoteCounts,
            'orderedStages' => DispatchStage::ordered(),
            'recentTimeline' => $recentTimeline,
            'stats' => $stats,
            'actionIncident' => $actionIncident,
            'shiftChecklistShift' => $shiftChecklistShift,
            'kanbanDispatch' => $kanbanDispatch,
            'kanbanSupportShifts' => $kanbanSupportShifts,
            'kanbanFireMeta' => $kanbanFireMeta,
            'kanbanCancelReasons' => DispatchSceneCancelReason::cases(),
            'closureIncident' => $closureIncident,
            'closureCandidates' => $closureCandidates,
            'mapIncidents' => $mapIncidents,
            'mapVehicles' => $mapVehicles,
        ]);
    }
}
