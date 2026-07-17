# Problemas Técnicos, Riscos e Melhorias

## Arquitetura e acoplamento

- Controllers concentram regras de negócio, persistência, integração externa, PDF, sessão e resposta HTTP.
- Não há camada de aplicação para casos de uso críticos.
- Models fazem joins complexos e também participam de regras de permissão/autenticação.
- Views PHP têm JavaScript inline, DataTables, AJAX e regras de fluxo.
- Vue 3 convive com jQuery/DataTables sem uma fronteira clara.
- Dependências são instanciadas com `new`, dificultando testes.

## Regras críticas sem transação

Operações que deveriam ser atômicas não usam transação:

- Empenhar ocorrência: cria vínculo, muda turno, muda ocorrência.
- Avançar etapa: atualiza Kanban e horário.
- Encerrar: soft-delete do vínculo, libera turno e encerra ocorrência.
- Inserir vítima: cria vítima e múltiplas tabelas filhas.
- Validar prescrição: marca aceite e baixa estoque.

Risco: em falha parcial, o estado operacional fica inconsistente.

## Concorrência

- Talão é gerado por `MAX(talao) + 1`, sem lock/unique constraint por ano.
- Viatura/turno pode ser empenhada simultaneamente por dois operadores se requisições competirem.
- Não há versionamento otimista ou lock pessimista para `turno` e `ocorrencia_has_turnos`.

## Bugs e inconsistências observadas

- `Ocorrencia::empenhar` usa `where('id', $viaturaId)` para resolver turno, mas deveria usar `viatura_id`.
- `OcorrenciaModel::$allowedFields` não inclui `area_protegida_id` nem `paciente`, apesar do payload usar esses campos.
- `Vitima::insert` tenta atualizar ocorrência com `$this->ocorrenciaModel->save($dados)` sem `id`.
- `Vitima::update` usa `insert($post)` em vez de update/save do registro existente.
- Migration `vitima_has_ferimento` cria FK `vitima_id` apontando para `turno.id`, incorreto.
- `Login::create` usa `$userLogin->is_client`, mas definição está comentada.
- `AuthFilter` não retorna response em todos os catches e assume `Authorization` com duas partes.
- Rota `ocorrencia/destroy` está como GET, mas controller lê POST.
- `VisitFilter` precisa ser revisado; seu uso em login/password e nova chamada pode estar conceitualmente invertido.
- `Config\Traccar` contém IP fixo no código.
- WebSocket usa URLs hardcoded diferentes (`localhost`, `172.17.0.1`, `websocket-server`).

## Banco de dados

- Migrations não cobrem todas as tabelas usadas por models (`emergency`, `emergency_has_teams`, `partes`, `permissions`, `users_departments` aparecem sem migration clara no conjunto analisado).
- Uso de `ENUM` MySQL em `ocorrencia_has_turnos`; PostgreSQL exige enum/check/domain ou tabela de status.
- Ausência de índices compostos em consultas críticas: `ocorrencia.status`, `turno.final/status`, `turno.viatura_id`, `ocorrencia_has_turnos.ocorrencia_id/turno_id/status/deleted_at`.
- Soft delete em tabelas críticas sem policies claras de retenção.
- Nomes de colunas misturam português, inglês, camelCase e snake_case.
- `contract_id` deve ser reinterpretado como `municipio_id`/base administrativa.

## Segurança

- Permissões são manuais e por string de rota.
- Algumas checagens críticas estão comentadas.
- CSRF é global, mas rotas internas `/api/*` não estão agrupadas formalmente sob auth web.
- JWT expira em 5 minutos, mas não há refresh ou escopo/roles.
- Possíveis segredos/endpoints expostos no código (`Config/Traccar`, comentários WhatsApp).
- Controllers `Migrate` e `Seed` em produção são risco alto.

## Realtime

- Eventos não usam outbox nem fila; se WebSocket falha, apenas loga warning.
- O frontend usa eventos para recarregar dados, não para aplicar estado de forma determinística.
- Não há autenticação/autorização clara no Socket.IO externo.
- Traccar WS proxy repassa dados para todos os clientes conectados sem filtro por município/base.

## Observabilidade/auditoria

- Não há trilha consistente de quem despachou, avançou etapa, liberou viatura ou validou prescrição.
- Logs parecem existir como telas, mas as operações críticas não geram audit log padronizado.
- Não há métricas de SLA/resposta, tempos por etapa, disponibilidade de equipe ou falhas de integração.

## Recomendações prioritárias

1. Corrigir bug de `empenhar()` e proteger com transação.
2. Criar constraints/índices para talão e disponibilidade de turno.
3. Normalizar status e etapas com enums/tabelas claras.
4. Criar audit log obrigatório para eventos operacionais.
5. Centralizar permissões em policies/abilities.
6. Migrar realtime para eventos de domínio + broadcast autenticado.
7. Remover URLs hardcoded e controllers administrativos perigosos.
8. Separar `contract_id` legado de `municipio_id` operacional.
