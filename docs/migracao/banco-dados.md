# Banco de Dados Atual e Recomendações

## Visão geral

O banco é modelado via migrations CodeIgniter, com padrão MySQL/MariaDB. A migração para PostgreSQL deve revisar tipos, índices, FKs, enums e nomes. Existem divergências entre migrations, models e uso real no código.

## Tabelas principais

### `contract`

Segmentação administrativa atual. Na migração, deve virar `municipios` ou `bases_operacionais`, conforme decisão de domínio.

Colunas: `id`, `razao`, `cnpj`, `ie`, `phone`, `zipcode`, `address`, `number`, `district`, `city`, `state`, `active`, timestamps e `deleted_at`.

Índices/constraints: `id` PK; campos `razao`, `cnpj`, `ie`, `phone` únicos na migration.

### `users`

Colunas: `id`, `name_user`, `email`, `password_hash`, `reset_hash`, `reset_expire_at`, `avatar`, `active`, `contract_id`, `users_type_id`, `efetivo_id`, timestamps, `deleted_at`.

FKs: `contract_id`, `users_type_id`. `efetivo_id` não tem FK na migration.

### `users_type`

Perfil/tipo de usuário.

### `menu`, `submenu`, `menu_users_type`, `submenu_users_type`

Controle de navegação/permissões. `submenu.route` é base para `user_permission()`.

### `ocorrencia`

Colunas da migration inicial: `id`, `talao`, `data`, endereço, `referencia`, horários, `totalVitima`, `totalObito`, `descricao`, `status`, `qta`, `natureza_id`, `turno_id`.

Colunas adicionadas: `area_protegida_id`, `solicitante`, `telefone`, `idade`, `sexo`, `tipo`, `horaEmpenho`, `horaSaida`; `turno_id` passou a aceitar `null`.

FKs: `turno_id` -> `turno.id`. `natureza_id` é usado como FK conceitual, mas não foi vista FK na migration. `area_protegida_id` sem FK.

Índices recomendados no PostgreSQL:

- `(status, data)`.
- `(talao, extract(year from data))` ou coluna `ano` gerada/persistida com unique `(municipio_id, ano, talao)`.
- `(turno_id)`.
- `(natureza_id)`.
- `(municipio_id, status, data)` após normalização.

### `ocorrencia_has_turnos`

Colunas: `id`, `ocorrencia_id`, `turno_id`, `status`, `deleted_at` posterior.

FKs: ocorrência e turno.

Status atual como ENUM MySQL: `empenhada`, `qti`, `local`, `saidaLocal`, `us`, `saidaUs`.

No PostgreSQL, preferir:

- `dispatch_stage` como enum nativo ou varchar com check constraint;
- `position` inteiro para ordem;
- audit/event table para histórico completo.

Índices recomendados:

- `(deleted_at, status)` para Kanban.
- `(ocorrencia_id, deleted_at)`.
- `(turno_id, deleted_at)`.
- Unique parcial para impedir uma ocorrência ativa por turno quando aplicável.

### `turno`

Colunas: `id`, `inicio`, `final`, `viatura_id`, `contract_id`, `status`.

FKs: viatura e contract.

Índices recomendados:

- `(viatura_id, final)`.
- `(status, final)`.
- Unique/exclusion constraint para evitar turnos ativos sobrepostos por viatura.

### `turno_has_efetivo`

Colunas: `id`, `turno_id`, `efetivo_id`. FKs para turno e efetivo.

Recomendado unique `(turno_id, efetivo_id)`.

### `viatura`

Colunas: `id`, `placa`, `prefixo`, `marca`, `modelo`, `ano`, `status`, `contract_id`, `device_id`, timestamps, `deleted_at`.

FK: contract.

Constraints: `placa` unique. Recomendado unique `(municipio_id, prefixo)` e índice em `device_id`.

### `efetivo`

Colunas: `id`, `name`, documentos, `cpf`, `email`, `telefone`, `cargo`, `contract_id`, timestamps, `deleted_at`.

FK: contract.

Recomendado índices em `(contract_id, cargo)` e CPF único quando aplicável.

### `vitima`

Colunas clínicas/identificação detalhadas, `ocorrencia_id`, `contract_id`, timestamps e soft delete.

FKs: ocorrência e contract.

Problemas:

- Campo `veiculoOcupava` aparece duplicado na migration.
- Muitos inteiros representam enums sem tabela/check.
- `sexo` é int na migration de vítima, mas char/string em ocorrência.

### Tabelas filhas de vítima

- `vitima_has_sinais`: sinais vitais seriados.
- `vitima_has_procedimento`: vítima-procedimento.
- `vitima_has_acessorio`: vítima-acessório.
- `vitima_has_ferimento`: vítima-local ferimento, com FK incorreta no código analisado.
- `vitima_has_prescricao`: prescrição por vítima.
- `prescricao_has_medicamento`: itens da prescrição.

### Estoque

- `estoque`: saldo por material/segmentação.
- `estoque_lancamento`: movimentações.
- `material`: cadastro com categoria/unidade.
- `categoria`, unidades auxiliares.

Recomendado migrar baixa de prescrição para movimentação de estoque transacional, com ledger imutável.

### Traccar

Não há tabelas locais de posição/evento. Apenas `viatura.device_id` vincula o dispositivo externo. Para PostgreSQL, avaliar cache local de últimas posições em `vehicle_positions` e histórico mínimo de eventos importantes.

## Oportunidades de melhoria

- Padronizar nomes em snake_case.
- Trocar `contract_id` por `municipio_id` ou `base_operacional_id` conforme linguagem ubíqua.
- Substituir campos de status numéricos por enums com check constraints.
- Criar tabela `incident_events`/`dispatch_events` para timeline.
- Criar `audit_logs` com ator, ação, payload, IP e timestamp.
- Adicionar FKs ausentes e corrigir FKs incorretas.
- Adicionar índices para consultas em tempo real.
- Usar `timestamptz` para eventos operacionais e timezone explícito.
