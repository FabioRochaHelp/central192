<div class="rpt">
    <header class="rpt-header">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="top" width="65%">
                    <h1 class="rpt-header-title">{{ $document->title() }}</h1>
                    <p class="rpt-header-meta">{{ $document->talaoLabel() }}</p>
                    <p class="rpt-header-meta">{{ $document->addressLine() }}</p>
                    <span class="rpt-header-badge">{{ $document->modality()->label() }}</span>
                </td>
                <td valign="top" width="35%">
                    <p class="rpt-header-org">{{ $document->incident->municipio?->razao_social ?? config('app.name') }}</p>
                    <p class="rpt-header-date">{{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }}</p>
                </td>
            </tr>
        </table>
    </header>

    <div class="rpt-outcome">
        <span class="rpt-outcome-label">{{ __('Desfecho da ocorrência') }}</span>
        <span class="rpt-outcome-value">{{ $document->outcomeLabel() }}</span>
    </div>

    <section class="rpt-section">
        <h2 class="rpt-section-head">{{ __('Resumo da ocorrência') }}</h2>
        @include('operations.documents.partials.incident-final-report-fields-table', ['fields' => $document->incidentSummary()])
    </section>

    @if ($document->hasCallAlertData())
        <section class="rpt-section">
            <h2 class="rpt-section-head">{{ __('Alerta operacional — comportamento do fogo (sensor)') }}</h2>
            @include('operations.documents.partials.incident-final-report-fields-table', ['fields' => $document->callAlertFields()])
            @if ($document->callAlertObservationTexts() !== [])
                <div class="rpt-alert-box">
                    <p class="rpt-alert-box-title">{{ __('Observações do monitoramento') }}</p>
                    <ul class="rpt-alert-list">
                        @foreach ($document->callAlertObservationTexts() as $observation)
                            <li>{{ $observation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    @if ($document->hasDispatchContactAttempts())
        <section class="rpt-section">
            <h2 class="rpt-section-head">{{ __('Contato pré-despacho') }}</h2>
            @foreach ($document->dispatchContactAttempts() as $contact)
                <div class="rpt-unit-card">
                    <div class="rpt-unit-head">{{ $contact['result'] }} · {{ $contact['recorded_at'] }}</div>
                    <div class="rpt-unit-body">
                        <p class="rpt-unit-row">
                            <strong>{{ __('Viatura acionada') }}:</strong> {{ $contact['vehicle'] }}
                            @if ($contact['vehicle_base'] !== '—')
                                <span style="color: #6b7280; font-size: 0.82em;">({{ $contact['vehicle_base'] }})</span>
                            @endif
                        </p>
                        <p class="rpt-unit-row"><strong>{{ __('Método de contato') }}:</strong> {{ $contact['method'] }}</p>
                        <p class="rpt-unit-row"><strong>{{ __('Número / ramal / link') }}:</strong> {{ $contact['details'] }}</p>
                        @unless ($contact['successful'])
                            <p class="rpt-unit-row"><strong>{{ __('Motivo') }}:</strong> {{ $contact['reason'] }}</p>
                        @endunless
                        <p class="rpt-unit-row"><strong>{{ __('Registrado por') }}:</strong> {{ $contact['actor'] }}</p>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="rpt-section">
        <h2 class="rpt-section-head">{{ __('Viatura, turno e efetivo') }}</h2>
        @forelse ($document->dispatchUnits() as $unit)
            <div class="rpt-unit-card">
                <div class="rpt-unit-head">{{ $unit['vehicle'] }}</div>
                <div class="rpt-unit-body">
                    <p class="rpt-unit-row"><strong>{{ __('Turno') }}:</strong> {{ $unit['shift_period'] }}</p>
                    <p class="rpt-unit-row"><strong>{{ __('Etapa final') }}:</strong> {{ $unit['stage'] }}</p>
                    <p class="rpt-unit-row"><strong>{{ __('Efetivo') }}:</strong></p>
                    @if ($unit['staff'] === [])
                        <p class="rpt-empty" style="margin: 4px 0 0;">—</p>
                    @else
                        <ul class="rpt-staff-list">
                            @foreach ($unit['staff'] as $member)
                                <li>{{ $member }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @empty
            <p class="rpt-empty">{{ __('Nenhum despacho registrado.') }}</p>
        @endforelse
    </section>

    <section class="rpt-section">
        <h2 class="rpt-section-head">{{ __('Dados gerais do relatório') }}</h2>
        @include('operations.documents.partials.incident-final-report-fields-table', ['fields' => $document->baseFields()])
    </section>

    <section class="rpt-section">
        <h2 class="rpt-section-head">{{ $document->modality()->label() }}</h2>
        @include('operations.documents.partials.incident-final-report-fields-table', ['fields' => $document->specificFields()])
    </section>

    @if ($document->hasFireScars())
        <section class="rpt-section">
            <h2 class="rpt-section-head">{{ __('Cicatriz de incêndio (área queimada)') }}</h2>
            @foreach ($document->fireScars() as $scarFields)
                @include('operations.documents.partials.incident-final-report-fields-table', ['fields' => $scarFields])
            @endforeach
        </section>
    @endif

    @if ($document->incident->description)
        <section class="rpt-section">
            <h2 class="rpt-section-head">{{ __('Descrição da ocorrência') }}</h2>
            <div class="rpt-description">{{ $document->incident->description }}</div>
        </section>
    @endif

    <p class="rpt-footer">
        {{ __('Documento gerado automaticamente pelo sistema operacional.') }}
        · {{ $document->talaoLabel() }}
    </p>
</div>
