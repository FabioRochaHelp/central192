# Realtime, Eventos, WebSocket, Mapas e Notificações

## Realtime atual

O sistema usa realtime de forma híbrida:

- Socket.IO externo para chamadas/ocorrências.
- Vue recarrega listas ao receber eventos.
- Proxy Node `ws` para Traccar WebSocket.
- Polling/AJAX manual em DataTables e Vue.

Não há Laravel Echo/Reverb, Redis, filas, outbox ou garantias de entrega no sistema atual.

## Socket.IO da central

### Criação de ocorrência

Quando `Ocorrencia::insert` cria ocorrência `N` ou `U`, faz POST para:

- `WEBSOCKET_SERVER_URL/emergency`, ou fallback `http://websocket-server:7001/emergency`.

Payload:

- `data`: dados locais da ocorrência recém-criada.

Falha:

- Exceção é capturada e registrada como warning; a ocorrência permanece salva.

### Frontend CCO

`App.vue` conecta em `http://172.17.0.1:7001` e escuta:

- `new-emergency`: recarrega ocorrências.

### Frontend formulário de ocorrência

`OcorrenciaForm.vue` conecta em `http://localhost:7001` e escuta:

- `new-call`: recebe telefone e `latlng`, toca bip, localiza endereço/mapa e abre formulário.

Problema: URLs diferentes e hardcoded criam comportamento distinto entre host, Docker e produção.

## Polling/AJAX atual

O painel CCO chama sob demanda:

- `/api/viaturas` para viaturas/equipes disponíveis.
- `/api/ocorrencias` para ocorrências abertas.
- `/api/status-etapas` para Kanban.

Após cada ação (`empenhar`, `liberar`, `atualizar-status`), o frontend recarrega os três recursos. Isso reduz complexidade, mas aumenta latência e carga.

DataTables em views PHP usam AJAX para CRUDs e listagens operacionais.

## Traccar HTTP

`V1/Traccar` expõe proxy autenticado para:

- Health.
- Sessão.
- Devices.
- Positions.
- Events.
- Reports route/events/summary/trips/stops.

`TraccarClient` suporta:

- Basic auth por config/env.
- Bearer token.
- Login `/session` para cookie.
- Fallback de devices com sessão se Basic/Bearer retorna 401.

## Traccar WebSocket proxy

Arquivo: `realtime/traccar-ws-proxy.js`.

Função:

- Faz login em `POST /api/session` para obter `JSESSIONID`.
- Conecta no Traccar `ws://.../api/socket` com cookie.
- Repassa mensagens para clientes conectados.
- Envia status `connected/disconnected`.
- Reconecta com backoff exponencial até 30s.
- Health check em `/health`.

Mensagens Traccar esperadas:

- `devices`.
- `positions`.
- `events`.

Riscos:

- Proxy repassa tudo para todos; sem filtro por município/base, usuário ou viatura autorizada.
- Credenciais Traccar ficam em env do processo Node.
- Não persiste última posição localmente.

## Mapas

### Criação de ocorrência

`OcorrenciaForm.vue` usa Leaflet e OpenStreetMap:

- Recebe `latlng`.
- Cria mapa no modal.
- Adiciona marker draggable.
- Usa Nominatim reverse geocode para preencher `endereco`, `bairro`, `cidade`.
- Pode abrir Waze/Google Maps, embora botões não estejam destacados no template analisado.

### Rota da ocorrência

`Ocorrencia::rota` usa Traccar `reports/route`:

- Recebe `ocorrencia_id`.
- Busca detalhes da ocorrência com `device_id` da viatura.
- Exige `device_id`, `horaEmpenho` e `horaBase`.
- Usa data da ocorrência + horários para montar intervalo.
- Converte timezone local para UTC.
- Se não retorna pontos, tenta fallback interpretando horários como UTC sem shift.
- Retorna pontos e metadados para frontend.

`public/js/services/traccarRouteService.js` consome `/api/ocorrencia/rota` e plota polyline, marcador de início e fim no Leaflet.

## Eventos de domínio recomendados para Laravel

Eventos principais:

- `CallReceived`.
- `CallClassified`.
- `IncidentCreated`.
- `IncidentUpdated`.
- `UnitDispatched`.
- `DispatchStageAdvanced`.
- `UnitReleased`.
- `IncidentClosed`.
- `VictimRecorded`.
- `PrescriptionCreated`.
- `PrescriptionApproved`.
- `StockDecremented`.
- `VehiclePositionUpdated`.
- `TraccarDeviceLinked`.

Broadcasts recomendados com Reverb:

- Canal central: `operations.dispatch` para supervisores/central.
- Canal por município: `operations.municipio.{id}` para segmentação administrativa.
- Canal por ocorrência: `incidents.{id}` para detalhe/timeline.
- Canal por viatura: `vehicles.{id}` para localização/status.

## Filas e Redis

Usar Redis para:

- Filas de broadcast e notificações.
- Cache de dashboard operacional.
- Locks de despacho por turno/viatura.
- Rate limit de APIs externas.

Jobs recomendados:

- `NotifyDispatchBoard`.
- `SyncTraccarPositions`.
- `FetchIncidentRouteFromTraccar`.
- `SendProtectedAreaNotification`.
- `GenerateIncidentPdf`.
- `WriteAuditLog`.

## Outbox

Para sistema crítico, usar outbox transacional:

- Ao mudar estado no banco, gravar evento em `outbox_events` na mesma transação.
- Worker publica em Reverb/Redis/integrações externas.
- Garante que evento não se perde se WebSocket cair.

## Notificações

Código atual possui envio WhatsApp comentado para área protegida:

- Na criação de ocorrência em área protegida.
- Na finalização/atendimento de vítima.

Na migração, implementar como listener assíncrono com template auditado, opt-in por contato e retry.
