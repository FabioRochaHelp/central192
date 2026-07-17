# Especificacao de Migracao: Vitimas para Laravel 12 + Livewire

## Objetivo

Migrar a funcionalidade atual de vitimas do CodeIgniter 4 para Laravel 12 com Livewire, mantendo o comportamento operacional da central: uma vitima pertence a uma ocorrencia, pode ser registrada como atendida ou recusa, pode receber sinais vitais, procedimentos, equipamentos/acessorios, ferimentos, dados de transporte/unidade de saude e prescricao medica com validacao posterior.

Esta especificacao cobre as views atuais em `app/Views/Vitima/*`, o controller `Vitima`, o model `VitimaModel` e as migrations relacionadas.

## Funcionalidade Atual Mapeada

### Views atuais

- `Vitima/create.php`: tela principal de cadastro de vitima para uma ocorrencia. Inclui `_form.php`, campos ocultos `ocorrencia_id` e `contract_id`, carrega subformularios por AJAX e envia para `vitima/insert`.
- `Vitima/_form.php`: dados basicos da vitima: nome, RG, SSP, idade, sexo e situacao (`1` atendida, `3` recusa). A selecao da situacao carrega `atendida.php` ou `recusa.php`.
- `Vitima/atendida.php`: formulario clinico completo da vitima atendida: sinais vitais, Glasgow, matriz de ferimentos, pupilas, halito etilico, queimadura, queda, classificacao, cena/local, veiculo, tipo de acidente, equipamentos, procedimentos, transporte, unidade de saude, obito e dados complementares.
- `Vitima/recusa.php`: campos de testemunha para recusa de atendimento.
- `Vitima/obito.php`: view parcial praticamente igual ao `_form.php`, aparenta ser legado/incompleto.
- `Vitima/prescricao.php`: criacao de prescricao medica para vitima, com medicamentos vindos do estoque da base/municipio e itens adicionados dinamicamente.
- `Vitima/validar.php`: validacao de prescricao, exibindo medicamentos e botao de aceite; ao validar baixa estoque.
- `Vitima/detalhe.php`: view antiga/incompleta de detalhe da ocorrencia/vitimas.
- `Vitima/edit.php`: edicao antiga/inconsistente, inclui `Ocorrencia/_form` e atualiza ocorrencia, nao a vitima.
- `Vitima/finaliza.php`: wizard legado com dados ficticios, nao representa o fluxo atual de vitima.
- `Vitima/index.php` e `Vitima/pendente.php`: listagens inconsistentes; `index.php` lista viaturas e `pendente.php` lista ocorrencias. Devem ser descartadas ou reespecificadas.
- `Vitima/print.php` e `Vitima/printnow.php`: conteudo de PDF de processo administrativo legado (`procAdm`), nao especifico de vitima. Nao migrar como parte do modulo de vitima sem revisao funcional.

### Controller atual

Controller: `App\Controllers\Vitima`.

Responsabilidades atuais:

- `create($id)`: abre cadastro de vitima para ocorrencia.
- `recusa($id)`: retorna parcial de recusa por AJAX.
- `obito()`: retorna parcial de recusa; incompleto.
- `atendida($id)`: retorna parcial de atendimento por AJAX, carregando tipo de vitima, acessorios, procedimentos e locais.
- `insert()`: cria vitima; se atendida, grava sinais, acessorios, procedimentos e ferimentos; se recusa, grava testemunha; depois compara quantidade de vitimas com `ocorrencia.totalVitima`.
- `prescricao($id)`: tela para prescricao medica, filtrando medicamentos por categoria `3` e `contract_id`, e buscando efetivo medico do turno com `cargo = 2`.
- `validacao($id)`: tela para validar prescricao.
- `savePrescricao()`: cria prescricao e itens de medicamento.
- `getAlertsPrescricao()`: lista prescricoes pendentes para usuario medico logado.
- `validar()`: aceita prescricao e baixa estoque.
- `update()`: atualmente usa `insert($post)` em vez de update; deve ser corrigido na migracao.
- `destroy()`: soft delete da vitima.

## Regras de Negocio

### Situacao da vitima

- `situacao = 1`: vitima atendida.
- `situacao = 3`: recusa de atendimento.
- `situacao = 2` aparece em JavaScript como obito, mas nao esta integrado de forma consistente no backend atual.

Recomendacao Laravel:

- Criar enum `VictimSituation` com `attended`, `refused`, `death`.
- Migrar valores atuais:
  - `1` -> `attended`.
  - `3` -> `refused`.
  - `2`, se existir em dados reais -> `death`.

### Registro de vitima atendida

Quando a vitima e atendida, o sistema deve salvar em uma unica transacao:

- Dados basicos da vitima.
- Avaliacao clinica/trauma.
- Sinais vitais seriados.
- Procedimentos realizados.
- Acessorios/equipamentos utilizados.
- Ferimentos/localizacoes.

Se qualquer etapa falhar, toda a gravacao deve ser revertida.

### Registro de recusa

Quando ha recusa:

- Salvar dados basicos da vitima.
- Salvar testemunha, RG da testemunha e SSP da testemunha.
- Nao exigir sinais vitais, procedimentos, equipamentos ou ferimentos.

### Fechamento por quantidade de vitimas

O CodeIgniter compara:

- quantidade de vitimas cadastradas para a ocorrencia;
- `ocorrencia.totalVitima`.

Se forem iguais, tenta marcar a ocorrencia como `status = 1`.

Na migracao, essa regra deve ser revista porque `totalVitima` pode nao estar presente no fluxo novo de ocorrencia. Se mantida, deve virar uma Action explicita:

- `RecalculateIncidentVictimCompletionAction`.

Ela deve executar depois de criar/alterar/remover vitima e registrar evento de timeline.

### Prescricao medica

Regra atual:

- Apenas vitima atendida deve permitir prescricao.
- O acesso aparece na view de detalhe da ocorrencia quando `users_type == 4`.
- Medicamentos vem do estoque onde `categoria.id = 3` e `contract_id = user_logged()->contract_id`.
- O efetivo medico e identificado no turno da ocorrencia com `cargo = 2`.
- A prescricao nasce com `aceite = 0`.
- A validacao muda `aceite = 1` e baixa estoque.

Recomendacao Laravel:

- Autorizar por policy/ability, nao por `users_type == 4`.
- Usar `PrescriptionStatus`: `pending`, `approved`, `cancelled`.
- Validar saldo antes de aprovar.
- Baixar estoque por lancamento/movimento transacional, nao apenas decremento direto.

## Arquitetura Laravel Recomendada

### Controllers

Usar controllers finos apenas para telas/paginas e endpoints auxiliares. A logica de dominio deve ficar em Actions.

Controllers sugeridos:

- `VictimController`
  - `show(Incident $incident, Victim $victim)`
  - `destroy(Incident $incident, Victim $victim)`
  - `print(Incident $incident, Victim $victim)` se houver relatorio especifico da vitima.
- `PrescriptionController`
  - `show(Victim $victim)`
  - `approve(Prescription $prescription)`

Observacao: criacao/edicao principal deve ser feita por Livewire, sem controller recebendo formulario grande.

### Componentes Livewire

Componentes recomendados:

- `Victims/CreateVictim`
  - Substitui `Vitima/create.php`, `_form.php`, `atendida.php` e `recusa.php`.
  - Recebe `Incident $incident`.
  - Controla estado `situation`.
  - Renderiza blocos condicionais: dados basicos, atendimento, recusa, obito.
  - Salva usando `CreateVictimRecordAction`.

- `Victims/VictimClinicalForm`
  - Pode ser extraido se o formulario ficar muito grande.
  - Controla arrays dinamicos de sinais vitais, procedimentos, acessorios e ferimentos.

- `Victims/PrescriptionForm`
  - Substitui `Vitima/prescricao.php`.
  - Recebe `Victim $victim`.
  - Lista medicamentos disponiveis.
  - Permite adicionar/remover itens antes de salvar.
  - Usa `CreatePrescriptionAction`.

- `Victims/PrescriptionApproval`
  - Substitui `Vitima/validar.php`.
  - Exibe vitima, ocorrencia, prescritor e itens.
  - Usa `ApprovePrescriptionAction`.

- `Victims/PendingPrescriptionAlerts`
  - Substitui `getAlertsPrescricao()`.
  - Pode ser widget Livewire no dashboard medico.

### Actions

- `CreateVictimRecordAction`
  - Cria vitima e relacoes clinicas em transacao.
  - Recebe DTO com dados basicos, situacao e colecoes filhas.

- `UpdateVictimRecordAction`
  - Atualiza vitima e sincroniza relacoes.

- `DeleteVictimAction`
  - Soft delete e recalculo de completude da ocorrencia.

- `CreatePrescriptionAction`
  - Cria prescricao pendente e itens.

- `ApprovePrescriptionAction`
  - Valida permissao, confere status pendente, confere estoque, aprova e baixa estoque em transacao.

- `RecalculateIncidentVictimCompletionAction`
  - Recalcula estado da ocorrencia conforme regra de quantidade de vitimas, se essa regra continuar existindo.

### Policies

- `VictimPolicy`
  - `view`
  - `create`
  - `update`
  - `delete`
  - `recordClinicalData`

- `PrescriptionPolicy`
  - `create`
  - `approve`
  - `viewAlerts`

Regras de escopo:

- Operacao centralizada.
- `municipio_id` e segmentacao administrativa, nao tenant isolado.
- Usuario central/supervisor pode acessar multiplos municipios.
- Usuario municipal acessa apenas dados autorizados para seu `municipio_id`.

## Entidades Eloquent

### `Victim`

Tabela alvo: `victims`.

Campos sugeridos:

- `id`
- `incident_id`
- `municipio_id`
- `name`
- `sex`
- `document_rg`
- `document_ssp`
- `age`
- `situation`
- `status`
- `scene_location_id`
- `victim_type_id`
- `transported_by`
- `health_unit_id`
- `doctor_name`
- `doctor_crm`
- `fall_height`
- `pupil_light_reaction`
- `pupil_size`
- `pupil_side`
- `pupil_symmetry`
- `alcohol_breath`
- `burn_percentage`
- `occupied_vehicle_type`
- `accident_type`
- `death_location`
- `death_report_type`
- `witness_name`
- `witness_rg`
- `witness_ssp`
- `complementary_data`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`
- `deleted_at`

Relacionamentos:

- `incident()`
- `municipio()`
- `vitalSigns()`
- `procedures()`
- `accessories()`
- `injuries()`
- `prescriptions()`
- `victimType()`
- `sceneLocation()`

### `VictimVitalSign`

Tabela alvo: `victim_vital_signs`.

Campos:

- `id`
- `victim_id`
- `measured_at_time`
- `systolic_pressure`
- `diastolic_pressure`
- `heart_rate`
- `respiratory_rate`
- `oxygen_saturation`
- `temperature`
- `blood_glucose`
- `glasgow_eye`
- `glasgow_verbal`
- `glasgow_motor`
- `glasgow_total`
- `created_at`
- `updated_at`

Observacao: `destro` deve ser renomeado para `blood_glucose`.

### `VictimProcedure`

Pode ser pivot `procedure_victim` ou entidade propria se precisar auditoria.

Campos:

- `id`
- `victim_id`
- `procedure_id`
- `created_at`

### `VictimAccessory`

Pode ser pivot `accessory_victim`.

Campos:

- `id`
- `victim_id`
- `accessory_id`
- `created_at`

### `VictimInjury`

Tabela alvo: `victim_injuries`.

Campos:

- `id`
- `victim_id`
- `injury_location_id`
- `created_at`

Observacao: no sistema atual `local_ferimento_id` mistura tipo/local conforme a matriz. Na migracao, avaliar separar:

- `injury_type_id`: contusao, corte, escoriacao, perfurante, etc.
- `body_region_id`: cranio, face, pescoco, torax, etc.

Se nao houver tempo para normalizar, preservar `injury_location_id` com os valores atuais de `local_ferimento`.

### `Prescription`

Tabela alvo: `prescriptions`.

Campos:

- `id`
- `victim_id`
- `medical_staff_id`
- `prescribed_by_user_id`
- `status`
- `description`
- `approved_by_user_id`
- `approved_at`
- `created_at`
- `updated_at`

Mapeamento atual:

- `vitima_has_prescricao.efetivo_id` -> `medical_staff_id`.
- `vitima_has_prescricao.prescrita_por_id` -> `prescribed_by_user_id`.
- `aceite = 0` -> `pending`.
- `aceite = 1` -> `approved`.

### `PrescriptionItem`

Tabela alvo: `prescription_items`.

Campos:

- `id`
- `prescription_id`
- `stock_id`
- `material_id` opcional/desnormalizado
- `quantity`
- `created_at`
- `updated_at`

## Migrations Laravel Relacionadas

### `create_victims_table`

Origem: `vitima`.

Regras:

- Trocar `ocorrencia_id` por `incident_id`.
- Trocar `contract_id` por `municipio_id`.
- Converter nomes camelCase para snake_case.
- Criar FKs para `incidents`, `municipios`, `victim_types`, `scene_locations` e `health_units` quando essas tabelas existirem.
- Manter soft deletes.
- Usar enums/check constraints para campos de situacao e classificacoes.

Indices recomendados:

- `(incident_id)`
- `(municipio_id, created_at)`
- `(situation)`
- `(deleted_at)`

### `create_victim_vital_signs_table`

Origem: `vitima_has_sinais`.

Regras:

- FK `victim_id` com cascade delete.
- Armazenar Glasgow total, mas validar que total = eye + verbal + motor.
- Permitir multiplos registros por vitima.

Indices:

- `(victim_id)`
- `(victim_id, measured_at_time)`

### `create_victim_procedure_table`

Origem: `vitima_has_procedimento`.

Regras:

- FK para `victims`.
- FK para `procedures`.
- Unique `(victim_id, procedure_id)` se o mesmo procedimento nao puder repetir.

### `create_accessory_victim_table`

Origem: `vitima_has_acessorio`.

Regras:

- FK para `victims`.
- FK para `accessories`.
- Unique `(victim_id, accessory_id)` se o mesmo item nao puder repetir.

### `create_victim_injuries_table`

Origem: `vitima_has_ferimento`.

Regra critica:

- Corrigir FK atual incorreta. No CodeIgniter, `vitima_id` aponta para `turno.id`; no Laravel deve apontar para `victims.id`.

Indices:

- `(victim_id)`
- `(injury_location_id)`

### `create_prescriptions_table`

Origem: `vitima_has_prescricao`.

Regras:

- FK `victim_id` -> `victims`.
- FK `medical_staff_id` -> `staff`/`efetivo`.
- FK `prescribed_by_user_id` -> `users`.
- FK `approved_by_user_id` -> `users`, nullable.
- `status` com enum/check.
- `approved_at` nullable.

Indices:

- `(victim_id)`
- `(medical_staff_id, status)`
- `(prescribed_by_user_id)`

### `create_prescription_items_table`

Origem: `prescricao_has_medicamento`.

Regras:

- FK `prescription_id` -> `prescriptions`.
- FK `stock_id` -> `stock_balances` ou tabela equivalente de estoque.
- `quantity` inteiro positivo.

### Tabelas catalogo dependentes

Ja existem no dominio atual e devem ser migradas ou normalizadas:

- `vitima_tipo` -> `victim_types`.
- `procedimento` -> `procedures`.
- `acessorio` -> `accessories`.
- `local` -> `scene_locations`.
- `local_ferimento` -> `injury_locations` ou normalizacao em `injury_types` + `body_regions`.
- `unidade_atendimento` -> `health_units`.
- `estoque`, `material`, `categoria` -> modulo de estoque/materiais.

## Especificacao de Rotas Laravel

Rotas web sugeridas:

- `GET /incidents/{incident}/victims/create`
  - Renderiza Livewire `Victims\CreateVictim`.
- `GET /incidents/{incident}/victims/{victim}`
  - Detalhe da vitima.
- `GET /victims/{victim}/prescriptions/create`
  - Renderiza Livewire `Victims\PrescriptionForm`.
- `GET /prescriptions/{prescription}/approval`
  - Renderiza Livewire `Victims\PrescriptionApproval`.
- `DELETE /incidents/{incident}/victims/{victim}`
  - Soft delete.

As operacoes de salvar devem preferencialmente ser metodos Livewire, nao endpoints AJAX separados.

## Validacoes Principais

### Cadastro basico

- `incident_id`: obrigatorio, existente, visivel pelo usuario.
- `name`: obrigatorio.
- `sex`: obrigatorio.
- `situation`: obrigatoria.
- `age`: nullable, inteiro >= 0.
- `rg`, `ssp`: nullable.

### Se `attended`

- Permitir pelo menos um sinal vital quando a operacao exigir registro clinico.
- Validar `glasgow_total` entre 3 e 15.
- Validar `burn_percentage` entre 0 e 100.
- Validar arrays de procedimentos/acessorios/ferimentos como IDs existentes.

### Se `refused`

- `witness_name`, `witness_rg` e `witness_ssp` podem ser obrigatorios conforme regra operacional definida pela central.
- Nao exigir sinais/procedimentos.

### Prescricao

- Vitima deve estar `attended`.
- Usuario deve possuir permissao de prescrever.
- Medicamento deve existir no estoque do municipio/base autorizada.
- Quantidade deve ser positiva.

### Validacao de prescricao

- Prescricao deve estar `pending`.
- Usuario deve possuir permissao de aprovar.
- Estoque deve ter saldo suficiente para todos os itens.
- Aprovar e baixar estoque em transacao.

## Eventos de Dominio

Eventos recomendados:

- `VictimRecorded`
- `VictimUpdated`
- `VictimDeleted`
- `VictimRefusalRecorded`
- `VictimClinicalDataRecorded`
- `PrescriptionCreated`
- `PrescriptionApproved`
- `StockDecrementedForPrescription`
- `IncidentVictimCompletionRecalculated`

Esses eventos devem alimentar timeline da ocorrencia e audit log.

## Pontos de Atencao na Migracao

- Corrigir `Vitima::update`, que atualmente insere em vez de atualizar.
- Corrigir FK de `vitima_has_ferimento`.
- Remover views legadas que apontam para viatura, ocorrencia ou processo administrativo.
- Substituir jQuery/AJAX por estado Livewire.
- Transformar a tabela de sinais vitais dinamica em array Livewire validado.
- Nao usar `contract_id` na nova modelagem; migrar para `municipio_id`.
- Criar transacoes para cadastro de vitima e prescricao.
- Criar auditoria com usuario responsavel, data/hora e origem da alteracao.

## Resultado Esperado

Ao final da migracao, o modulo de vitimas deve permitir:

- Registrar vitima atendida ou recusa dentro da ocorrencia.
- Registrar dados clinicos completos sem recarregamentos parciais por AJAX.
- Manter vinculos normalizados com procedimentos, acessorios, ferimentos e sinais vitais.
- Criar e aprovar prescricoes com baixa de estoque segura.
- Publicar eventos para timeline operacional da ocorrencia.
- Aplicar autorizacao por policy e escopo de municipio dentro da operacao centralizada.
