<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Actions\ConvertOperationalCallAlertAction;
use App\Domain\Operations\Actions\CreateOperationalIncidentAction;
use App\Domain\Operations\Actions\CreateSimpleCallLogAction;
use App\Domain\Operations\Actions\RegisterIncidentCallRequestAction;
use App\Domain\Operations\DTOs\CreateIncidentDTO;
use App\Domain\Operations\DTOs\RegisterIncidentCallRequestDTO;
use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Models\Incident;
use App\Models\Nature;
use App\Models\OperationalCallAlert;
use App\Models\User;
use App\Support\Operations\GeoDistance;
use App\Support\Operations\IncidentPhoneNormalizer;
use App\Support\Operations\NearbyIncidentFinder;
use App\Support\Operations\OpenStreetMapGeocoder;
use App\Support\Operations\OperationalCallAlertGrouper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** Cadastro de ocorrência (equivalente a rotas legadas `ocorrencia/create`). */
#[Layout('layouts.app')]
#[Title('Nova ocorrência')]
final class IncidentCreate extends Component
{
    public string $occurred_at = '';

    /** Só hidratação (webhook → modal): mescla em `occurred_at`, não aparece no formulário. */
    public string $call_received_at = '';

    /** Busca Nominatim para localização no mapa (livre). */
    public string $addressGeocodeQuery = '';

    public ?int $nature_id = null;

    public string $description = '';

    public ?string $address_line = '';

    public ?string $number = '';

    public ?string $district = '';

    public ?string $city = '';

    public ?string $reference_notes = '';

    public ?string $caller_name = '';

    public string $caller_phone = '';

    public ?string $patient_name = '';

    public ?int $patient_age = null;

    public ?string $patient_sex = '';

    /** Preenchido apenas ao clicar num botão de tipo de chamada (persistência). */
    public string $patient_call_type = 'N';

    public ?string $manchester_risk = null;

    public ?int $expected_victim_total = null;

    public bool $is_qta = false;

    public ?int $total_death_count = null;

    public ?string $latitude = '';

    public ?string $longitude = '';

    public string $message = '';

    /** Fluxo PBX/webhook: formulário sem login (URL assinada + sessão até expirar). */
    public bool $guest_intake = false;

    /** Incorporado no modal da Central (dados via Reverb + prefill; operador autenticado). */
    public bool $embeddedInModal = false;

    /** Alerta de chamada convertido em ocorrência (mapa tático). */
    public ?string $intake_alert_id = null;

    /** Identificador da chamada no PABX (Asterisk ${UNIQUEID}) para recuperar a gravação. */
    public ?string $pabx_uniqueid = null;

    /** Aviso de ocorrência já registrada no mesmo ponto (raio configurável). */
    public bool $showDuplicateModal = false;

    /** @var list<int> */
    public array $duplicateCandidateIds = [];

    /** Texto anexado à descrição da ocorrência existente ao somar a solicitação. */
    public string $duplicateNotes = '';

    /**
     * Última coordenada já avaliada (`lat,lng` arredondado).
     *
     * Evita reabrir o aviso a cada re-render depois que o operador o dispensou;
     * mover o ponto no mapa gera uma chave nova e dispara a busca de novo.
     */
    public ?string $checkedCoordinateKey = null;

    public function mount(): void
    {
        $this->occurred_at = now()->format('Y-m-d\TH:i');

        if ($this->embeddedInModal) {
            session()->forget('operations.incident_create_guest');
            $this->guest_intake = false;

            $user = Auth::user();
            abort_unless($user !== null, 403);
            abort_unless(Gate::forUser($user)->allows('viewAny', Incident::class), 403);
            abort_unless($user->hasOperationalAbility('incident.create'), 403);

            $this->hydrateEmbeddedPrefillFromProps();

            return;
        }

        if (request()->hasValidSignature()) {
            $this->hydrateFromSignedQuery(request()->query());
            session()->put('operations.incident_create_guest', [
                'expires_at' => (int) request()->query('expires', 0),
            ]);
            $this->guest_intake = true;

            return;
        }

        if ($this->guestSignedLinkSessionValid()) {
            $this->guest_intake = true;

            return;
        }

        session()->forget('operations.incident_create_guest');

        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless(Gate::forUser($user)->allows('viewAny', Incident::class), 403);
        abort_unless($user->hasOperationalAbility('incident.create'), 403);

        if ($intake = session()->pull('operations.incident_intake')) {
            $this->hydrateFromSessionIntake($intake);
        }
    }

    /** @param  array<string, mixed>  $query */
    private function hydrateFromSignedQuery(array $query): void
    {
        if (isset($query['phone'])) {
            $this->caller_phone = IncidentPhoneNormalizer::normalize((string) $query['phone']);
        }
        if (! empty($query['name'])) {
            $this->caller_name = (string) $query['name'];
        }
        if (isset($query['lat']) && (string) $query['lat'] !== '') {
            $this->latitude = (string) $query['lat'];
        }
        if (isset($query['lng']) && (string) $query['lng'] !== '') {
            $this->longitude = (string) $query['lng'];
        }
        if (! empty($query['received_at'])) {
            try {
                $this->occurred_at = CarbonImmutable::parse((string) $query['received_at'])->format('Y-m-d\TH:i');
            } catch (Throwable) {
                //
            }
        }
        if (! empty($query['ref'])) {
            $this->reference_notes = (string) $query['ref'];
        }
        if (! empty($query['uniqueid'])) {
            $this->pabx_uniqueid = trim((string) $query['uniqueid']);
        }

        $this->normalizeCoordinateProps();
        $this->enrichAddressFromCoordinatesIfNeeded();
        $this->dispatchIncidentOsmInvalidateDelayed();
    }

    /** @param  array<string, mixed>  $intake */
    private function hydrateFromSessionIntake(array $intake): void
    {
        if (! empty($intake['caller_phone'])) {
            $this->caller_phone = IncidentPhoneNormalizer::normalize((string) $intake['caller_phone']);
        }
    }

    private function hydrateEmbeddedPrefillFromProps(): void
    {
        /** @var array<string, mixed>|null $snap */
        $snap = session()->get('operations.call_intake_modal_prefill');
        if (is_array($snap)) {
            if ($this->caller_phone === '' && (($snap['phone'] ?? '') !== '')) {
                $this->caller_phone = IncidentPhoneNormalizer::normalize((string) $snap['phone']);
            }
            $cn = $snap['caller_name'] ?? null;
            if (($this->caller_name === null || trim((string) $this->caller_name) === '') && is_string($cn) && trim($cn) !== '') {
                $this->caller_name = trim($cn);
            }
            if (($this->latitude === null || $this->latitude === '') && (($snap['latitude'] ?? '') !== '' && $snap['latitude'] !== null)) {
                $this->latitude = is_string($snap['latitude']) ? trim($snap['latitude']) : (string) $snap['latitude'];
            }
            if (($this->longitude === null || $this->longitude === '') && (($snap['longitude'] ?? '') !== '' && $snap['longitude'] !== null)) {
                $this->longitude = is_string($snap['longitude']) ? trim($snap['longitude']) : (string) $snap['longitude'];
            }
            $ref = $snap['external_reference'] ?? null;
            if (($this->reference_notes === null || trim((string) $this->reference_notes) === '') && is_string($ref) && trim($ref) !== '') {
                $this->reference_notes = trim($ref);
            }
            $cra = $snap['call_received_at'] ?? null;
            if ($this->call_received_at === '' && is_string($cra) && trim($cra) !== '') {
                $this->call_received_at = trim($cra);
            }
            if ($this->intake_alert_id === null || trim($this->intake_alert_id) === '') {
                $aid = $snap['alert_id'] ?? null;
                if (is_string($aid) && trim($aid) !== '') {
                    $this->intake_alert_id = trim($aid);
                }
            }
            if ($this->pabx_uniqueid === null || trim((string) $this->pabx_uniqueid) === '') {
                $uid = $snap['uniqueid'] ?? null;
                if (is_string($uid) && trim($uid) !== '') {
                    $this->pabx_uniqueid = trim($uid);
                }
            }
        }

        session()->forget('operations.call_intake_modal_prefill');

        $this->caller_phone = IncidentPhoneNormalizer::normalize($this->caller_phone);

        if ($this->call_received_at !== '') {
            try {
                $this->occurred_at = CarbonImmutable::parse((string) $this->call_received_at)->format('Y-m-d\TH:i');
            } catch (Throwable) {
                //
            }
            $this->call_received_at = '';
        }

        $this->normalizeCoordinateProps();
        $this->enrichAddressFromCoordinatesIfNeeded();
        $this->dispatchIncidentOsmInvalidateDelayed();
    }

    private function normalizeCoordinateProps(): void
    {
        foreach (['latitude', 'longitude'] as $prop) {
            $raw = $this->{$prop};
            if ($raw === null || $raw === '') {
                $this->{$prop} = '';

                continue;
            }
            $f = filter_var($raw, FILTER_VALIDATE_FLOAT);
            $this->{$prop} = $f !== false ? (string) round((float) $f, 7) : '';
        }
    }

    /** Quando o PBX envia só lat/lng, preenche logradouro/bairro/cidade via Nominatim (fora dos testes automatizados). */
    private function enrichAddressFromCoordinatesIfNeeded(): void
    {
        if (App::runningUnitTests()) {
            return;
        }

        $lat = filter_var($this->latitude, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($this->longitude, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false) {
            return;
        }

        if (trim((string) ($this->address_line ?? '')) !== '') {
            return;
        }

        try {
            $hit = OpenStreetMapGeocoder::reverseLookup((float) $lat, (float) $lng);
        } catch (Throwable) {
            return;
        }

        $line = $hit['street_line'];
        $this->address_line = $line ?? mb_substr($hit['display_name'], 0, 255);

        if (trim((string) ($this->district ?? '')) === '' && ($hit['district'] ?? null) !== null && $hit['district'] !== '') {
            $this->district = $hit['district'];
        }
        if (trim((string) ($this->city ?? '')) === '' && ($hit['city'] ?? null) !== null && $hit['city'] !== '') {
            $this->city = $hit['city'];
        }

        if (trim((string) ($this->addressGeocodeQuery ?? '')) === '') {
            $this->addressGeocodeQuery = mb_substr($hit['display_name'], 0, 400);
        }
    }

    private function dispatchIncidentOsmInvalidateDelayed(): void
    {
        $lat = filter_var($this->latitude, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($this->longitude, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false) {
            return;
        }

        $this->js('setTimeout(() => window.dispatchEvent(new CustomEvent("incident-osm-invalidate")), 120)');
    }

    public function geocodeAddressSearch(): void
    {
        $this->resetErrorBag('addressGeocodeQuery');

        $this->validate([
            'addressGeocodeQuery' => ['required', 'string', 'max:400'],
        ], [], [
            'addressGeocodeQuery' => __('Busca de endereço'),
        ]);

        try {
            $hit = OpenStreetMapGeocoder::firstHit($this->addressGeocodeQuery);
        } catch (Throwable) {
            $this->addError('addressGeocodeQuery', __('Não foi possível localizar o endereço. Refine a busca (rua, bairro, cidade).'));

            return;
        }

        $line = $hit['street_line'];
        $this->address_line = $line ?? mb_substr($hit['display_name'], 0, 255);
        $this->district = $hit['district'];
        $this->city = $hit['city'];
        $this->latitude = (string) round($hit['lat'], 7);
        $this->longitude = (string) round($hit['lon'], 7);

        // Atribuição direta não passa pelos hooks `updated*`.
        $this->evaluateNearbyIncidents();

        $this->js('window.dispatchEvent(new CustomEvent("incident-osm-invalidate"))');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes)
    {
        // Normaliza telefone; nature_id já chega como ?int via cast do Livewire 3
        $callerPhone = IncidentPhoneNormalizer::normalize((string) ($attributes['caller_phone'] ?? ''));

        $raw = $attributes['nature_id'] ?? null;
        $natureId = ($raw !== '' && $raw !== null) ? (int) $raw : null;

        return array_merge($attributes, [
            'caller_phone' => $callerPhone,
            'nature_id' => $natureId,
        ]);
    }

    /** Persistência disparada pelos botões de tipo de chamada. */
    public function saveWithCallType(
        string $code,
        CreateOperationalIncidentAction $operationalAction,
        CreateSimpleCallLogAction $simpleAction,
    ): void {
        $this->patient_call_type = $code;
        $this->finalizeIncident($operationalAction, $simpleAction);
    }

    private function finalizeIncident(
        CreateOperationalIncidentAction $operationalAction,
        CreateSimpleCallLogAction $simpleAction,
    ): void {
        $this->resetErrorBag();

        if ($this->guest_intake && ! $this->guestSignedLinkSessionValid()) {
            $this->addError('scope', __('O tempo deste formulário expirou. Peça um novo link pela central ou entre no sistema.'));

            return;
        }

        if ($this->latitude === '') {
            $this->latitude = null;
        }
        if ($this->longitude === '') {
            $this->longitude = null;
        }

        if (! $this->guestSignedLinkSessionValid()) {
            if (! Gate::allows('createOperational')) {
                $this->addError('scope', __('Sem permissão para registrar ocorrência.'));

                return;
            }
        }

        $callTypeEnum = CallType::tryFrom($this->patient_call_type) ?? CallType::Normal;
        $simple = $callTypeEnum->isSimple();

        $validated = $this->validate([
            'occurred_at' => $simple ? ['nullable', 'date'] : ['required', 'date'],
            'nature_id' => $simple
                                        ? ['nullable', 'integer', Rule::exists('natures', 'id')]
                                        : ['required', 'integer', Rule::exists('natures', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'address_line' => $simple ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:64'],
            'district' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'string', 'max:128'],
            'reference_notes' => ['nullable', 'string', 'max:2000'],
            'caller_name' => ['nullable', 'string', 'max:255'],
            'caller_phone' => ['required', 'string', 'min:8', 'max:64'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'patient_sex' => ['nullable', 'string', 'max:16'],
            'patient_call_type' => ['required', Rule::enum(CallType::class)],
            'manchester_risk' => ['nullable', Rule::enum(ManchesterRisk::class)],
            'expected_victim_total' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_qta' => ['boolean'],
            'total_death_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $occurred = $simple || blank($validated['occurred_at'])
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($validated['occurred_at']);

        $enumCallType = CallType::from($validated['patient_call_type']);
        $enumManchester = isset($validated['manchester_risk']) && $validated['manchester_risk'] !== null && $validated['manchester_risk'] !== ''
            ? ManchesterRisk::from($validated['manchester_risk'])
            : null;

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        $dto = new CreateIncidentDTO(
            municipioId: null,
            natureId: $validated['nature_id'] ?? null,
            description: $validated['description'] ?? '',
            addressLine: $validated['address_line'] ?: null,
            number: $validated['number'] ?: null,
            district: $validated['district'] ?: null,
            city: $validated['city'] ?: null,
            callerName: $validated['caller_name'] ?: null,
            callerPhone: $validated['caller_phone'] ?: null,
            patientAge: $validated['patient_age'],
            patientSex: $validated['patient_sex'] ?: null,
            latitude: $latitude,
            longitude: $longitude,
            referenceNotes: $validated['reference_notes'] ?: null,
            callType: $enumCallType,
            expectedVictimTotal: $validated['expected_victim_total'],
            createdByUserId: Auth::id(),
            patientName: $validated['patient_name'] ?: null,
            protectedAreaId: null,
            isQta: $validated['is_qta'],
            totalDeathCount: $validated['total_death_count'],
            occurredAt: $occurred,
            callReceivedAt: $occurred,
            manchesterRisk: $enumManchester,
            pabxUniqueid: ($this->pabx_uniqueid !== null && trim($this->pabx_uniqueid) !== '') ? trim($this->pabx_uniqueid) : null,
        );

        $action = $simple ? $simpleAction : $operationalAction;

        try {
            $incident = $action->execute($dto);
        } catch (QueryException $e) {
            report($e);
            $this->addError('save', __('Não foi possível salvar (dados duplicados ou violação no banco). Verifique o talão ou tente novamente.'));

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível salvar a ocorrência. Tente novamente ou contate o suporte.'));

            return;
        }

        if ($this->embeddedInModal) {
            $this->consumeIntakeAlert($incident);

            $this->dispatch('call-intake-incident-saved', incidentId: $incident->id);

            return;
        }

        // Chamadas simples: redireciona para a aba de registradas, sem detalhe operacional
        if ($simple) {
            $this->redirect(route('operations.incidents.index', ['f' => 'logged']), navigate: true);

            return;
        }

        if ($this->guestSignedLinkSessionValid()) {
            session()->forget('operations.incident_create_guest');
            $this->guest_intake = false;
            session()->flash('registered_incident', [
                'talao' => $incident->talao,
                'dispatch_year' => $incident->dispatch_year,
            ]);
            $this->redirect(route('operations.incidents.registered-guest'), navigate: true);

            return;
        }

        $this->redirect(route('operations.incidents.show', $incident), navigate: true);
    }

    /**
     * O aviso nasce da localização, então roda quando o ponto muda — não no salvamento.
     *
     * O mapa grava latitude e longitude em props separados, então cada eixo dispara sua
     * avaliação. A checagem por par de coordenadas ({@see self::$checkedCoordinateKey})
     * mantém o resultado final coerente: o último eixo aplicado reavalia com o par completo.
     */
    public function updatedLatitude(): void
    {
        $this->evaluateNearbyIncidents();
    }

    public function updatedLongitude(): void
    {
        $this->evaluateNearbyIncidents();
    }

    /**
     * O formulário público (link assinado do PBX) não avisa: quem preenche é o solicitante,
     * sem contexto para decidir se é o mesmo evento — a triagem fica com o operador.
     */
    private function evaluateNearbyIncidents(): void
    {
        if ($this->guest_intake) {
            return;
        }

        $latitude = filter_var($this->latitude, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($this->longitude, FILTER_VALIDATE_FLOAT);

        if ($latitude === false || $longitude === false) {
            $this->checkedCoordinateKey = null;

            return;
        }

        $key = round((float) $latitude, 5).','.round((float) $longitude, 5);
        if ($key === $this->checkedCoordinateKey) {
            return;
        }

        $this->checkedCoordinateKey = $key;

        $candidates = NearbyIncidentFinder::find((float) $latitude, (float) $longitude, Auth::user());

        if ($candidates->isEmpty()) {
            $this->duplicateCandidateIds = [];
            $this->showDuplicateModal = false;

            return;
        }

        $this->duplicateCandidateIds = $candidates->pluck('id')->map(intval(...))->all();
        $this->duplicateNotes = trim($this->description);
        $this->showDuplicateModal = true;
    }

    /** Operador reconheceu a ocorrência existente: a ligação vira solicitação daquele ponto. */
    public function attachRequestToIncident(int $incidentId, RegisterIncidentCallRequestAction $action): void
    {
        $this->resetErrorBag();

        if (! in_array($incidentId, $this->duplicateCandidateIds, strict: true)) {
            $this->addError('duplicate', __('Ocorrência inválida para vincular a solicitação.'));

            return;
        }

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            $this->addError('duplicate', __('A ocorrência selecionada não está mais disponível.'));

            return;
        }

        Gate::authorize('addObservation', $incident);

        // O aviso abre já na definição do endereço, quando o telefone pode ainda não ter sido
        // digitado — mas uma solicitação sem telefone não identifica quem ligou.
        $this->validate(
            [
                'caller_phone' => ['required', 'string', 'min:8', 'max:64'],
                'duplicateNotes' => ['nullable', 'string', 'max:2000'],
            ],
            ['caller_phone.required' => __('Informe o telefone do solicitante para somar a solicitação.')],
            ['duplicateNotes' => __('Descrição adicional')],
        );

        /** @var User $user */
        $user = Auth::user();

        $latitude = filter_var($this->latitude, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($this->longitude, FILTER_VALIDATE_FLOAT);
        $latitude = $latitude === false ? null : (float) $latitude;
        $longitude = $longitude === false ? null : (float) $longitude;

        $distanceMeters = null;
        if ($latitude !== null && $longitude !== null && $incident->latitude !== null && $incident->longitude !== null) {
            $distanceMeters = (int) round(GeoDistance::haversineMeters(
                $latitude,
                $longitude,
                (float) $incident->latitude,
                (float) $incident->longitude,
            ));
        }

        $notes = trim($this->duplicateNotes);

        try {
            $action->execute($incident, new RegisterIncidentCallRequestDTO(
                callerName: ($this->caller_name !== null && trim($this->caller_name) !== '') ? trim($this->caller_name) : null,
                callerPhone: $this->caller_phone !== '' ? $this->caller_phone : null,
                notes: $notes !== '' ? $notes : null,
                latitude: $latitude,
                longitude: $longitude,
                distanceMeters: $distanceMeters,
                pabxUniqueid: ($this->pabx_uniqueid !== null && trim($this->pabx_uniqueid) !== '') ? trim($this->pabx_uniqueid) : null,
            ), $user);
        } catch (Throwable $e) {
            report($e);
            $this->addError('duplicate', __('Não foi possível registrar a solicitação. Tente novamente ou contate o suporte.'));

            return;
        }

        $this->showDuplicateModal = false;
        $this->duplicateCandidateIds = [];
        $this->duplicateNotes = '';

        $this->finishAfterAttach($incident);
    }

    /**
     * Encerra o fluxo como se a chamada tivesse virado ocorrência, porém apontando
     * para a existente: consome o alerta do mapa tático e devolve o operador ao board.
     */
    private function finishAfterAttach(Incident $incident): void
    {
        $this->consumeIntakeAlert($incident);

        if ($this->embeddedInModal) {
            $this->dispatch('call-intake-request-attached', incidentId: $incident->id);

            return;
        }

        session()->flash('status', __('Solicitação somada ao talão :talao/:ano.', [
            'talao' => $incident->talao,
            'ano' => $incident->dispatch_year,
        ]));

        $this->redirect(route('operations.incidents.show', $incident), navigate: true);
    }

    /** Converte o alerta de chamada (mapa tático) na ocorrência informada e limpa o cluster. */
    private function consumeIntakeAlert(Incident $incident): void
    {
        if ($this->intake_alert_id === null || trim($this->intake_alert_id) === '') {
            return;
        }

        $alertId = trim($this->intake_alert_id);
        $alert = OperationalCallAlert::query()->find($alertId);

        app(ConvertOperationalCallAlertAction::class)->execute($alertId, $incident);

        if ($alert === null) {
            return;
        }

        $this->js('window.__samuUpdateAlertCluster?.('.json_encode([
            'location_key' => OperationalCallAlertGrouper::locationKey(
                (float) $alert->latitude,
                (float) $alert->longitude,
            ),
            'remove' => true,
        ], JSON_THROW_ON_ERROR).')');
    }

    public function dismissDuplicateModal(): void
    {
        $this->showDuplicateModal = false;
        $this->duplicateCandidateIds = [];
        $this->duplicateNotes = '';
        $this->resetErrorBag('duplicate');
    }

    public function render(): View
    {
        return view('livewire.operations.incident-create', [
            'natures' => Nature::query()->orderBy('name')->get(),
            'callTypesForButtons' => CallType::orderedForIncidentForm(),
            'duplicateCandidates' => $this->duplicateCandidates(),
        ]);
    }

    /**
     * Candidatas exibidas no aviso, com viaturas empenhadas e última posição conhecida
     * para o operador julgar se é o mesmo evento.
     *
     * @return Collection<int, Incident>
     */
    private function duplicateCandidates(): Collection
    {
        if ($this->duplicateCandidateIds === []) {
            return collect();
        }

        $latitude = filter_var($this->latitude, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($this->longitude, FILTER_VALIDATE_FLOAT);

        $incidents = Incident::query()
            ->with(['nature', 'municipio', 'dispatches' => fn ($q) => $q->whereNull('deleted_at')->with('shift.vehicle.position')])
            ->withCount('callRequests')
            ->whereIn('id', $this->duplicateCandidateIds)
            ->get();

        if ($latitude === false || $longitude === false) {
            return $incidents;
        }

        return $incidents
            ->map(function (Incident $incident) use ($latitude, $longitude): Incident {
                if ($incident->latitude === null || $incident->longitude === null) {
                    return $incident;
                }

                $incident->distance_meters = (int) round(GeoDistance::haversineMeters(
                    (float) $latitude,
                    (float) $longitude,
                    (float) $incident->latitude,
                    (float) $incident->longitude,
                ));

                return $incident;
            })
            ->sortBy(static fn (Incident $incident): int => $incident->distance_meters ?? PHP_INT_MAX)
            ->values();
    }

    private function guestSignedLinkSessionValid(): bool
    {
        $guest = session()->get('operations.incident_create_guest');

        return is_array($guest)
            && isset($guest['expires_at'])
            && (int) $guest['expires_at'] >= now()->timestamp;
    }
}
