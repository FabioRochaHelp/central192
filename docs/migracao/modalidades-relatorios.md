# Modalidades de Ocorrência e Relatórios Finais

**Data:** 2026-05-22
**Contexto:** O sistema é multi-modalidade — atende ocorrências de Incêndio, Salvamento **e SAMU**
na mesma central. Cada modalidade possui seu próprio relatório final. O código SAMU já existente
(vítimas, sinais vitais, prescrição médica, relatório de enfermagem, Manchester) deve ser mantido.
Este documento mapeia as modalidades CB que ainda precisam ser implementadas e o modelo de dados
unificado para relatórios finais por modalidade.

---

## Taxonomia de modalidades

```
SAMU  (já implementado)
├── Normal
└── Urgente

Incêndio  (a implementar)
├── Florestal
├── Residencial
└── Comercial / Industrial

Salvamento  (a implementar)
├── Animal em situação de risco
├── Insetos agressivos
└── Outras
    ├── Aquático
    ├── Em altura
    ├── Colapso estrutural
    ├── Desencarceramento veicular
    ├── Espaço confinado
    └── Elevador
```

A `NatureType` existente suporta essa hierarquia diretamente:
- `NatureType.name` = "Incêndio" ou "Salvamento"
- `Nature.name` = "Florestal", "Residencial", "Animal em situação de risco", etc.

A **modalidade do relatório final** é derivada da `Nature` selecionada na ocorrência — um enum
`IncidentReportModality` determina qual formulário/tabela de relatório é usado.

---

## Enum recomendado: `IncidentReportModality`

```php
enum IncidentReportModality: string
{
    case Samu                = 'samu';            // já implementado — vítimas, sinais, prescrição, nurse report
    case FireForest          = 'fire_forest';
    case FireBuilding        = 'fire_building';   // residencial + comercial/industrial
    case RescueAnimal        = 'rescue_animal';
    case RescueInsects       = 'rescue_insects';
    case RescueOther         = 'rescue_other';
}
```

`FireBuilding` cobre residencial e comercial/industrial por um campo `building_type`
(evita duas tabelas quase idênticas).

---

## Modelo de dados recomendado

```
incident_final_reports   (base comum a todas as modalidades)
├── fire_forest_reports  (campos específicos de incêndio florestal)
├── fire_building_reports (campos específicos de incêndio em edificação)
├── rescue_animal_reports
├── rescue_insect_reports
└── rescue_other_reports
```

### `incident_final_reports` — base

| Coluna              | Tipo        | Descrição                                              |
|---------------------|-------------|--------------------------------------------------------|
| id                  | bigint PK   |                                                        |
| incident_id         | bigint FK   | `incidents.id` — único por ocorrência                  |
| modality            | varchar     | enum `IncidentReportModality`                          |
| filled_by           | bigint FK   | `users.id` — quem preencheu                            |
| filled_at           | timestamptz | data/hora do preenchimento                             |
| victims_rescued     | smallint    | pessoas resgatadas com vida                            |
| victims_injured     | smallint    | pessoas com lesões                                     |
| victims_deceased    | smallint    | óbitos confirmados                                     |
| resources_summary   | text        | resumo textual de recursos empregados (viaturas, pessoal) |
| external_support    | text        | apoios externos acionados                              |
| observations        | text        | observações gerais                                     |

Índices: `unique(incident_id)`, `(modality, filled_at)`.

---

## Campos específicos por modalidade

### `fire_forest_reports` — Incêndio Florestal

| Coluna                   | Tipo           | Valores / Notas                                              |
|--------------------------|----------------|--------------------------------------------------------------|
| incident_final_report_id | bigint FK PK   |                                                              |
| affected_area_ha         | numeric(10,2)  | área estimada em hectares                                    |
| vegetation_type          | varchar        | cerrado, mata atlântica, pasto, capoeira, eucalipto, outro  |
| fire_behavior            | varchar        | superficial, copa, salto (spotting), misto                  |
| probable_cause           | varchar        | raio, descuido humano, criminoso, operacional, indeterminado |
| discovery_source         | varchar        | vigilância aérea, denúncia, INPE, rondante, outro           |
| temperature_celsius      | smallint       |                                                              |
| humidity_percent         | smallint       |                                                              |
| wind_speed_kmh           | smallint       |                                                              |
| wind_direction           | varchar        | N, NE, L, SE, S, SO, O, NO                                  |
| affected_coordinates     | text           | WKT polygon ou JSON de pontos GPS                           |
| vehicles_used            | jsonb          | `[{"type":"...", "qty":1}]`                                 |
| personnel_count          | smallint       |                                                              |
| aircraft_used            | boolean        |                                                              |
| aircraft_description     | text nullable  |                                                              |
| external_agencies        | text           | IBAMA, ICMBio, Exército, voluntários, etc.                  |
| actions_taken            | text           | aceiro, contrafogo, abafamento, retardante, etc.            |
| fauna_damage             | boolean        |                                                              |
| fauna_damage_description | text nullable  |                                                              |
| structures_affected      | smallint       | quantidade de benfeitorias/imóveis atingidos                |
| people_evacuated         | smallint       |                                                              |
| final_status             | varchar        | extinto, controlado, monitoramento, repassado               |
| control_achieved_at      | timestamptz nullable |                                                        |
| extinction_achieved_at   | timestamptz nullable |                                                        |

---

### `fire_building_reports` — Incêndio Residencial / Comercial / Industrial

| Coluna                   | Tipo           | Valores / Notas                                                    |
|--------------------------|----------------|--------------------------------------------------------------------|
| incident_final_report_id | bigint FK PK   |                                                                    |
| building_type            | varchar        | residencial, comercial, industrial, institucional, misto, veículo |
| construction_type        | varchar        | alvenaria, madeira, metálica, misto                               |
| floors_total             | smallint       | número de andares da edificação                                    |
| floors_affected          | smallint       | andares atingidos                                                  |
| affected_area_m2         | numeric(8,2)   | área atingida em m²                                               |
| rooms_affected           | varchar[]      | sala, quarto, cozinha, banheiro, garagem, depósito, etc.          |
| probable_cause           | varchar        | falha elétrica, vazamento de gás, descuido, criminoso, explosão, curto, indeterminado |
| fire_origin_location     | varchar        | cômodo/setor de origem                                            |
| hazmat_present           | boolean        |                                                                    |
| hazmat_description       | text nullable  | inflamáveis, GLP, produtos químicos, etc.                         |
| occupants_at_incident    | smallint       | pessoas presentes no momento                                      |
| animals_rescued          | smallint       |                                                                    |
| animals_deceased         | smallint       |                                                                    |
| residents_displaced      | smallint       | desabrigados                                                       |
| damage_level             | varchar        | parcial_leve (<25%), parcial_grave (26-75%), total (>75%)         |
| vehicle_involved         | boolean        | incêndio envolveu veículo                                         |
| external_agencies        | text           | concessionária de gás, CELESC/COPEL, PM, etc.                     |
| actions_taken            | text           | combate, busca e salvamento, ventilação, rescaldo, isolamento     |
| final_status             | varchar        | extinto, controlado, monitoramento, transferido                   |
| business_name            | varchar nullable | para comercial/industrial                                        |
| business_activity        | varchar nullable | ramo de atividade                                                |

---

### `rescue_animal_reports` — Salvamento de Animal

| Coluna                   | Tipo           | Valores / Notas                                                    |
|--------------------------|----------------|--------------------------------------------------------------------|
| incident_final_report_id | bigint FK PK   |                                                                    |
| animal_category          | varchar        | doméstico, silvestre, de produção                                 |
| animal_species           | varchar        | cão, gato, cavalo, boi, serpente, ave, jacaré, outro             |
| animal_breed             | varchar nullable |                                                                  |
| animal_size              | varchar nullable | pequeno, médio, grande                                           |
| entrapment_type          | varchar        | árvore, buraco, cisterna/poço, via aquática, veículo, estrutura, cerca/cabo, elevado, outro |
| entrapment_height_m      | smallint nullable | altura em metros (quando aplicável)                             |
| animal_condition_arrival | varchar        | calmo, agitado, ferido, inconsciente, óbito na chegada           |
| equipment_used           | text           | escada, corda, rede, armadilha, alicate, EPI específico          |
| outcome                  | varchar        | resgatado_tutor, resgatado_abrigo, resgatado_veterinario, solto_silvestre, nao_localizado, obito |
| owner_name               | varchar nullable |                                                                  |
| owner_phone              | varchar nullable |                                                                  |
| destination_notes        | text nullable  |                                                                   |

---

### `rescue_insect_reports` — Insetos Agressivos

| Coluna                   | Tipo           | Valores / Notas                                                    |
|--------------------------|----------------|--------------------------------------------------------------------|
| incident_final_report_id | bigint FK PK   |                                                                    |
| insect_type              | varchar        | abelhas, marimbondos, vespas, maribondo-tatu, outro               |
| insect_species           | varchar nullable | Apis mellifera, Polybia, etc.                                   |
| colony_size_estimate     | varchar        | pequena (<5k), média (5–20k), grande (>20k), indeterminada       |
| nest_location_type       | varchar        | parede/forro, árvore, subsolo, veículo, caixa de luz/água, estrutura metálica, outro |
| nest_location_detail     | text nullable  |                                                                   |
| technique_used           | varchar        | captura_realocacao, exterminacao_quimica, exterminacao_fisica, nao_realizado |
| colony_destination       | varchar        | apicultor, exterminada, realocada, abandono_local                 |
| people_stung             | smallint       |                                                                    |
| sting_severity           | varchar        | sem_atendimento, leve, moderado_hospitalar, grave                 |
| prehospital_care         | boolean        | prestou atendimento pré-hospitalar                                |
| prehospital_description  | text nullable  |                                                                   |
| equipment_used           | text           | traje apicultor, extintores, bomba vácuo, EPI                    |

---

### `rescue_other_reports` — Outros Salvamentos

| Coluna                   | Tipo           | Valores / Notas                                                              |
|--------------------------|----------------|------------------------------------------------------------------------------|
| incident_final_report_id | bigint FK PK   |                                                                              |
| rescue_subtype           | varchar        | aquatico, altura, colapso_estrutural, desencarceramento, espaco_confinado, elevador, outro |
| victim_count             | smallint       | número de pessoas envolvidas                                                 |
| situation_description    | text           | descrição da situação encontrada                                             |
| victim_condition         | varchar        | ileso, ferido_leve, ferido_grave, obito                                     |
| entrapment_description   | text nullable  | como a vítima estava presa/em risco                                         |
| rescue_technique         | text           | técnica utilizada                                                            |
| equipment_used           | text           | equipamentos e materiais                                                     |
| hospital_transport       | boolean        | vítima transportada para hospital                                            |
| hospital_name            | varchar nullable |                                                                             |
| outcome                  | varchar        | resgatado_ileso, resgatado_ferido, obito_local, nao_localizado               |
| duration_minutes         | smallint nullable | tempo de operação de salvamento                                           |

---

## Etapas de despacho por modalidade

As etapas atuais (`DispatchStage`) foram pensadas para SAMU (com estágio de hospital).
Para Bombeiros, adaptar:

| Stage atual          | Label CB recomendado       | Aplicável a                     |
|----------------------|----------------------------|---------------------------------|
| `dispatched`         | Empenhada                  | Todas                           |
| `departed_base`      | A caminho (QTI)            | Todas                           |
| `arrived_scene`      | No local                   | Todas                           |
| `left_scene`         | Saída do local             | Todas                           |
| `arrived_hospital`   | A caminho para suporte *   | Só quando há transporte de vítima |
| `released_hospital`  | Suporte encerrado *        | Só quando há transporte de vítima |

`*` Os dois últimos estágios devem ser opcionais/condicionais no Kanban,
ativados apenas quando `hospital_transport = true` no relatório final.
Uma ocorrência de incêndio florestal encerra em `left_scene`.

---

## Impacto no código existente

### O que **manter sem alteração** (SAMU — já implementado)

| Componente                               | Situação                                               |
|------------------------------------------|--------------------------------------------------------|
| `ManchesterRisk` enum                    | Triagem SAMU — manter                                 |
| `IncidentNurseReport` model/action/view  | Relatório de enfermagem SAMU — manter                 |
| `PrescriptionForm` / `PrescriptionApproval` | Prescrição médica SAMU — manter                   |
| `VictimVitalSign` model                  | Sinais vitais clínicos SAMU — manter                  |
| `VictimInjuryMatrixEntry` model          | Matriz de ferimentos SAMU — manter                    |
| `Prescription` / `PrescriptionItem`      | Prescrição de medicamentos SAMU — manter              |
| Campos em `incidents`: `patient_*`, `manchester_risk` | Dados clínicos de paciente SAMU — manter |
| `CareLocal` parâmetro                    | Local de atendimento hospitalar SAMU — manter         |
| `HealthUnit` parâmetro                   | Unidade de saúde SAMU — manter                        |
| `Victim` model completo                  | Utilizado no fluxo SAMU — manter                      |
| Rota `/incidents/{incident}/nurse-report` | Fluxo SAMU — manter                                  |
| `IncidentStatus::PendingNurseReport`     | Status pós-liberação SAMU — manter                    |

### O que **adaptar**

**`IncidentStatus::PendingNurseReport`:** Para ocorrências CB (Incêndio/Salvamento), o status
pós-liberação deve ser `PendingFinalReport`. Para SAMU, permanece `PendingNurseReport`.
Opções:
1. Renomear para `PendingFinalReport` e tratar o nurse report como uma das formas de relatório final.
2. Manter os dois status separados e rotear por modalidade.

Recomendação: opção 1 — unificar em `PendingFinalReport`, e o sistema decide qual formulário
exibir (`/incidents/{id}/nurse-report` para SAMU, `/incidents/{id}/final-report` para CB).

**`DispatchStage`:** As etapas de hospital (`arrived_hospital`, `released_hospital`) são
relevantes para SAMU e para salvamentos com transporte de vítima. Para incêndio puro,
o Kanban deve **ocultar** essas colunas. A lógica de exibição deve ser guiada pelo
`report_modality` da natureza da ocorrência.

### O que **manter sem alteração**

- Todo o ciclo de despacho: `DispatchUnitAction`, `AdvanceDispatchStageAction`, `ReleaseUnitAction`
- `DispatchBoard`, `IncidentCreate`, `IncidentIndex`, `FleetShifts`
- `Nature` / `NatureType` — ampliar o cadastro com as novas naturezas CB
- `TalaoIssuer`, `IncidentTimelineRecorder`, `IncidentEvent`
- `CallType` enum e classificação da chamada
- Ciclo de autenticação, turno, viatura, efetivo
- Integração Traccar (ainda relevante para rastreamento de viaturas)

---

## Novos componentes necessários

### Livewire

| Componente                         | Função                                                    |
|------------------------------------|-----------------------------------------------------------|
| `IncidentFinalReport`              | Formulário genérico que carrega sub-form por modalidade  |
| `FinalReport/FireForestForm`       | Campos específicos de incêndio florestal                 |
| `FinalReport/FireBuildingForm`     | Campos específicos de incêndio em edificação             |
| `FinalReport/RescueAnimalForm`     | Campos específicos de salvamento animal                  |
| `FinalReport/RescueInsectForm`     | Campos específicos de insetos agressivos                 |
| `FinalReport/RescueOtherForm`      | Campos específicos de outros salvamentos                 |

### Actions

| Action                             | Função                                                    |
|------------------------------------|-----------------------------------------------------------|
| `SaveFinalReportAction`            | Orquestra gravação em `incident_final_reports` + subtabela |
| `CloseIncidentAction`              | Fecha a ocorrência após relatório final preenchido        |

### Events

| Event                              | Disparo                                                   |
|------------------------------------|-----------------------------------------------------------|
| `FinalReportFilled`                | Ao salvar relatório final                                |
| `IncidentClosed`                   | Ao fechar a ocorrência                                   |

### Migrations necessárias

1. `create_incident_final_reports_table`
2. `create_fire_forest_reports_table`
3. `create_fire_building_reports_table`
4. `create_rescue_animal_reports_table`
5. `create_rescue_insect_reports_table`
6. `create_rescue_other_reports_table`
7. `alter_incidents_table_remove_samu_fields` (remover `manchester_risk`, campos de paciente)
8. `rename_incident_status_pending_nurse_to_pending_final_report`
9. `drop_prescriptions_and_victim_clinical_tables` (após confirmação)

---

## Fluxo operacional revisado

```
Chamada recebida
    → Classificação (CallType: C/A/T/N/U)
    → Criação da ocorrência (natureza CB: Incêndio Florestal, Residencial, Salvamento Animal…)
    → Despacho de viatura/equipe
    → Kanban: Empenhada → A caminho → No local → Saída do local
                                                  [→ A caminho suporte → Suporte encerrado]
    → Liberação da viatura  (ReleaseUnitAction)
    → Preenchimento do relatório final (formulário por modalidade)
    → Fechamento da ocorrência  (CloseIncidentAction)
```

O status `PendingFinalReport` (substitui `PendingNurseReport`) é atingido ao liberar a
viatura. A ocorrência só vai para `Closed` após o relatório final ser preenchido.

---

## Ligação entre `Nature` e `IncidentReportModality`

Cadastro sugerido na tabela `natures`:

| NatureType  | Nature                      | report_modality          |
|-------------|-----------------------------|--------------------------|
| SAMU        | Urgência clínica            | samu                     |
| SAMU        | Trauma                      | samu                     |
| SAMU        | Suporte básico de vida      | samu                     |
| Incêndio    | Florestal                   | fire_forest              |
| Incêndio    | Residencial                 | fire_building            |
| Incêndio    | Comercial                   | fire_building            |
| Incêndio    | Industrial                  | fire_building            |
| Incêndio    | Veicular                    | fire_building            |
| Salvamento  | Animal em situação de risco | rescue_animal            |
| Salvamento  | Insetos agressivos          | rescue_insects           |
| Salvamento  | Aquático                    | rescue_other             |
| Salvamento  | Em altura                   | rescue_other             |
| Salvamento  | Desencarceramento veicular  | rescue_other             |
| Salvamento  | Colapso estrutural          | rescue_other             |
| Salvamento  | Espaço confinado            | rescue_other             |
| Salvamento  | Elevador                    | rescue_other             |

Implementação: adicionar coluna `report_modality varchar nullable` na tabela `natures`.
O sistema a usa para:
- Determinar qual sub-form exibir ao preencher o relatório final
- Mostrar/ocultar etapas de hospital no Kanban
- Controlar quais campos do formulário de criação de ocorrência são exibidos
  (Manchester risk e dados de paciente só aparecem para `samu`)

Naturezas sem `report_modality` definida usam o formulário genérico / relatório livre.
