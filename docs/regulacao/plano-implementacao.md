# Módulo de Regulação Médica — Plano de Implementação

> Central 192 — Laravel 13 / Livewire 4 (Flux) / stancl-tenancy / Reverb / dompdf.
> Documento de arquitetura e planejamento. Sem código de produção ainda.

## 1. Contexto e objetivo

Hoje o fluxo operacional é:

```
Atendente cria ocorrência (status = OPEN)
        │
        ▼
Fila da Central (DispatchBoard) — status OPEN
        │
        ▼
Despachador empenha viatura (DispatchUnitAction → status DISPATCHED)
```

O objetivo é inserir a **regulação médica** como etapa obrigatória **entre o cadastro da
solicitação e o empenho da viatura**, alinhada ao modelo SAMU 192:

1. **TARM / Atendente** recebe a ligação e cadastra a solicitação (dados da vítima, local, queixa).
2. A ocorrência entra na **fila de regulação médica** (nova tela) — ainda **não** é empenhável.
3. O **Médico Regulador** assume a ocorrência, avalia, classifica gravidade/prioridade e registra a
   **decisão de regulação** (conduta): enviar recurso, orientação médica, transferência ou recusa.
4. Só quando o médico **autoriza envio de recurso** a ocorrência cai na fila de despacho (status OPEN)
   para o despachador empenhar a viatura.
5. Todo o registro do médico (hipótese diagnóstica, prioridade, recurso indicado, orientações,
   médico responsável, tempos) fica anexado à ocorrência e disponível em relatório.

### Terminologia SAMU adotada
- **TARM**: Técnico Auxiliar de Regulação Médica (o "atendente" atual).
- **Médico Regulador**: decide a grade de resposta.
- **Grade de resposta / recurso**: USB (Suporte Básico), USA (Suporte Avançado, com médico), VIR,
  Motolância, Aeromédico, ou "sem recurso" (orientação).
- **Decisão / conduta de regulação**: enviar recurso | orientação médica | transferência | recusa.

## 2. Decisão arquitetural central: novo status no ciclo de vida

O ponto de menor atrito é inserir **um novo status antes de OPEN**, mantendo intacta a
`DispatchBoard` (que já filtra `status = OPEN` para montar a fila de despacho).

### `App\Domain\Operations\Enums\IncidentStatus` — novos casos

| case                 | value                   | label                                | Empenhável? |
|----------------------|-------------------------|--------------------------------------|-------------|
| `PendingRegulation`  | `pending_regulation`    | Aguardando regulação                 | não         |
| `InRegulation`       | `in_regulation`         | Em regulação (médico assumiu)        | não         |
| `Open` (existente)   | `open`                  | Aberta (regulada, aguardando viatura)| **sim**     |
| `RegulationDenied`   | `regulation_denied`     | Encerrada na regulação (orientação/recusa) | não   |

Fluxo de status:

```
                     ┌─────────────────────────── (natureza sem regulação: vai direto p/ OPEN)
                     │
cria ocorrência ─► PENDING_REGULATION ─► IN_REGULATION ─┬─► OPEN ─► DISPATCHED ─► ... (fluxo atual)
                    (fila regulação)     (médico assume) │
                                                         ├─► REGULATION_DENIED (orientação / recusa / transferência)
                                                         └─► CANCELLED
```

> **Compatibilidade**: nenhuma query existente que assume `OPEN = pronto p/ despacho` quebra —
> a ocorrência só chega a `OPEN` depois de regulada. `CreateOperationalIncidentAction` passa a
> nascer em `PENDING_REGULATION` (ver §7, ponto de decisão sobre exigir regulação por natureza).

## 3. Ponto de decisão: regulação é obrigatória para todas as ocorrências?

O sistema também atende Bombeiros/Incêndio/Salvamento (modalidades de relatório distintas em
`Nature.report_modality`). Regulação médica só faz sentido para naturezas de saúde (SAMU).

**Recomendação**: adicionar flag `requires_medical_regulation` (boolean) na tabela `natures`.
- Natureza com flag = ocorrência nasce em `PENDING_REGULATION`.
- Natureza sem flag (incêndio, salvamento) = nasce em `OPEN` (fluxo atual, sem mudança).

Isso mantém o módulo cirúrgico e não afeta o fluxo dos Bombeiros.

## 4. Modelo de dados (migrations)

### 4.1 `natures` — flag de regulação
```
add_column: requires_medical_regulation BOOLEAN NOT NULL DEFAULT false
```

### 4.2 Nova tabela `incident_regulations`
Um registro por ocorrência (HasOne). Guarda a decisão do médico.

| coluna                    | tipo            | notas |
|---------------------------|-----------------|-------|
| id                        | bigint pk       | |
| municipio_id              | fk nullable     | `BelongsToMunicipio` (escopo multi-base) |
| incident_id               | fk unique       | HasOne |
| regulator_user_id         | fk users        | médico que regulou |
| status                    | string (enum)   | `RegulationDecision`: dispatch_resource / medical_guidance / transfer / refused |
| priority                  | string (enum)   | reaproveita `ManchesterRisk` (red…blue) ou nova `RegulationPriority` |
| diagnostic_hypothesis     | text nullable   | hipótese diagnóstica |
| recommended_resource      | string (enum)   | `RegulationResource`: usb / usa / vir / moto / aero / none |
| guidance_notes            | text nullable   | orientações ao paciente / conduta |
| refusal_reason            | text nullable   | quando recusa/transferência |
| transfer_target           | string nullable | serviço/UPA/hospital destino da transferência |
| assumed_at                | datetime        | início da regulação (médico assumiu) |
| decided_at                | datetime        | fim da regulação (decisão registrada) |
| response_time_seconds     | int nullable    | `decided_at - incident.call_received_at` (indicador) |
| created_at / updated_at   | timestamps      | |

Índices: `incident_id` unique, `regulator_user_id`, `status`, `(municipio_id, decided_at)`.

### 4.3 Timeline / eventos
Sem tabela nova — usar `IncidentTimelineRecorder->record()` com novos `event_key`:
`regulation_queued`, `regulation_assumed`, `regulation_decided`, `regulation_denied`.

## 5. Enums novos (`app/Domain/Operations/Enums`)

- **`RegulationDecision`**: `DispatchResource`, `MedicalGuidance`, `Transfer`, `Refused` (+ `label()`).
- **`RegulationResource`**: `Usb`, `Usa`, `Vir`, `Moto`, `Aero`, `None` (+ `label()`).
- **`RegulationPriority`** *(opcional)*: se não reaproveitar `ManchesterRisk`. Recomendação:
  **reaproveitar `ManchesterRisk`** (já tem cores Flux e ordenação de criticidade).
- Ampliar **`IncidentStatus`** com os 3 casos novos (§2) + `label()` + helper `isRegulable()`.

## 6. Domain — Actions + DTOs (padrão já usado na base)

Todas em `DB::transaction`, registrando timeline e disparando evento de broadcast.

| Action                          | DTO                          | Efeito |
|---------------------------------|------------------------------|--------|
| `AssumeRegulationAction`        | incidentId, regulatorUserId  | `PENDING_REGULATION → IN_REGULATION`, grava `assumed_at`, trava para outros médicos |
| `RegisterRegulationDecisionAction` | `RegulationDecisionDTO`   | cria/atualiza `IncidentRegulation`, calcula `response_time`, decide próximo status |
| `ReleaseRegulationAction` *(opc)* | incidentId                 | médico devolve à fila (`IN_REGULATION → PENDING_REGULATION`) |

`RegisterRegulationDecisionAction` roteia o status conforme a decisão:
- `DispatchResource` → `OPEN` (entra na fila de despacho; grava `recommended_resource`).
- `MedicalGuidance` / `Refused` / `Transfer` → `REGULATION_DENIED` (encerra sem viatura).

Evento novo: `RegulationDecided` (broadcast em `operations.dispatch` para atualizar filas em tempo real
via Reverb, seguindo o padrão de `DispatchStageAdvanced`/`UnitDispatched`).

## 7. Permissões (abilities)

`UserLegacyProfile::Doctor` (case 4) já existe. Estender `abilities()`:

```php
self::Doctor => [
    ...self::municipalOperationalBase(),
    'victim.prescribe',
    'victim.prescription.approve',
    'regulation.view',      // ver fila de regulação
    'regulation.regulate',  // assumir e decidir
],
```

- `CentralAdministrator` / `CentralOperator` já têm `*` (acesso total).
- Novo `Gate`/`Policy` `regulate` em `IncidentPolicy` checando `regulation.regulate` + escopo de município.
- Atendente/Despachador **não** regulam (só veem o status).

## 8. Telas (Livewire) e formulários

Namespace `App\Livewire\Operations\Regulation`, views em `resources/views/livewire/operations/regulation/`.
Rotas em `routes/web.php` sob o grupo `operations` existente. Item novo no
`resources/views/layouts/app/sidebar.blade.php` visível quando `hasOperationalAbility('regulation.view')`.

### 8.1 Fila de Regulação — `RegulationQueue` (tela principal do médico)
Rota: `GET /operations/regulation` → `operations.regulation.index`.
- Lista ocorrências `PENDING_REGULATION` + `IN_REGULATION` do(s) município(s) do médico.
- Ordenação por prioridade/tempo de espera (reaproveitar lógica de `DispatchQueueIncidentSorter`).
- Colunas: talão, natureza, idade/sexo, queixa, tempo em espera, quem assumiu.
- Ação **"Assumir"** → `AssumeRegulationAction` → abre o formulário de regulação.
- Cronômetro de tempo de espera (indicador de resposta) por card.
- Atualização em tempo real via Reverb (`#[On('dispatch-board-refresh')]`, mesmo canal).

### 8.2 Formulário de Regulação — `RegulationForm` (modal ou tela)
Rota: `GET /operations/incidents/{incident}/regulation` → `operations.incidents.regulation`.
Campos:
- **Resumo da solicitação** (read-only): dados da vítima, local, queixa, ligações associadas.
- **Classificação de prioridade** (`ManchesterRisk` — badges coloridos Flux).
- **Hipótese diagnóstica** (texto).
- **Decisão** (`RegulationDecision`) — controla campos condicionais:
  - `DispatchResource` → **Recurso indicado** (`RegulationResource`: USB/USA/VIR/Moto/Aero).
  - `MedicalGuidance` → **Orientações** (texto obrigatório).
  - `Transfer` → **Destino** + observações.
  - `Refused` → **Motivo da recusa**.
- Botão **Registrar decisão** → `RegisterRegulationDecisionAction`.
- Ao autorizar recurso, mostra confirmação de que a ocorrência foi para a fila de despacho.

### 8.3 Ajustes em telas existentes
- **`DispatchBoard`**: nenhuma mudança de query (só vê `OPEN`). Opcional: exibir badge do recurso
  indicado (`recommended_resource`) e prioridade da regulação no card da fila, e um contador de
  "aguardando regulação" no painel de stats.
- **`IncidentOperationalDetail`** (`operations.incidents.show`): novo bloco **"Regulação médica"**
  com a decisão, médico responsável, prioridade, hipótese, orientações e tempos.
- **Sidebar**: grupo "Central" ganha item **"Regulação"** (ícone estetoscópio) sob `regulation.view`.

## 9. Relatórios

Seguir o padrão `IncidentNurseReport` + dompdf (`operations/documents/...`).

### 9.1 Ficha de Regulação (PDF por ocorrência)
Rota: `GET /operations/incidents/{incident}/regulation/document`.
Documento imprimível: dados da ocorrência + decisão de regulação + médico + assinatura + tempos.
Reaproveita o layout base de `resources/views/operations/documents/`.

### 9.2 Relatório gerencial de Regulação
Rota: `GET /operations/relatorios/regulacao` → `operations.reports.regulation.index`
(novo `App\Livewire\Operations\Reports\RegulationReport`, ao lado de `IncidentReport`).
Filtros: período, município, médico regulador, decisão, prioridade.
Indicadores (KPIs SAMU):
- Total de regulações por decisão (envio / orientação / transferência / recusa).
- **Tempo médio de regulação** (`response_time_seconds`) e **tempo de espera na fila**.
- Distribuição por prioridade (Manchester) e por recurso indicado.
- Produtividade por médico regulador.
- % de ocorrências resolvidas por orientação (sem viatura) — indicador de eficiência.
Export: tabela em tela + PDF (dompdf) no padrão `incident-report-pdf.blade.php`.

## 10. Tempo real (Reverb)

Reaproveitar o canal `operations.dispatch` já existente. Novos eventos:
`RegulationDecided`, `RegulationAssumed` → disparam `dispatch-board-refresh` para que fila de
regulação, DispatchBoard e detalhe atualizem sem reload (padrão atual da base).

## 11. Testes (Pest)

- Unit/Feature das Actions: transição de status correta por decisão; cálculo de `response_time`;
  bloqueio de dupla assunção; roteamento `DispatchResource → OPEN`, demais → `REGULATION_DENIED`.
- Policy: médico regula; atendente/despachador não.
- Fluxo ponta-a-ponta: criar (natureza com flag) → PENDING_REGULATION → assumir → decidir →
  aparece/não aparece na fila de despacho.
- Natureza sem flag: nasce OPEN (regressão do fluxo Bombeiros).

## 12. Fases de entrega

| Fase | Escopo | Entregável |
|------|--------|-----------|
| **1 — Núcleo de dados** | Migrations (`natures.requires_medical_regulation`, `incident_regulations`), enums, model `IncidentRegulation`, abilities, ajuste de `CreateOperationalIncidentAction`. | Ocorrência nasce no status correto; testes de status. |
| **2 — Regulação (médico)** | Actions/DTOs, `RegulationQueue`, `RegulationForm`, rotas, item de sidebar, tempo real. | Médico assume e decide; ocorrência flui para despacho. |
| **3 — Visibilidade** | Bloco de regulação no detalhe da ocorrência; badges/contadores na DispatchBoard. | Registro do médico visível na ocorrência. |
| **4 — Relatórios** | Ficha PDF por ocorrência + relatório gerencial com KPIs. | Telas e documentos de relatório. |
| **5 — Refino** | Cronômetros, ordenação por prioridade, ajustes de UX, cobertura de testes. | Módulo pronto para produção. |

## 13. Decisões validadas (2026-07-17)

1. **Abrangência** ✅ — regulação **por flag na natureza** (`natures.requires_medical_regulation`).
   Só naturezas de saúde passam por regulação; Bombeiros/salvamento seguem o fluxo atual sem mudança.
2. **Prioridade** ✅ — **reaproveitar `ManchesterRisk`** (badges/cores Flux e ordenação já prontos).
   Não criar `RegulationPriority`.
3. **Papel do médico no despacho** ✅ — médico **apenas autoriza**; ao decidir `DispatchResource` a
   ocorrência vai para `OPEN` e o **despachador empenha** a viatura na `DispatchBoard` (fluxo atual).
   Sem empenho direto pela tela de regulação.
4. **Perfil** ✅ — **reutilizar `UserLegacyProfile::Doctor` (case 4)**, acrescentando as abilities
   `regulation.view` e `regulation.regulate`. Sem novo perfil nem migração de usuários.
