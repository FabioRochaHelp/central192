# Fluxo de Ocorrências

## Ciclo completo observado

O ciclo atual combina chamada, ocorrência, despacho, etapas operacionais, registro clínico e encerramento. A central é centralizada; a segmentação por município/base aparece atualmente como `contract_id` em usuários, equipes, viaturas e filtros.

## 1. Entrada da chamada

A chamada pode entrar de duas formas principais:

- Manual: operador abre `/ocorrencia/create`, informa telefone e preenche o formulário Vue.
- Realtime: componente `OcorrenciaForm.vue` escuta evento Socket.IO `new-call`, toca um bip, recebe telefone e `latlng`, tenta localizar endereço no mapa e abre formulário.

A rota pública `v1/ocorrencia/nova-chamada` recebe JSON com `phone` e `latlng` e tenta repassar para servidor WebSocket. Essa rota é exceção de CSRF e usa filtro `visit`, não JWT.

## 2. Triagem/classificação

O operador escolhe um tipo:

- `C`: não completou.
- `A`: administrativo.
- `T`: trote.
- `N`: normal.
- `U`: urgente.

A regra de persistência é binária:

- `N` e `U` viram ocorrência operacional.
- Demais tipos viram registro em `ligacoes`.

## 3. Criação da ocorrência operacional

Endpoint: `POST /api/ocorrencia` -> `Ocorrencia::insert`.

Validações reais:

- Requer AJAX (`X-Requested-With: XMLHttpRequest`).
- Requer `Content-Type: application/json`.
- Requer JSON válido.

Campos usados para ocorrência:

- `talao`: gerado automaticamente por ano.
- `data`: data/hora atual.
- `endereco`, `numero`, `bairro`, `cidade`.
- `natureza_id`, `descricao`.
- `solicitante`, `telefone`.
- `turno_id`, opcional; a migration nova permite nulo.
- `status = 0`.
- `paciente`, `idade`, `sexo`, `tipo`.

Após salvar, o backend tenta notificar `WEBSOCKET_SERVER_URL/emergency`. Falha no WebSocket apenas gera warning e não desfaz a ocorrência.

## 4. Lista de ocorrências para despacho

O painel CCO (`Control::index` + `App.vue`) carrega:

- `GET /api/ocorrencias`: mapeado para `Ocorrencia::getAll`, retorna ocorrências com `status = 0` e join com `natureza`.
- `GET /api/viaturas`: mapeado para `Turno::getAll`, retorna turnos ativos/disponíveis (`final >= NOW()` e `turno.status = 1`) com viatura e efetivo.
- `GET /api/status-etapas`: mapeado para `Ocorrencia::etapas`, retorna registros ativos de `ocorrencia_has_turnos` para o Kanban.

## 5. Despacho/empenho

Endpoint: `POST /api/empenhar`.

Fluxo esperado:

1. Operador seleciona ocorrência e viatura/equipe disponível.
2. Frontend envia `ocorrencia_id`, `viatura_id` e observações.
3. Backend cria vínculo `ocorrencia_has_turnos` com status `empenhada`.
4. Backend marca turno como empenhado (`turno.status = 2`).
5. Backend atualiza ocorrência para status `2`, grava `horaEmpenho` e `turno_id`.
6. Frontend recarrega viaturas, etapas e ocorrências.

Atenção: há bug de resolução do turno em `empenhar()` usando `turno.id = viatura_id`.

## 6. Atendimento em etapas

O atendimento é acompanhado no Kanban por drag-and-drop. As colunas são:

1. `empenhada`: equipe empenhada.
2. `qti`: saída/deslocamento.
3. `local`: chegada ao local.
4. `saidaLocal`: saída do local.
5. `us`: chegada à unidade de saúde.
6. `saidaUs`: liberada da unidade de saúde.

Ao mover cartão, `App.vue` chama `POST /api/atualizar-status` com `id` do registro `ocorrencia_has_turnos` e novo `status`.

O backend impõe:

- status precisa existir na ordem fixa;
- não pode voltar;
- não pode pular;
- repetir status é sucesso sem alteração.

Cada etapa atualiza horário na ocorrência:

- Empenhada: `horaEmpenho`.
- QTI: `horaSaida`.
- Local: `horaLocal`.
- Saída local: `horaSaidaLocal`.
- U.S.: `horaHospital`.
- Saída U.S.: `horaSaidaHospital`.

## 7. Registro clínico da vítima

A partir do detalhe da ocorrência, o operador pode adicionar vítimas (`vitima/create/{ocorrencia_id}`).

Situações:

- `situacao = 1`: vítima atendida.
- `situacao = 3`: recusa de atendimento.

Para vítima atendida, são persistidos sinais vitais, procedimentos, acessórios e ferimentos. Após cada vítima, o sistema tenta comparar quantidade registrada com `totalVitima` para finalizar status clínico/administrativo da ocorrência.

## 8. Prescrição e validação

Se a vítima foi atendida e o usuário possui `users_type == 4`, a view exibe acesso à prescrição médica.

Fluxo:

1. Médico/usuário acessa `vitima/prescricao/{vitima_id}`.
2. Seleciona medicamentos do estoque da segmentação atual.
3. `savePrescricao()` cria prescrição e itens.
4. `getAlertsPrescricao()` lista prescrições pendentes para médico vinculado ao turno.
5. `validar()` aceita prescrição e baixa estoque.

## 9. Encerramento operacional

Endpoint: `POST /api/liberar`.

Pré-condição forte:

- A última etapa ativa precisa ser `saidaUs`.

Ao encerrar:

- `ocorrencia_has_turnos` é soft-deleted.
- `turno.status` volta para `1`.
- `ocorrencia.status` vira `3`.
- `ocorrencia.horaBase` recebe hora atual.

## Timeline operacional recomendada para migração

A migração deve transformar tempos soltos em eventos de timeline auditáveis:

- `call_received`: telefone, origem, coordenada, operador.
- `incident_created`: natureza, prioridade, endereço, município.
- `unit_dispatched`: turno/equipe/viatura, operador, horário.
- `unit_departed_base`: etapa `qti`.
- `unit_arrived_scene`: etapa `local`.
- `unit_left_scene`: etapa `saidaLocal`.
- `unit_arrived_hospital`: etapa `us`.
- `unit_released_hospital`: etapa `saidaUs`.
- `unit_returned_base`: encerramento/liberação.
- `victim_recorded`, `prescription_created`, `prescription_approved`, `stock_decremented`.
- `incident_closed`, `incident_cancelled`, `incident_qta`.

## Invariantes operacionais desejadas

- Uma viatura não pode estar em dois turnos ativos simultâneos.
- Um turno empenhado não pode ser empenhado em outra ocorrência ativa.
- O talão deve ser único por ano e município/base operacional.
- Etapas de deslocamento devem ser monotônicas.
- Encerramento exige etapa final e liberação explícita.
- Toda mudança de estado crítica deve gravar operador, origem, timestamp e motivo.
