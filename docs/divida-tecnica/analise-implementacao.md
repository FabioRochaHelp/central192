# Análise de Implementação: Projeto vs. Plano de Migração

**Data:** 2026-05-22
**Referência:** `docs/migracao/`

> **Escopo confirmado:** Sistema **multi-modalidade** — atende ocorrências de Incêndio
> (Florestal, Residencial, Comercial), Salvamento (Animal, Insetos agressivos, Outras) **e SAMU**.
> O código SAMU já implementado (vítimas, sinais vitais, prescrição, nurse report, Manchester)
> deve ser mantido. Os relatórios finais de Incêndio e Salvamento ainda precisam ser criados.
> Ver `docs/migracao/modalidades-relatorios.md` para o mapeamento completo.

---

## O que está implementado

### Stack e arquitetura

- Laravel 12 + Livewire 3 + Flux UI + PostgreSQL — stack-alvo completa instalada
- Estrutura `app/Domain/Operations/` com Actions, DTOs, Enums, Events, Services
- Reverb configurado (`config/reverb.php`) para WebSocket
- Suporte operacional em `app/Support/Operations/`

### Enums (todos os recomendados)

- `IncidentStatus`, `DispatchStage`, `CallType`, `ManchesterRisk`, `PrescriptionStatus`, `ShiftStatus`
- `DispatchStage` inclui mapeamento legado (`tryFromLegacyKanban`)

### Actions (ciclo operacional completo)

- `CreateOperationalIncidentAction`, `DispatchUnitAction`, `AdvanceDispatchStageAction`, `ReleaseUnitAction`
- `SaveVictimRecordAction`, `CreatePrescriptionAction`, `ApprovePrescriptionAction`
- `SaveIncidentNurseReportAction`, `SyncStandardInjuryMatrixSitesAction`
- `TalaoIssuer` com `Cache::lock` (Redis) — gera talão único por ano sem race condition

### Events de domínio

- `IncidentCreated`, `UnitDispatched`, `DispatchStageAdvanced`, `UnitReleased`
- `OperationalCallIntakeReceived`, `DashboardCallStatsInvalidate`

### Models (todos os principais)

- `Incident`, `IncidentDispatch`, `IncidentEvent`, `IncidentNurseReport`
- `Victim`, `VictimVitalSign`, `VictimInjuryMatrixEntry`
- `Prescription`, `PrescriptionItem`
- `Vehicle`, `Shift`, `Staff`, `Municipio`, `Nature`, `ProtectedArea`, `ProtectedAreaContact`, `UserType`
- Todos os parâmetros operacionais (Accessory, CareLocal, HealthUnit, InjurySite, NatureType, OperationalSupport, Procedure, VictimType)

### Migrations (estrutura alvo criada)

- `municipios`, `incidents`, `incident_dispatches`, `incident_events`
- `victims`, `victim_injury_matrix_entries`, `prescriptions`, `prescription_items`
- `shifts`, `staff`, `vehicles`, `nature_types`, `protected_areas`
- `transport_schedules` e `vehicle_checkups` existem no banco

### Livewire (telas operacionais)

- `DispatchBoard` — CCO com Kanban
- `IncidentCreate`, `IncidentCallStart`, `IncidentIndex`, `IncidentOperationalDetail`
- `VictimRecord`, `PrescriptionForm`, `PrescriptionApproval`, `IncidentNurseReport`
- `FleetShifts`, `VehicleManage`, `StaffManage`, `ShiftManage`
- Todos os 8 parâmetros operacionais (natureza, acessório, apoio, local, etc.)
- `MunicipioManage`, `SystemUserManage`

### Rotas

- Webhook `POST /integrations/calls/incident-intake` para entrada de chamada externa
- `/operations/dispatch`, `/incidents`, `/fleet`, `/parameters/*`, `/cadastro/*`, `/admin/*`

### Autorização

- Canais Reverb com policy: `operations.dispatch`, `operations.municipio.{id}`, `incidents.{id}`
- `hasOperationalAbility()` e `canAccessOperationalMunicipio()` no `User` model
- Policies para `Incident`, `Prescription`, `Victim`, `Vehicle`, `Shift`, `Staff`, `Municipio`, `User`
- `Cache::lock` em `DispatchUnitAction` e `ReleaseUnitAction` (impede duplo despacho)

### Mapas

- Leaflet + OpenStreetMap no `IncidentCreate` e `IncidentOperationalDetail`
- `OpenStreetMapGeocoder` (geocoding via Nominatim)
- `incident-osm.js` para mapa de ocorrência

---

## O que falta implementar

### 1. Integração Traccar (ausente por completo)

- Nenhum `app/Integrations/Traccar/` — sem proxy HTTP, cliente autenticado ou WebSocket
- Sem evento `VehiclePositionUpdated` nem `TraccarDeviceLinked`
- Sem Jobs `SyncTraccarPositions` e `FetchIncidentRouteFromTraccar`
- Sem cache local de posições (`vehicle_positions`) — o campo `device_id` existe no `Vehicle` mas não há integração ativa
- Referência: `docs/migracao/realtime-eventos.md` detalha a implementação esperada

### 2. Estoque / Inventário (ausente por completo)

- Nenhum domínio `app/Domain/Inventory/`
- Sem tabelas `materials`, `stock_balances`, `stock_movements` (migrations não criadas)
- `ApprovePrescriptionAction` existe mas **não baixa estoque** — o fluxo de prescrição está incompleto
- Sem evento `StockDecremented`
- Referência: `docs/migracao/regras-negocio.md` § Prescrição e validação médica

### 3. Jobs / Filas assíncronas (ausente por completo)

- Sem diretório `app/Jobs/`
- Sem `NotifyDispatchBoard`, `GenerateIncidentPdf`, `SendProtectedAreaNotification`, `WriteAuditLog`
- Broadcasts são síncronos — sem fila assíncrona para operações não críticas

### 4. Outbox transacional (ausente)

- Sem tabela `outbox_events` nem worker de publicação
- Eventos críticos podem se perder se o Reverb cair durante uma transação
- Referência: `docs/migracao/realtime-eventos.md` § Outbox

### 5. Audit log (ausente)

- Sem tabela `audit_logs` nem gravação de ator/ação/IP/payload
- Referência: `docs/migracao/banco-dados.md` § Oportunidades de melhoria

### 6. PDF / Impressão de ocorrência (ausente)

- Sem pacote PDF no `composer.json` (sem Dompdf/barryvdh)
- Sem Job `GenerateIncidentPdf`
- O sistema legado tinha `Ocorrencia::print` com Dompdf

### 7. `CloseIncidentAction` explícita (ausente)

- `ReleaseUnitAction` transiciona para `pending_nurse_report` (renomear para `pending_final_report`)
- Após o relatório final ser preenchido, **não há action para fechar** a ocorrência (`Closed`)
- Status `Closed` existe no enum mas nenhum fluxo o alcança
- Referência: `docs/migracao/plano-migracao-laravel.md` § Actions e `docs/migracao/modalidades-relatorios.md`

### 8. `CancelIncidentAction` (ausente)

- Status `Cancelled` existe no enum mas nenhuma action, rota ou tela o utiliza

### 9. Transporte agendado — UI ausente (migration existe)

- Tabela `transport_schedules` foi criada
- Sem Livewire component nem rota de gerenciamento

### 10. FleetCheckup / Checklist de viatura — UI ausente (migration existe)

- Tabela `vehicle_checkups` foi criada
- Sem `FleetCheckupManage` component nem rota

### 11. WhatsApp / Notificações para Área Protegida (ausente)

- `ProtectedArea` e `ProtectedAreaContact` existem nos models
- Sem listener nem job de notificação implementado

### 12. Laravel Horizon (ausente)

- O plano recomenda Horizon para monitorar filas
- Apenas `config/reverb.php` existe; sem `config/horizon.php`

### 14. Relatórios finais por modalidade CB (ausente por completo)

- Nenhuma das tabelas de relatório final CB foi criada (`fire_forest_reports`, `fire_building_reports`, `rescue_animal_reports`, `rescue_insect_reports`, `rescue_other_reports`)
- Sem tabela base `incident_final_reports`
- Nenhum Livewire component para preenchimento do relatório por modalidade
- Sem `SaveFinalReportAction`, `FinalReportFilled` event, `CloseIncidentAction`
- Sem coluna `report_modality` em `natures`
- O sistema SAMU já tem seu relatório final (`IncidentNurseReport`) — a arquitetura CB deve ser análoga
- Referência: `docs/migracao/modalidades-relatorios.md`

### 15. Kanban sem controle de modalidade (pendente)

- As colunas de hospital (`arrived_hospital`, `released_hospital`) aparecem para todas as ocorrências
- Para incêndios puros essas etapas não se aplicam — o Kanban deve ocultá-las com base no `report_modality` da natureza
- Referência: `docs/migracao/modalidades-relatorios.md` § Etapas de despacho por modalidade

### 13. Listagem de Ligações (ausente)

- Tabela `ligacoes` existe na migration
- Sem interface de consulta ou listagem de chamadas não-operacionais (tipos C, A, T)

---

## Resumo por área

| Área | Status |
|---|---|
| Stack Laravel / Livewire / Flux / Reverb | Completo |
| Enums e regras de negócio core | Completo |
| Actions do ciclo operacional | Completo |
| Models e migrations principais | Completo |
| Telas CCO / Kanban | Completo |
| Vítimas, sinais, prescrição (formulário SAMU) | Completo |
| Autorização e policies | Completo |
| Mapas Leaflet + OSM | Completo |
| Cache locks (anti duplo despacho) | Completo |
| Integração Traccar | Ausente |
| Estoque / Inventário | Ausente |
| Jobs / Filas assíncronas | Ausente |
| Outbox transacional | Ausente |
| Audit log | Ausente |
| PDF / Impressão de ocorrência | Ausente |
| `CloseIncidentAction` (pós-relatório final CB ou nurse report SAMU) | Ausente |
| `CancelIncidentAction` | Ausente |
| Transporte agendado (UI) | Ausente |
| FleetCheckup (UI) | Ausente |
| WhatsApp — notificações área protegida | Ausente |
| Laravel Horizon | Ausente |
| Listagem de ligações (C/A/T) | Ausente |
