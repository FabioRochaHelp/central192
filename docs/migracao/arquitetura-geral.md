# Arquitetura Geral do Sistema Atual

## Visão geral

O projeto atual é uma aplicação CodeIgniter 4 para operação de central de emergência/urgência. O sistema combina CRUD administrativo, operação de ocorrências, despacho de viaturas, controle de equipes em turno, cadastro clínico de vítimas, prescrição/validação médica, integração com rastreamento Traccar, mapas Leaflet/OpenStreetMap e atualizações em tempo real via Socket.IO/WebSocket externo.

A aplicação não está organizada como DDD. O domínio está concentrado principalmente em controllers, models Active Record do CodeIgniter, views PHP com JavaScript inline e componentes Vue 3 em `public/app`. Não existe camada de services/actions para regras críticas. As regras de despacho, transição de etapas, encerramento, autenticação e permissões ficam espalhadas em `Ocorrencia`, `Vitima`, `Turno`, `Viatura`, `Auth`, `Login`, `UsersModel`, filtros e componentes Vue.

## Objetivo operacional

O objetivo operacional real é apoiar uma central centralizada de atendimento, com segmentações administrativas por município/base. No código atual essa segmentação aparece majoritariamente como `contract_id`, herdado de uma modelagem de contrato/base. Na migração deve ser rebatizado e normalizado para `municipio_id`, preservando a ideia de operação centralizada: o município não é um tenant SaaS isolado, mas uma partição administrativa dentro da central.

A operação principal é:

- Receber ligação/chamada ou registrar ocorrência manualmente.
- Classificar a chamada por tipo (`C`, `A`, `T`, `N`, `U`).
- Criar ocorrência operacional para chamadas normais/urgentes.
- Despachar uma viatura/equipe em turno ativo.
- Acompanhar a equipe em etapas progressivas no Kanban.
- Registrar horários operacionais da ocorrência.
- Registrar vítimas, sinais, procedimentos, ferimentos, acessórios e prescrições.
- Encerrar ocorrência apenas após a última etapa operacional liberada.
- Consultar rota Traccar quando a viatura possui `device_id` e a ocorrência possui intervalo completo.

## Módulos existentes

- Autenticação web por sessão: `Login`, `Auth` library, `LoginFilter`, helpers `user_logged()` e `user_permission()`.
- Autenticação API por JWT: `Auth::Signin`, `Auth::Me`, `AuthFilter`.
- Menu/permissões por tipo de usuário: `menu`, `submenu`, `menu_users_type`, `submenu_users_type`.
- Ocorrências: `Ocorrencia`, `OcorrenciaModel`, `OcorrenciaHasTurnoModel`, views `Ocorrencia/*`, Vue `OcorrenciaForm.vue` e `App.vue`.
- Despacho/CCO: `Control`, `public/app/components/App.vue`, endpoints `/api/*`.
- Viaturas: `Viatura`, `ViaturaModel`, vínculo opcional com Traccar por `device_id`.
- Turnos/equipes: `Turno`, `TurnoModel`, `TurnoHasEfetivoModel`, `EfetivoModel`.
- Vítimas e atendimento clínico: `Vitima`, `VitimaModel`, tabelas auxiliares `vitima_has_*`.
- Prescrição e estoque: `VitimaHasPrescricaoModel`, `PrescricaoHasMedicamentoModel`, `EstoqueModel`, `MaterialModel`.
- Naturezas, tipos, locais, procedimentos, acessórios e apoio: cadastros de domínio operacional.
- Área protegida: `AreaProtegida`, contatos e notificação WhatsApp comentada.
- Traccar: `Config/Traccar.php`, `TraccarClient`, `V1/Traccar`, `realtime/traccar-ws-proxy.js`, view de teste `Traccar/test.php`.
- Relatórios/impressão: `Dompdf` em `Ocorrencia::print`.
- Transporte agendado: `Transport`, `TransportScheduleModel`.
- Checkup de viatura: `ViaturaCheckup`, `ViaturaCheckupModel`.

## Fluxo principal atual

1. Usuário acessa a aplicação web e autentica via `Login::create`.
2. A sessão armazena `user_id` e `contract_id`.
3. O menu e os submenus são carregados a partir do tipo do usuário.
4. A central abre `control`, renderizando o Vue `App.vue`.
5. O painel carrega viaturas disponíveis em `/api/viaturas`, ocorrências em `/api/ocorrencias` e etapas em `/api/status-etapas`.
6. A criação de ocorrência acontece em `/ocorrencia/create` com componente `OcorrenciaForm.vue`, que salva em `/api/ocorrencia`.
7. Se o tipo é `N` ou `U`, `Ocorrencia::insert` cria a ocorrência e notifica `WEBSOCKET_SERVER_URL/emergency`; se é outro tipo, salva em `ligacoes`.
8. O despacho ocorre via `/api/empenhar`, criando `ocorrencia_has_turnos`, mudando `turno.status` para 2 e `ocorrencia.status` para 2.
9. O Kanban avança por `/api/atualizar-status`, exigindo progressão sequencial: `empenhada`, `qti`, `local`, `saidaLocal`, `us`, `saidaUs`.
10. Cada etapa grava horários na ocorrência (`horaEmpenho`, `horaSaida`, `horaLocal`, `horaSaidaLocal`, `horaHospital`, `horaSaidaHospital`).
11. O encerramento via `/api/liberar` só é permitido quando a última etapa é `saidaUs`; o vínculo de Kanban é soft-deleted, a ocorrência vai para status 3 e o turno volta a status 1.

## Pontos críticos

- Regras de negócio críticas estão nos controllers e no Vue, sem transações explícitas.
- Existem dois modelos de ocorrência: `ocorrencia` operacional e `emergency` legado/alternativo, sem integração clara.
- `contract_id` representa segmentação administrativa, mas a migração deve adotar `municipio_id` sem tratar o sistema como SaaS multi-tenant.
- `AuthFilter` decodifica JWT, mas não retorna a resposta em alguns blocos de erro e não injeta usuário autenticado na request.
- `Login::create` usa `$userLogin->is_client`, porém a propriedade está comentada na library `Auth`, risco de propriedade indefinida.
- `Ocorrencia::empenhar` valida `viatura_id`, mas busca turno por `where('id', $viaturaId)` em vez de `where('viatura_id', $viaturaId)`, enquanto `liberar` usa `viatura_id`. Isso pode causar despacho para turno incorreto.
- `Ocorrencia::insert` usa `paciente`, `area_protegida_id` e `referencia` no payload/frontend, mas `OcorrenciaModel::$allowedFields` não inclui `paciente` nem `area_protegida_id` e a criação usa `protect(true)`, logo campos podem ser descartados.
- `Vitima::insert` atualiza ocorrência com `$this->ocorrenciaModel->save($dados)` sem garantir `id`, podendo não atualizar o registro correto.
- Transições de status e liberação de viatura não estão protegidas por transação ou lock concorrente.
- O realtime depende de serviços externos hardcoded (`localhost:7001`, `172.17.0.1:7001`, `websocket-server:7001`) e não há fallback consistente.
- Traccar usa URL/IP configurado diretamente em `Config/Traccar.php`, com credenciais por env parcialmente usadas.
- A base de dados tem FKs inconsistentes, enum MySQL, campos duplicados e migrations que não refletem todos os models.

## Stack atual observada

- PHP CodeIgniter 4.
- MySQL/MariaDB presumido pelas migrations e funções SQL (`NOW()`, `year(data)`, `ENUM`).
- jQuery/DataTables em views PHP.
- Vue 3 compilado por Laravel Mix em `public/app` para central de despacho.
- Socket.IO client para evento `new-emergency` e `new-call`.
- Node `ws` para proxy Traccar.
- Leaflet/OpenStreetMap/Nominatim para mapas/geocodificação.
- Dompdf/TCPDF para relatórios.
