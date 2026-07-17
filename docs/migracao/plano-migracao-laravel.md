# Plano de Migração para Laravel 12 + Livewire + Flux UI + PostgreSQL

## Direção arquitetural

Migrar para uma arquitetura modular orientada ao domínio operacional, não para um SaaS tradicional. A central continua centralizada; municípios são segmentações administrativas (`municipio_id`) usadas para filtro, autorização e roteamento operacional.

## Stack recomendada

- Laravel 12.
- PHP 8.3+.
- PostgreSQL 16+.
- Redis para cache, locks, filas e broadcasts.
- Laravel Reverb para WebSocket.
- Livewire 3 para telas operacionais reativas.
- Flux UI + TailwindCSS para interface.
- Laravel Horizon para filas.
- Laravel Pulse/Telescope em ambientes controlados para observabilidade.
- Laravel Sanctum para APIs internas/externas quando necessário.

## Estrutura de módulos/domínios

Sugestão:

- `app/Domain/Incidents`: ocorrência, talão, classificação, timeline.
- `app/Domain/Dispatch`: despacho, turno, viatura, etapas, disponibilidade.
- `app/Domain/Clinical`: vítima, sinais, procedimentos, prescrição.
- `app/Domain/Fleet`: viaturas, checkups, devices Traccar.
- `app/Domain/Staff`: efetivo, cargos, escala/turno.
- `app/Domain/Inventory`: estoque, materiais, movimentos.
- `app/Domain/Access`: usuários, papéis, permissões, policies.
- `app/Domain/Geo`: geocoding, mapas, rotas.
- `app/Integrations/Traccar`.
- `app/Support/Audit`.

## Organização Laravel

- Models Eloquent em cada domínio ou `app/Models` com namespaces por domínio.
- Actions para casos de uso críticos:
  - `CreateIncidentFromCallAction`.
  - `ClassifyCallAction`.
  - `DispatchUnitAction`.
  - `AdvanceDispatchStageAction`.
  - `ReleaseUnitAction`.
  - `CloseIncidentAction`.
  - `RecordVictimAction`.
  - `CreatePrescriptionAction`.
  - `ApprovePrescriptionAction`.
- Data objects/DTOs para entrada e saída.
- FormRequests para validação HTTP.
- Policies para autorização.
- Events para mudanças de estado.
- Listeners/Jobs para broadcast, notificações e integrações.

## Modelo de dados alvo

Entidades principais:

- `municipios` ou `bases_operacionais`.
- `users`, `roles`, `permissions` ou Spatie Permission, com abilities operacionais.
- `incidents`.
- `incident_calls` ou `calls`.
- `incident_dispatches`.
- `dispatch_stages` ou enum no dispatch.
- `incident_events` para timeline imutável.
- `vehicles`.
- `vehicle_devices` ou campo `traccar_device_id`.
- `shifts`.
- `shift_staff`.
- `staff`.
- `victims`.
- `victim_vitals`, `victim_procedures`, `victim_injuries`, `victim_accessories`.
- `prescriptions`, `prescription_items`.
- `materials`, `stock_balances`, `stock_movements`.
- `protected_areas`, `protected_area_contacts`.
- `audit_logs`.
- `outbox_events`.

## Status e enums alvo

Usar enums PHP + check constraints PostgreSQL:

- `IncidentStatus`: `open`, `dispatched`, `in_progress`, `closed`, `cancelled`, `qta`.
- `CallType`: `not_completed`, `administrative`, `hoax`, `normal`, `urgent`.
- `DispatchStage`: `dispatched`, `departed_base`, `arrived_scene`, `left_scene`, `arrived_hospital`, `released_hospital`, `returned_base`.
- `ShiftStatus`: `available`, `assigned`, `closed`, `inactive`.
- `VictimSituation`: `attended`, `refused`, `death`, conforme domínio confirmado.

## Livewire/Flux UI

Telas recomendadas:

- `DispatchBoard`: painel CCO com colunas de etapas, lista de ocorrências e viaturas disponíveis.
- `CreateIncidentModal`: modal de chamada/nova ocorrência.
- `IncidentTimeline`: timeline em tempo real.
- `VehicleStatusPanel`: disponibilidade e vínculo Traccar.
- `IncidentDetail`: detalhes, vítimas, horários e rota.
- `VictimForm`: atendimento clínico.
- `PrescriptionApprovalPanel`: alertas de prescrição pendente.
- `FleetCheckup`: checklists.

Flux UI deve substituir formulários Bootstrap/jQuery. Tailwind deve padronizar design da central.

## Realtime com Reverb

Broadcasts:

- `IncidentCreated` -> atualiza lista da central.
- `UnitDispatched` -> remove viatura disponível e adiciona no Kanban.
- `DispatchStageAdvanced` -> move cartão e atualiza timeline.
- `UnitReleased` -> libera viatura e remove do Kanban.
- `VehiclePositionUpdated` -> atualiza mapa/status.
- `PrescriptionCreated` -> notifica médico responsável.

Autorização de canais:

- Usuário central/supervisor acessa todos os municípios permitidos.
- Usuário municipal acessa apenas `municipio_id` autorizado.
- Não tratar município como tenant isolado; usar policy de escopo operacional.

## Redis, filas e locks

- Usar `Cache::lock("vehicle:{id}:dispatch")` para impedir duplo despacho.
- Usar transações DB + locks em `shifts`/`vehicles`.
- Jobs assíncronos para Traccar, PDF, WhatsApp e broadcasts não críticos.
- Outbox para publicar eventos após commit.

## Migração incremental

1. Congelar regras atuais em documentação e testes caracterizadores.
2. Corrigir bugs críticos no CI4 antes de migrar dados, se possível.
3. Criar schema PostgreSQL alvo com migrations Laravel.
4. Criar scripts de ETL idempotentes.
5. Mapear `contract_id` -> `municipio_id`.
6. Migrar cadastros básicos: municípios, usuários, perfis, naturezas, procedimentos, viaturas, efetivo.
7. Migrar turnos e ocorrências históricas.
8. Migrar vítimas, sinais, prescrições e estoque.
9. Implementar CCO em Livewire/Reverb em paralelo.
10. Rodar operação piloto com sincronização somente leitura.
11. Fazer cutover por janela operacional, com plano de rollback.

## Testes obrigatórios

- Unit tests para enums e transições.
- Feature tests para criar ocorrência, despachar, avançar etapas e encerrar.
- Tests de concorrência para duplo despacho.
- Tests de autorização por perfil/município.
- Tests de prescrição e baixa de estoque.
- Contract tests para Traccar.
- Browser tests para DispatchBoard.

## Visão arquitetural moderna recomendada

A arquitetura recomendada é event-driven transacional: comando altera estado em PostgreSQL dentro de transação, grava timeline/audit/outbox, e listeners assíncronos publicam para Reverb, notificam integrações e atualizam caches. A UI Livewire consome estado do banco e broadcasts autorizados, enquanto Redis fornece locks e filas. O domínio operacional fica em actions e services pequenos, testáveis e nomeados pela linguagem da central, não por CRUD.
