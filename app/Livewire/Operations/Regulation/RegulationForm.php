<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Regulation;

use App\Domain\Operations\Actions\AssumeRegulationAction;
use App\Domain\Operations\Actions\RegisterRegulationDecisionAction;
use App\Domain\Operations\DTOs\RegulationDecisionDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * Formulário de regulação médica — classificação, hipótese e decisão do médico regulador.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
#[Layout('layouts.app')]
#[Title('Regulação médica')]
final class RegulationForm extends Component
{
    public Incident $incident;

    public string $priority = '';

    public string $diagnostic_hypothesis = '';

    public string $decision = '';

    public string $recommended_resource = '';

    public string $guidance_notes = '';

    public string $refusal_reason = '';

    public string $transfer_target = '';

    public function mount(Incident $incident, AssumeRegulationAction $assume): void
    {
        Gate::authorize('regulate', $incident);

        /** @var User $user */
        $user = Auth::user();

        // Deep-link direto na fila: garante que o médico assuma antes de decidir.
        if ($incident->status === IncidentStatus::PendingRegulation) {
            try {
                $assume->execute($incident->id, $user);
            } catch (RuntimeException $e) {
                abort(409, $e->getMessage());
            }
        }

        $this->incident = $incident->fresh()->load(['nature', 'municipio', 'regulation', 'callRequests']);

        $regulation = $this->incident->regulation;
        $this->priority = $regulation?->priority?->value
            ?? $this->incident->manchester_risk?->value
            ?? '';
        $this->diagnostic_hypothesis = (string) ($regulation?->diagnostic_hypothesis ?? '');
    }

    public function save(RegisterRegulationDecisionAction $action): void
    {
        Gate::authorize('regulate', $this->incident);

        $validated = $this->validate($this->rules(), [], [
            'decision' => __('Decisão'),
            'priority' => __('Prioridade'),
            'recommended_resource' => __('Recurso indicado'),
            'guidance_notes' => __('Orientações'),
            'refusal_reason' => __('Motivo da recusa'),
            'transfer_target' => __('Destino da transferência'),
        ]);

        $decision = RegulationDecision::from($validated['decision']);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $action->execute(new RegulationDecisionDTO(
                incidentId: $this->incident->id,
                regulatorUserId: $user->id,
                decision: $decision,
                priority: $validated['priority'] !== '' ? ManchesterRisk::from($validated['priority']) : null,
                diagnosticHypothesis: $validated['diagnostic_hypothesis'] !== '' ? $validated['diagnostic_hypothesis'] : null,
                recommendedResource: $decision->authorizesDispatch() && $validated['recommended_resource'] !== ''
                    ? RegulationResource::from($validated['recommended_resource'])
                    : null,
                guidanceNotes: $validated['guidance_notes'] !== '' ? $validated['guidance_notes'] : null,
                refusalReason: $validated['refusal_reason'] !== '' ? $validated['refusal_reason'] : null,
                transferTarget: $validated['transfer_target'] !== '' ? $validated['transfer_target'] : null,
            ));
        } catch (RuntimeException $e) {
            $this->addError('save', $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível registrar a regulação.'));

            return;
        }

        $this->redirect(route('operations.incidents.show', $this->incident), navigate: true);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $isDispatch = $this->decision === RegulationDecision::DispatchResource->value;
        $isGuidance = $this->decision === RegulationDecision::MedicalGuidance->value;
        $isTransfer = $this->decision === RegulationDecision::Transfer->value;
        $isRefused = $this->decision === RegulationDecision::Refused->value;

        return [
            'decision' => ['required', Rule::in(array_map(fn ($c) => $c->value, RegulationDecision::cases()))],
            'priority' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, ManchesterRisk::cases()))],
            'diagnostic_hypothesis' => ['nullable', 'string', 'max:5000'],
            'recommended_resource' => [
                $isDispatch ? 'required' : 'nullable',
                Rule::in(array_map(fn ($c) => $c->value, RegulationResource::dispatchable())),
            ],
            'guidance_notes' => [$isGuidance ? 'required' : 'nullable', 'string', 'max:5000'],
            'refusal_reason' => [$isRefused ? 'required' : 'nullable', 'string', 'max:5000'],
            'transfer_target' => [$isTransfer ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function render(): View
    {
        return view('livewire.operations.regulation.regulation-form', [
            'priorities' => ManchesterRisk::cases(),
            'decisions' => RegulationDecision::cases(),
            'resources' => RegulationResource::dispatchable(),
        ]);
    }
}
