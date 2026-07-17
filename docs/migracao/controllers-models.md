# Controllers e Models

## Padrão arquitetural atual

A aplicação usa controllers CodeIgniter como camada de apresentação e, em muitos casos, como camada de aplicação/domínio. Os models são `CodeIgniter\Model` com `allowedFields`, joins manuais e algumas validações/callbacks. Não há services/actions para orquestração de regras críticas.

## Controllers mapeados

- `Acessorio`: CRUD de acessórios/equipamentos.
- `Apoio`: CRUD de apoios.
- `AreaProtegida`: CRUD de áreas protegidas, contatos e consulta de CEP.
- `Auth`: login API/JWT e `me`.
- `BaseController`: controller base.
- `Contract`: cadastro de contrato/base administrativa atual.
- `Control`: tela CCO/despacho e endpoint `getEmergencies` legado.
- `Csrf`: endpoint de token CSRF para Vue/Axios.
- `Efetivo`: CRUD e busca de efetivo por cargo.
- `Emergency`: cadastro legado/alternativo de emergência.
- `Estoque`: estoque e visão geral.
- `Home`: dashboard/calendário e listagens iniciais.
- `Local`: cadastro local.
- `Login`: autenticação web por sessão.
- `Logs`: telas de logs/registro.
- `Material`: cadastro e consulta por categoria.
- `Menu`: CRUD e relacionamento de menus com tipos de usuário.
- `Migrate`: execução de migrations via controller, risco operacional.
- `Natureza`: CRUD de naturezas.
- `Naturezatipo`: CRUD de tipos de natureza.
- `Ocorrencia`: controller crítico de ocorrência, despacho, etapas, encerramento, rota Traccar e relatório.
- `Password`: recuperação/reset de senha.
- `Persons`: cadastro de partes/pessoas.
- `Procedimento`: CRUD de procedimentos.
- `Relatorio`: entrada de relatórios.
- `Seed`: execução de seeders via controller, risco operacional.
- `Submenu`: CRUD e relacionamento de submenus.
- `Tipovitima`: CRUD de tipos de vítima.
- `TraccarTest`: tela de teste Traccar.
- `Transport`: transporte agendado.
- `Turno`: CRUD de turnos, disponibilidade e efetivo do turno.
- `UnidadeAtendimento`: CRUD de unidades.
- `Users`: usuários, perfil, avatar, vínculo com efetivo/contrato.
- `Userstype`: tipos de usuário.
- `Viatura`: CRUD de viaturas e seleção de device Traccar.
- `Viaturacheckup`: checklist/inspeção de viatura.
- `Vitima`: vítima, sinais, procedimentos, prescrição e validação.
- `Whatsapp`: integração/mensagens, aparentemente auxiliar.
- `V1/Acessorio`: API v1 de acessórios.
- `V1/Clients`: API v1 de clientes.
- `V1/Ocorrencia`: API v1 de ocorrência/nova chamada.
- `V1/Traccar`: proxy HTTP autenticado para Traccar.

## Models mapeados

- `AcessorioModel`: `acessorio`, soft delete, timestamps.
- `ApoioModel`: `apoio`, soft delete, timestamps.
- `AreaProtegidaModel`: `area_protegida`, soft delete, timestamps.
- `AreaProtegidaHasContatoModel`: `area_protegida_has_contato`.
- `ContractModel`: `contract`, soft delete, entity `Contracts`.
- `EfetivoModel`: `efetivo`, soft delete, busca por cargo excluindo efetivo já em turno ativo.
- `EmergencyModel`: `emergency`, soft delete, entity `EntityEmergency`.
- `EmergencyHasTeamsModel`: `emergency_has_teams`, contém consulta `getEmergenciesTeams`.
- `EstoqueModel`: `estoque`, soft delete.
- `EstoqueLancamentoModel`: `estoque_lancamento`, soft delete.
- `LigacoesModel`: `ligacoes`, soft delete.
- `LocalModel`: `local`, soft delete.
- `MaterialModel`: `material`, soft delete, consulta por categoria/estoque.
- `MenuModel`: `menu`.
- `MenuUsersTypeModel`: `menu_users_type`.
- `NaturezaModel`: `natureza`, soft delete.
- `NaturezaTipoModel`: `natureza_tipo`, soft delete.
- `OcorrenciaModel`: `ocorrencia`, detalhes por joins com turno/viatura/natureza.
- `OcorrenciaHasTurnoModel`: `ocorrencia_has_turnos`, soft delete e constante de status.
- `PermissionsModel`: `permissions`, legado/incompleto.
- `PersonsModel`: `partes`, entity `EntityPersons`.
- `PrescricaoHasMedicamentoModel`: `prescricao_has_medicamento`.
- `ProcedimentoModel`: `procedimento`, soft delete.
- `SubmenuModel`: `submenu`.
- `SubmenuUsersTypeModel`: `submenu_users_type`, usado para `permissionFor`.
- `TransportScheduleModel`: `transport_schedules`, soft delete.
- `TurnoModel`: `turno`.
- `TurnoHasEfetivoModel`: `turno_has_efetivo`.
- `UnidadeAtendimentoModel`: `unidade_atendimento`, soft delete.
- `UsersDepartmentsModel`: `users_departments`, sem migration observada.
- `UsersModel`: `users`, autenticação, menus, submenus e hash de senha.
- `UsersTypeModel`: `users_type`.
- `ViaturaCheckupModel`: `viatura_checkups`.
- `ViaturaModel`: `viatura`, soft delete, `device_id` Traccar.
- `VitimaHasPrescricaoModel`: `vitima_has_prescricao`.
- `VitimaModel`: `vitima`, soft delete.
- `VitimaTipoModel`: `vitima_tipo`, soft delete.

## Acoplamentos importantes

- Quase todos os controllers constroem `UsersModel` para carregar menu/submenu no construtor.
- Controllers fazem `new Model()` diretamente, sem injeção de dependência.
- Regras críticas estão duplicadas entre backend e Vue: status, labels, bloqueios de encerramento e recarga de dados.
- Views PHP têm JavaScript inline com URLs e regras de interface.
- `Ocorrencia` conhece Traccar, WebSocket, Dompdf, models de vítima, usuário e turno.
- `Vitima` conhece estoque, material, prescrição, ocorrência, efetivo, turno e usuários.
- `Viatura` conhece Traccar para listar devices durante create/edit.
- `Auth` mistura autenticação web e base para JWT via mesma sessão/library.

## Pontos de atenção para migração

- Extrair `DispatchOccurrenceAction`, `AdvanceDispatchStageAction`, `ReleaseUnitAction`, `CreateOccurrenceFromCallAction` e `RecordVictimAttendanceAction`.
- Substituir `user_permission('rota')` por policies/gates baseados em abilities.
- Criar DTOs/FormRequests para payloads JSON e formulários.
- Mover integrações Traccar/WebSocket para services isolados.
- Criar events/listeners para timeline, broadcasts e notificações.
