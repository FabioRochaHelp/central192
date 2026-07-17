# Regras de Negócio Encontradas

## Classificação da chamada

A criação de ocorrência pelo Vue (`OcorrenciaForm.vue`) envia um campo `tipo` com os seguintes significados documentados na migration:

- `C`: não completou.
- `A`: administrativo.
- `T`: trote.
- `N`: normal.
- `U`: urgente.

Regra real em `Ocorrencia::insert`:

- Se `tipo` é `N` ou `U`, cria registro em `ocorrencia`.
- Se `tipo` é qualquer outro valor, cria registro em `ligacoes` e não cria ocorrência operacional.
- A ocorrência criada recebe `status = 0` no controller, apesar do comentário dizer “empenhada”. Na prática esse status representa ocorrência aberta/aguardando despacho nas listagens atuais.
- A ocorrência recebe `talao` calculado por ano: busca `MAX(talao)` onde `year(data)` é o ano atual e incrementa; se não houver, inicia em 1.

Riscos:

- A geração do talão não é transacional e pode duplicar sob concorrência.
- `year(data)` é específico de MySQL/MariaDB.
- O payload inclui `paciente`, `latlng` e `area_protegida_id`, mas nem todos são persistidos pelo model atual.

## Status principal da ocorrência

Os status numéricos aparecem de forma inconsistente entre comentário antigo, listagens e fluxo atual:

- `0`: usado por `getAll()` como ocorrências disponíveis/abertas e por `listAtendimento()` como ocorrências em atendimento em algumas telas.
- `1`: usado em `Vitima::insert` como finalizada quando o número de vítimas registradas alcança `totalVitima`.
- `2`: usado em `getAllPendente()`/`getAllQta()` e em `empenhar()` como ocorrência empenhada/despachada.
- `3`: usado em `getAllEncerrada()` e em `liberar()` como encerrada.

Recomendação: substituir por enum explícito no Laravel/PostgreSQL, por exemplo `aberta`, `despachada`, `em_atendimento`, `encerrada`, `cancelada`, `qta`, mantendo mapeamento histórico em migration de dados.

## Despacho de viatura/equipe

Regra real em `/api/empenhar` (`Ocorrencia::empenhar`):

- Requer AJAX, JSON e campos `ocorrencia_id` e `viatura_id`.
- `viatura_id` precisa ser inteiro positivo.
- Busca um turno ativo com `final >= NOW()`.
- Cria `ocorrencia_has_turnos` com `status = empenhada`.
- Atualiza `turno.status = 2`, interpretado como empenhada/indisponível.
- Atualiza a ocorrência com `horaEmpenho = hora atual`, `status = 2` e `turno_id`.

Problema crítico encontrado:

- Em `empenhar()`, o turno é resolvido com `where('id', $viaturaId)` em vez de `where('viatura_id', $viaturaId)`. Isso diverge de `liberar()`, que usa `where('viatura_id', $viaturaId)`. O risco é empenhar ocorrência no turno errado quando ID da viatura e ID do turno não coincidirem.

## Disponibilidade de viatura/equipe

A disponibilidade operacional usada pelo despacho vem de `turno.status`, não diretamente de `viatura.status`.

- `Turno::getAll()` retorna apenas turnos com `final >= NOW()` e `turno.status = 1`.
- Ao empenhar, `turno.status` vira `2`.
- Ao liberar/encerrar, `turno.status` volta para `1`.
- Na criação de turno, a viatura só é listada se não existir em turno ainda ativo (`viatura.id NOT IN (SELECT DISTINCT viatura_id from turno where final >= NOW())`).

Regra por segmentação:

- Usuários com `users_type <= 2` veem viaturas/turnos de todos os contratos/municípios.
- Usuários com `users_type > 2` são filtrados por `contract_id` atual, futuro `municipio_id`.

## Etapas de atendimento no Kanban

Regra real em `/api/atualizar-status`:

- O ID recebido é `ocorrencia_has_turnos.id`.
- O status novo precisa pertencer à ordem fixa: `empenhada`, `qti`, `local`, `saidaLocal`, `us`, `saidaUs`.
- Se o status atual é conhecido:
  - não pode voltar etapa;
  - não pode pular etapa;
  - se for o mesmo status, retorna sucesso sem alteração.
- Ao atualizar `ocorrencia_has_turnos.status`, também grava horário correspondente em `ocorrencia`:
  - `empenhada` -> `horaEmpenho`.
  - `qti` -> `horaSaida`.
  - `local` -> `horaLocal`.
  - `saidaLocal` -> `horaSaidaLocal`.
  - `us` -> `horaHospital`.
  - `saidaUs` -> `horaSaidaHospital`.

Observação operacional:

- `qti` aparenta representar saída da base/viatura em deslocamento.
- `us` representa chegada à unidade de saúde.
- `saidaUs` é rotulada como “Liberada” no Kanban.

## Encerramento/liberação

Regra real em `/api/liberar`:

- Requer AJAX, JSON, `ocorrencia_id` e `viatura_id`.
- Resolve turno ativo da viatura com `final >= NOW()`.
- Busca a última etapa (`ocorrencia_has_turnos`) para ocorrência e turno.
- Só permite encerrar se a última etapa for exatamente `saidaUs`.
- Soft-delete do registro em `ocorrencia_has_turnos` remove a viatura do Kanban.
- Atualiza `turno.status = 1`.
- Atualiza `ocorrencia.status = 3`, `turno_id` e `horaBase = hora atual`.

Riscos:

- Não há transação envolvendo soft-delete do Kanban, atualização do turno e atualização da ocorrência.
- Não há lock para impedir dois operadores encerrando/despachando simultaneamente.
- O botão do frontend também bloqueia encerramento antes de `saidaUs`, mas a regra correta está no backend.

## Vítimas

Regras reais em `Vitima::insert`:

- Vítima é inserida a partir de formulário AJAX.
- `situacao = 3` representa recusa de atendimento.
- `situacao = 1` representa vítima atendida.
- Para vítima atendida, o sistema grava:
  - sinais vitais em `vitima_has_sinais`;
  - equipamentos/acessórios em `vitima_has_acessorio`;
  - procedimentos em `vitima_has_procedimento`;
  - ferimentos em `vitima_has_ferimento`.
- Após inserir vítima, conta vítimas da ocorrência e compara com `ocorrencia.totalVitima`.
- Se `count(vitimas) == totalVitima`, tenta marcar ocorrência como status `1`.

Problemas:

- `totalVitima` não aparece no payload novo de ocorrência e pode estar nulo/ausente.
- Atualização de status da ocorrência em atendimento usa `$this->ocorrenciaModel->save($dados)` sem `id`; pode não persistir corretamente.
- Não há transação envolvendo vítima e tabelas filhas.

## Prescrição e validação médica

Regras reais:

- Usuário com `users_type == 4` e vítima atendida (`situacao == 1`) vê botão de prescrição.
- A tela de prescrição busca medicamentos do estoque cujo material pertence à categoria `3`, filtrado por `contract_id`.
- Para identificar médico/equipe, busca efetivo do turno da ocorrência com `cargo = 2`.
- `savePrescricao()` cria prescrição e itens em `prescricao_has_medicamento`.
- `getAlertsPrescricao()` lista prescrições pendentes (`aceite = 0`) para o usuário logado vinculado ao efetivo médico (`cargo = 2`).
- `validar()` marca `aceite = 1` e baixa estoque (`estoque = estoque - quantidade`).

Riscos:

- Baixa de estoque não valida saldo.
- Não há transação entre aceite e baixa.
- Não há trilha formal de auditoria.

## Autenticação web

Regras reais:

- Login por `email` e `password` via AJAX em `Login::create`.
- `Auth::login()` busca usuário por email, valida `password_hash` via entity `User::verifyPassword`, exige `active` verdadeiro e grava sessão.
- Sessão grava `user_id` e `contract_id`.
- `LoginFilter` redireciona para `/login` se não houver usuário logado.
- CSRF global está ativo, exceto para `auth/signin` e `v1/ocorrencia/nova-chamada`.

Problemas:

- Turnstile/Cloudflare está comentado.
- `Login::create` acessa `is_client`, mas definição está comentada.

## Autenticação API/JWT

Regras reais:

- `Auth::Signin` recebe JSON `login` e `password`, autentica com a mesma library e retorna JWT.
- JWT expira em 300 segundos.
- `Auth::Me` decodifica JWT e retorna nome, email, `contract_id` como `office` e `active`.
- `AuthFilter` valida header `Authorization` e decodifica JWT.

Problemas:

- `AuthFilter` não retorna explicitamente as respostas nos casos `SignatureInvalidException` e exceção genérica.
- Não valida formato `Bearer token` antes de acessar `$arr[1]`.
- Não disponibiliza usuário autenticado ao controller.
- Grupo `v1` aplica `authFilter`, mas a configuração de `Filters` também tem um `before` parcial inconsistente.

## Permissões

Regras reais:

- Permissão é por rota textual de submenu.
- Controllers chamam `user_permission('rota')` manualmente.
- `Auth::getPermission` obtém usuário da sessão, consulta tipo de usuário e verifica `submenu_users_type`.
- Falha redireciona para `home` com mensagem.

Problemas:

- Autorização espalhada por controller.
- Algumas permissões estão comentadas em métodos críticos, como edição/controle de ocorrência e criação de vítima.
- Não existe policy central para regras operacionais críticas.

## Realtime e notificações

Regras reais:

- Ao criar ocorrência `N`/`U`, o backend faz POST para `WEBSOCKET_SERVER_URL/emergency` com payload da ocorrência.
- `App.vue` conecta via Socket.IO em `http://172.17.0.1:7001` e escuta `new-emergency` para recarregar ocorrências.
- `OcorrenciaForm.vue` conecta em `http://localhost:7001` e escuta `new-call` para abrir formulário com telefone e localização.
- Existe proxy WebSocket Traccar (`realtime/traccar-ws-proxy.js`) que faz login no Traccar e repassa `devices`, `positions` e `events`.

Problemas:

- URLs hardcoded e diferentes por componente.
- Realtime é usado principalmente para invalidar/recarregar dados, não como fonte de estado consistente.
- Não há fila, retry persistente ou outbox para eventos críticos.
