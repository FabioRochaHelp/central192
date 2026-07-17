# Entidades, Atributos e Relacionamentos

## Observação de modelagem

A aplicação atual usa tabelas e models com nomes herdados. Para a migração, `contract_id` deve ser tratado como segmentação administrativa atual e migrado para `municipio_id` ou para uma entidade explícita `Municipio/BaseOperacional`, sem isolar a operação como SaaS tradicional.

## Núcleo operacional

### Ocorrência (`ocorrencia`)

Representa o atendimento operacional da central. É criada para chamadas normais (`N`) e urgentes (`U`), despachada para uma equipe/turno e acompanhada até encerramento.

Atributos principais encontrados:

- `id`: identificador.
- `talao`: número sequencial anual gerado por `Ocorrencia::obterProximoNumeroOcorrencia()`.
- `data`: timestamp da ocorrência.
- `endereco`, `numero`, `bairro`, `cidade`, `referencia`: localização textual.
- `descricao`: descrição da chamada/ocorrência.
- `natureza_id`: natureza do atendimento.
- `turno_id`: turno/equipe vinculada; migration recente permite `null`.
- `status`: status numérico do ciclo principal.
- `qta`: flag/campo legado para ocorrências sem atendimento.
- `horaChamada`, `horaEmpenho`, `horaSaida`, `horaLocal`, `horaSaidaLocal`, `horaHospital`, `horaSaidaHospital`, `horaBase`: tempos operacionais.
- `totalVitima`, `totalObito`: totais esperados/registrados.
- `area_protegida_id`: adicionado por migration, usado no frontend e em consultas, mas não permitido no model atual.
- `solicitante`, `telefone`, `idade`, `sexo`, `tipo`: dados da chamada/paciente adicionados em 2025.
- `contract_id`: aparece no model, mas não foi visto na migration inicial; deve ser normalizado para `municipio_id` se existir no banco real.

Relacionamentos:

- `ocorrencia.natureza_id` -> `natureza.id`.
- `ocorrencia.turno_id` -> `turno.id`.
- `ocorrencia_has_turnos.ocorrencia_id` -> `ocorrencia.id`.
- `vitima.ocorrencia_id` -> `ocorrencia.id`.
- `area_protegida_id` aponta conceitualmente para `area_protegida.id`, mas a migration não cria FK.

Dependências no código:

- `OcorrenciaController` cria, lista, empenha, libera, atualiza etapas e gera relatório.
- `OcorrenciaModel::getDetalhe()` faz join com turno, viatura e natureza.
- `App.vue` consome ocorrências e envia comandos de despacho/status.
- `OcorrenciaForm.vue` cria ocorrência a partir da ligação/formulário.

### Despacho/Etapa de atendimento (`ocorrencia_has_turnos`)

Representa o vínculo operacional entre ocorrência e turno/equipe no Kanban.

Atributos:

- `id`.
- `ocorrencia_id`.
- `turno_id`.
- `status`: enum textual com valores `empenhada`, `qti`, `local`, `saidaLocal`, `us`, `saidaUs`.
- `deleted_at`: adicionado por migration posterior para ocultar registro encerrado do Kanban.

Relacionamentos:

- Pertence a uma ocorrência.
- Pertence a um turno.
- Por meio do turno chega à viatura e ao efetivo.

Regras associadas:

- A progressão é linear e não pode voltar nem pular etapa.
- O encerramento remove/soft-delete o registro do Kanban.
- A ocorrência só pode encerrar quando a última etapa for `saidaUs`.

### Turno (`turno`)

Representa uma equipe operacional alocada a uma viatura em um intervalo.

Atributos:

- `id`.
- `inicio`, `final`.
- `viatura_id`.
- `contract_id` atual, futuro `municipio_id`.
- `status`: usado como disponibilidade operacional; `1` disponível/ativo e `2` empenhado.

Relacionamentos:

- `turno.viatura_id` -> `viatura.id`.
- `turno.contract_id` -> `contract.id`.
- `turno_has_efetivo.turno_id` -> `turno.id`.
- `ocorrencia.turno_id` -> `turno.id`.
- `ocorrencia_has_turnos.turno_id` -> `turno.id`.

### Viatura (`viatura`)

Representa a unidade móvel.

Atributos:

- `id`, `placa`, `prefixo`, `marca`, `modelo`, `ano`.
- `status`: status cadastral/operacional legado; no fluxo de despacho a disponibilidade real é controlada em `turno.status`.
- `contract_id`: segmentação atual.
- `device_id`: vínculo com Traccar.
- `created_at`, `updated_at`, `deleted_at`.

Relacionamentos:

- `viatura.contract_id` -> `contract.id`.
- `turno.viatura_id` -> `viatura.id`.
- `device_id` referencia dispositivo externo Traccar, não FK local.

### Efetivo (`efetivo`)

Representa pessoa/equipe operacional escalável em turno.

Atributos:

- `id`, `name`, `tipo_documento`, `numero_documento`, `cpf`, `email`, `telefone`.
- `cargo`: usado para filtrar funções; `cargo = 2` aparece como médico para prescrição/validação.
- `contract_id` atual.
- timestamps e soft delete.

Relacionamentos:

- `efetivo.contract_id` -> `contract.id`.
- `turno_has_efetivo.efetivo_id` -> `efetivo.id`.
- `users.efetivo_id` vincula usuário a efetivo, mas não há FK na migration.

### Vítima (`vitima`)

Representa paciente/vítima vinculada à ocorrência.

Atributos principais:

- Identificação: `name`, `sexo`, `rg`, `idade`, `ssp`.
- Destino/atendimento: `hospital`, `transporte`, `unidadeSaude`, `medicoUS`, `crmMedicoUS`.
- Condições/trauma: `quedaAltura`, `pupilaReacao`, `pupilaTamanho`, `pupilaLado`, `pupilaSimetria`, `halitoEtilico`, `queimadura`, `veiculoOcupava`, `tipoAcidente`.
- Complementares: `dadosComplementares`.
- Classificações: `localCodigo`, `vitimatipoCodigo`, `situacao`, `status`.
- Recusa/óbito: `testemunha`, `rgTestemunha`, `sspTestemunha`, `obitoOnde`, `obitoParecer`.
- `ocorrencia_id`, `contract_id`.

Relacionamentos:

- `vitima.ocorrencia_id` -> `ocorrencia.id`.
- `vitima.contract_id` -> `contract.id`.
- `vitima_has_sinais.vitima_id` -> `vitima.id`.
- `vitima_has_procedimento.vitima_id` -> `vitima.id`.
- `vitima_has_acessorio.vitima_id` -> `vitima.id`.
- `vitima_has_ferimento.vitima_id` deveria apontar para `vitima.id`, mas a migration aponta incorretamente para `turno.id`.
- `vitima_has_prescricao.vitima_id` -> `vitima.id`.

### Sinais, procedimentos, acessórios e ferimentos da vítima

- `vitima_has_sinais`: registros seriados de sinais vitais por vítima, com hora, pressão, frequência, saturação, temperatura, destro, respostas neurológicas e Glasgow.
- `vitima_has_procedimento`: vínculo entre vítima e `procedimento`.
- `vitima_has_acessorio`: vínculo entre vítima e `acessorio`.
- `vitima_has_ferimento`: vínculo entre vítima e `local_ferimento`, mas FK está incorreta na migration.

### Prescrição (`vitima_has_prescricao`, `prescricao_has_medicamento`)

Representa prescrição feita para uma vítima e medicamentos vinculados.

- `vitima_has_prescricao` inclui `vitima_id`, profissional que prescreveu e campo `aceite` usado na validação.
- `prescricao_has_medicamento` vincula prescrição a `estoque` e quantidade.
- Ao validar, `Vitima::validar` marca aceite e baixa estoque com operação aritmética SQL.

## Cadastros operacionais

### Natureza e tipo de natureza

- `natureza_tipo`: tipo macro de natureza.
- `natureza`: natureza operacional, com `natureza_tipo_id`, nome e timestamps.
- Usada na criação/listagem/detalhe de ocorrência.

### Local, lesão, local_ferimento, procedimento, acessório, apoio

- `local`: cadastro auxiliar usado em vítima.
- `lesao` e `local_ferimento`: cadastros clínicos/de trauma.
- `procedimento`: procedimentos executáveis.
- `acessorio`: equipamentos/acessórios usados no atendimento.
- `apoio`: tipos/órgãos de apoio.

### Área protegida (`area_protegida`, `area_protegida_has_contato`)

- Área/ponto de referência relevante para chamada.
- Contatos podem ter `notifica = SIM`.
- Código de WhatsApp para notificação está comentado em `Ocorrencia::insert` e `Vitima::insert`.

### Unidade de atendimento (`unidade_atendimento`)

Cadastro de unidades de destino/atendimento.

## Identidade, acesso e navegação

### Usuário (`users`)

- `id`, `name_user`, `email`, `password_hash`, reset de senha, avatar, `active`, `contract_id`, `users_type_id`, `efetivo_id`, timestamps e soft delete.
- Senha é recebida como `password` no model e convertida para `password_hash` por callback.
- Login exige usuário existente, senha válida e ativo.

### Tipo de usuário (`users_type`)

- Define perfil/grupo funcional.
- Usado para permissões de menu/submenu.
- Regras no código assumem `users_type <= 2` como acesso amplo/central/admin; acima disso filtra por `contract_id`.
- `users_type == 4` libera prescrição médica na view de detalhe de ocorrência.

### Menu, submenu e permissões

- `menu` e `submenu` definem navegação.
- `menu_users_type` e `submenu_users_type` definem acesso por tipo de usuário.
- `Auth::getPermission($route)` consulta `submenu_users_type` via `SubmenuUsersTypeModel::permissionFor`.

## Integração e suporte

### Ligação (`ligacoes`)

Criada quando o tipo da chamada não é normal/urgente. Registra `tipo`, `referencia`, `descricao`, `solicitante`, `numero` e timestamps/soft delete.

### Traccar

Não há tabela local de posições. A relação local é `viatura.device_id`. Consultas em tempo real/rota são feitas no Traccar via HTTP e WebSocket proxy.

### Transporte agendado

`transport_schedules`: agenda transporte com paciente, tipo, data, hora, origem, destino e observações.

### Checkup de viatura

`viatura_checkups`: inspeção/checklist de viatura; a FK com viatura está comentada na migration.
