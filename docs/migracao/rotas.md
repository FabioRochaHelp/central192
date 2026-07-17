# Rotas, Filtros e Grupos

## Configuração geral

Arquivo principal: `app/Config/Routes.php`.

- Namespace padrão: `App\Controllers`.
- Controller padrão: `Home`.
- Método padrão: `index`.
- Auto route desativado (`setAutoRoute(false)`), ponto positivo para segurança.

## Filtros globais

Arquivo: `app/Config/Filters.php`.

- CSRF global ativo antes de requests.
- Exceções de CSRF: `auth/signin` e `v1/ocorrencia/nova-chamada`.
- Toolbar ativa após requests.

## Filtros nomeados

- `login`: `LoginFilter`, exige sessão web.
- `visit`: `VisitFilter`, usado em login/password e `v1/ocorrencia/nova-chamada`.
- `authFilter`: `AuthFilter`, valida JWT.

Rotas web protegidas pelo filtro `login`:

- `home`, `users`, `contract`, `menu`, `submenu`, `ocorrencia`, `acessorio`, `apoio`, `areaprotegida`, `efetivo`, `estoque`, `local`, `material`, `natureza`, `procedimento`, `tipovitima`, `turno`, `unidadeatendimento`, `viatura`, `vitima`.

Observação: rotas `/api/*`, `/control`, `/transport`, `/relatorios` e algumas rotas auxiliares não aparecem completamente na lista do filtro `login`, dependendo de sessão e checagens manuais nos controllers.

## Rotas públicas/web

Autenticação e sessão:

- `GET /login` -> `Login::index`.
- `POST /login/create` -> `Login::create`.
- `GET /logout` -> `Login::logout`.
- `GET /forgot` -> `Password::forgot`.
- `GET /login/messagelogout/{any}` -> mensagem pós logout.

Home:

- `GET /` e `/home` -> `Home::index`.
- `GET /calendar` -> `Home::calendar`.

Traccar health/teste:

- `GET /health` e `/traccar/health` -> `V1\Traccar::health` sem JWT.
- `GET /traccar/test` -> `TraccarTest::index`.

## Rotas CRUD principais

A maioria dos módulos segue padrão:

- `GET /modulo` -> index.
- `GET /modulo/getall` ou similar -> DataTables/AJAX.
- `GET /modulo/create` -> tela de criação.
- `POST /modulo/insert` -> criação.
- `GET /modulo/edit/{id}` -> edição.
- `POST /modulo/update` -> atualização.
- `POST /modulo/destroy` -> exclusão/soft delete.

Módulos com esse padrão: `acessorio`, `apoio`, `areaprotegida`, `contract`, `efetivo`, `estoque`, `local`, `material`, `menu`, `natureza`, `naturezatipo`, `procedimento`, `submenu`, `tipovitima`, `turno`, `unidadeatendimento`, `users`, `userstype`, `viatura`.

## Rotas de ocorrência

- `GET /ocorrencia` -> lista para encerramento/abertas.
- `GET /ocorrencia/getall` -> ocorrências status `0`.
- `GET /ocorrencia/getallpendente` -> ocorrências status `2`.
- `GET /ocorrencia/listatendimento` -> atendimento dashboard.
- `GET /ocorrencia/getallencerrada` -> ocorrências status `3`.
- `GET /ocorrencia/getallqta` -> status `2`, usada por QTA.
- `GET /ocorrencia/create` -> tela Vue de nova ocorrência.
- `POST /ocorrencia/insert` -> criação AJAX/form legado.
- `GET /ocorrencia/detalhe/{id}` -> detalhe e vítimas.
- `GET /ocorrencia/edit/{id}` -> edição.
- `GET /ocorrencia/pendente` -> pendentes.
- `GET /ocorrencia/controle` -> controle/atendimento.
- `GET /ocorrencia/encerrada` -> encerradas.
- `GET /ocorrencia/qta` -> sem atendimento.
- `POST /ocorrencia/update` -> atualização.
- `GET /ocorrencia/destroy` -> destroy, mas método lê POST; rota está inconsistente.
- `GET /ocorrencia/print/{id}` -> relatório PDF.

## Rotas do CCO/API interna Vue

- `GET /control` -> tela da central de despacho.
- `GET /control/getemergencies` -> lista emergency_has_teams legado.
- `GET /api/viaturas` -> `Turno::getAll`.
- `GET /api/ocorrencias` -> `Ocorrencia::getAll`.
- `POST /api/ocorrencia` -> `Ocorrencia::insert`.
- `POST /api/empenhar` -> `Ocorrencia::empenhar`.
- `POST /api/liberar` -> `Ocorrencia::liberar`.
- `POST /api/ocorrencia/rota` -> `Ocorrencia::rota`.
- `GET /api/csrf-token` -> `Csrf::getToken`.
- `GET /api/status-etapas` -> `Ocorrencia::etapas`.
- `POST /api/atualizar-status` -> `Ocorrencia::atualizarStatus`.

Essas rotas dependem de CSRF/AJAX e sessão web, mas não estão agrupadas formalmente por middleware de autenticação no arquivo de rotas.

## Rotas de vítima

- `GET /vitima/create/{ocorrencia}`.
- `GET /vitima/recusa/{id}`.
- `GET /vitima/obito`.
- `GET /vitima/atendida/{id}`.
- `POST /vitima/insert`.
- `GET /vitima/edit/{id}`.
- `GET /vitima/prescricao/{id}`.
- `GET /vitima/validacao/{id}`.
- `POST /vitima/saveprescricao`.
- `GET /vitima/getalertsprescricao`.
- `POST /vitima/validade`.
- `POST /vitima/validar`.
- `POST /vitima/update`.

## Rotas de autenticação API

- `POST /auth/signin` -> retorna JWT.
- `GET /auth/me` -> decodifica JWT e retorna usuário.

## Grupo `v1` sem JWT, com `visit`

- `POST /v1/ocorrencia/nova-chamada` -> recebe nova chamada e repassa para WebSocket.

## Grupo `v1` com `authFilter`

- `GET /v1/acessorio`.
- `POST /v1/acessorio/criar`.
- `GET /v1/ocorrencia/listar/{id}`.
- Traccar proxy:
  - `GET /v1/traccar/health`.
  - `POST /v1/traccar/session`.
  - `POST /v1/traccar/session/basic-test`.
  - `GET /v1/traccar/session`.
  - `DELETE /v1/traccar/session`.
  - `GET /v1/traccar/devices`.
  - `GET /v1/traccar/positions`.
  - `GET /v1/traccar/events`.
  - `GET /v1/traccar/reports/route`.
  - `GET /v1/traccar/reports/events`.
  - `GET /v1/traccar/reports/summary`.
  - `GET /v1/traccar/reports/trips`.
  - `GET /v1/traccar/reports/stops`.

## Recomendações para Laravel

- Criar grupos `web`, `auth`, `verified/active`, `can:*`.
- Separar API interna Livewire/web de API externa autenticada por Sanctum/Passport/JWT conforme necessidade.
- Agrupar rotas de despacho sob middleware transacional/autorização específica.
- Substituir permissões por rota textual por abilities como `dispatch.view`, `dispatch.assign_unit`, `incident.close`, `victim.prescribe`.
- Remover controllers de `Migrate` e `Seed` do ambiente produtivo.
