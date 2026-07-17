# Descricao das Views de Vitimas

## Objetivo

Este documento descreve as views atuais da funcionalidade de vitimas em `app/Views/Vitima`, com foco no papel de cada tela/parcial no fluxo operacional e no destino recomendado para a migracao para Laravel 12 + Livewire.

## Visao geral do fluxo atual

O fluxo principal de vitima parte do detalhe de uma ocorrencia. O operador acessa a criacao de vitima, informa dados basicos e escolhe a situacao:

- `Atendida`: carrega um formulario clinico completo com sinais vitais, ferimentos, procedimentos, equipamentos, transporte e unidade de saude.
- `Recusa`: carrega campos de testemunha.
- `Obito`: aparece em alguns pontos do JavaScript, mas a view atual nao implementa um fluxo consistente.

As views usam PHP/CodeIgniter, formularios HTML, jQuery, AJAX, tabelas dinamicas e validacao manual no frontend. Na migracao, o fluxo deve ser consolidado em componentes Livewire com estado reativo e validacao no servidor.

## `Vitima/_form.php`

### Papel atual

Parcial base do formulario de vitima. E incluida principalmente em `Vitima/create.php`.

### Campos exibidos

- `name`: nome da vitima.
- `rg`: documento RG.
- `ssp`: orgao/UF emissor.
- `idade`: idade.
- `sexo`: selecao numerica com feminino e masculino.
- `situacao`: radio para `Atendida` ou `Recusa`.

### Comportamento

A view apenas define os campos principais e tres areas vazias:

- `div-recusa`
- `div-obito`
- `div-atendida`

Essas areas sao preenchidas via jQuery em `create.php`, conforme a situacao selecionada.

### Destino na migracao

Deve virar a secao inicial do componente Livewire `Victims\CreateVictim`, com estado `situation`.

Recomendacao:

- Substituir `sexo` numerico por enum.
- Substituir `situacao` numerica por enum `VictimSituation`.
- Eliminar carregamento parcial por AJAX; usar renderizacao condicional do Livewire.

## `Vitima/create.php`

### Papel atual

Tela principal de cadastro de vitima vinculada a uma ocorrencia.

### Dados recebidos

- `$vitima`: instancia/model vazio.
- `$ocorrencia`: ocorrencia atual.
- `ocorrencia_id`: campo hidden.
- `contract_id`: campo hidden obtido da ocorrencia/turno.

### Comportamento

Inclui `Vitima/_form.php` e registra scripts jQuery para:

- Carregar `Vitima/recusa.php` quando `situacao = 3`.
- Carregar `Vitima/obito.php` quando `situacao = 2`, embora essa opcao nao apareca no `_form.php`.
- Carregar `Vitima/atendida.php` quando a situacao nao e recusa/obito.
- Adicionar linhas de sinais vitais em tabela HTML.
- Adicionar medicamentos/material, embora esse trecho esteja mais relacionado a prescricao.
- Enviar o formulario via AJAX para `vitima/insert`.

### Resultado esperado

Ao salvar:

- Se a resposta indica que ainda ha vitimas pendentes, redireciona para `ocorrencia/detalhe/{id}`.
- Se a resposta indica status final, redireciona para `ocorrencia/pendente`.

### Destino na migracao

Substituir por componente Livewire `Victims\CreateVictim`.

Responsabilidades do componente:

- Receber `Incident $incident`.
- Controlar dados basicos da vitima.
- Controlar situacao.
- Controlar colecoes dinamicas de sinais, procedimentos, acessorios e ferimentos.
- Salvar por Action transacional.
- Redirecionar para detalhe da ocorrencia.

## `Vitima/atendida.php`

### Papel atual

Parcial carregada quando a vitima e marcada como atendida. E a view mais importante do modulo.

### Secoes funcionais

#### Sinais vitais

Permite informar:

- Hora.
- Pressao sistolica.
- Pressao diastolica.
- FC.
- FR.
- Saturacao.
- Temperatura.
- Destro.
- RO.
- RV.
- RM.
- Glasgow calculado por JavaScript como `RO + RV + RM`.

Os sinais sao adicionados em uma tabela dinamica e enviados como arrays:

- `sinais[hora][]`
- `sinais[paSistolica][]`
- `sinais[paDiastolica][]`
- `sinais[fc][]`
- `sinais[fr][]`
- `sinais[saturacao][]`
- `sinais[temperatura][]`
- `sinais[destro][]`
- `sinais[ro][]`
- `sinais[rv][]`
- `sinais[rm][]`
- `sinais[glasgow][]`

#### Matriz de ferimentos

Mostra uma matriz com tipos de lesao por regioes do corpo:

- Cranio.
- Face.
- Pescoco.
- Torax.
- Dorso.
- Abdominal.
- Membro superior direito.
- Membro superior esquerdo.
- Membro inferior direito.
- Membro inferior esquerdo.

Tipos exibidos:

- Contusao.
- Corte.
- Escoriacao.
- Perfurante.
- Corte contuso.
- Laceracao/esmagamento.
- Amputacao/avulsao.
- Fratura aberta.
- Fratura fechada.

Cada checkbox envia `lesao[]` com um ID de `local_ferimento`.

Observacao: existem valores duplicados ou inconsistentes na matriz atual. A migracao deve revisar os seeds/catalogos.

#### Pupilas

Campos:

- `pupilaReacao`: presente/ausente.
- `pupilaSimetria`: isocoricas/anisocoricas.
- `pupilaTamanho`: miotica/midriatica.
- `pupilaLado`: direito/esquerdo.

Comportamento:

- Se a reacao a luz e ausente, exibe painel de pupilas.
- Se a simetria e anisocorica, exibe lado.

#### Avaliacao complementar

Campos:

- `halitoEtilico`: sim, nao, nao foi possivel.
- `queimadura`: percentual.
- `queda`: altura em metros, embora o model use `quedaAltura`; ha divergencia de nome.

#### Classificacao e cena

Campos:

- `vitimaTipo`: classificacao da vitima.
- `local`: cena/local onde a vitima foi encontrada.

Observacao: o model espera `vitimatipoCodigo` e `localCodigo`, mas a view envia `vitimaTipo` e `local`. A migracao deve normalizar esses nomes.

#### Veiculo e acidente

Campos:

- `veiculoOcupava`: automovel, motocicleta, caminhao, bicicleta, tracao animal, a pe.
- `tipoAcidente`: capotamento, tombamento, colisao frontal, colisao lateral, colisao traseira, choque, atropelamento, queda.

Observacao: ha um input com nome errado `radtipoAcidenteio2`; deve ser corrigido.

#### Equipamentos/acessorios

Lista `acessorio` e envia:

- `equipamento[]`

No backend, cada item vira registro em `vitima_has_acessorio`.

#### Procedimentos

Lista `procedimento` e envia:

- `procedimento[]`

No backend, cada item vira registro em `vitima_has_procedimento`.

#### Transporte e unidade de saude

Campos:

- `transporte`: UR, SAME, outros.
- `unidadeSaude`: lista fixa de unidades.
- `medicoUS`: medico da unidade de saude.
- `crmMedicoUS`: CRM.

Recomendacao: substituir lista fixa por tabela `health_units`.

#### Obito

Campo:

- `obitoOnde`: no local ou unidade de saude.

Observacao: existe campo `obitoParecer` no model/migration, mas a view nao mostra uma captura clara dessa informacao.

#### Dados complementares

Campo:

- `dadosComplementares`.

### Destino na migracao

Criar subcomponente ou secao Livewire `Victims\ClinicalAttendanceForm`.

Recomendacoes:

- Transformar sinais vitais em array Livewire com adicionar/remover.
- Calcular Glasgow no backend ou em computed property validada.
- Normalizar ferimentos em `injury_type` + `body_region`, se possivel.
- Validar todos os IDs enviados.
- Eliminar jQuery e HTML construido por string.

## `Vitima/recusa.php`

### Papel atual

Parcial carregada quando a vitima recusa atendimento.

### Campos

- `testemunha`
- `rgTestemunha`
- `sspTestemunha`

### Comportamento

O formulario principal continua sendo salvo por `Vitima/create.php`. Esses campos sao anexados ao mesmo POST de `vitima/insert`.

### Destino na migracao

Virar secao condicional no componente `Victims\CreateVictim`.

Recomendacao:

- Definir se testemunha e obrigatoria para recusa.
- Registrar evento `VictimRefusalRecorded`.

## `Vitima/obito.php`

### Papel atual

View parcial incompleta/legada. Apesar do nome, repete estrutura parecida com `_form.php` e nao implementa dados especificos de obito.

### Problemas

- Usa campos com nomes divergentes (`nome` em vez de `name`).
- IDs dos containers usam camelCase diferente de `_form.php`.
- Nao ha integracao consistente com o controller; `Vitima::obito()` retorna `Vitima/recusa`.

### Destino na migracao

Nao migrar diretamente.

Recomendacao:

- Criar fluxo real de obito somente se a regra operacional for confirmada.
- Se mantido, adicionar situacao `death` e campos especificos de obito no componente principal.

## `Vitima/prescricao.php`

### Papel atual

Tela de criacao de prescricao medica para uma vitima.

### Dados exibidos

- Nome da vitima.
- RG.
- Endereco da ocorrencia.
- Talao da ocorrencia.
- Lista de medicamentos disponiveis.
- Lista de medicamentos ja vinculados a prescricoes da vitima.

### Campos enviados

- `vitima_id`
- `efetivo_id`
- `prescrita_por_id`
- `descricao`
- `material[codigo][]`
- `material[qtdade][]`

### Comportamento

O usuario adiciona medicamentos em uma tabela dinamica via jQuery. Ao salvar, envia AJAX para `vitima/saveprescricao`.

No backend:

- Cria registro em `vitima_has_prescricao`.
- Cria itens em `prescricao_has_medicamento`.

### Destino na migracao

Substituir por Livewire `Victims\PrescriptionForm`.

Recomendacoes:

- Usar `Prescription` e `PrescriptionItem`.
- Remover arrays HTML manuais.
- Validar estoque e quantidade.
- Nao baixar estoque na criacao; baixar apenas na aprovacao.

## `Vitima/validar.php`

### Papel atual

Tela de validacao/aprovacao de prescricao.

### Dados exibidos

- Nome da vitima.
- RG.
- Endereco da ocorrencia.
- Talao.
- Medicamentos prescritos.
- Quantidades.
- Estoque atual.
- Nome do usuario que prescreveu.

### Comportamento

O botao de validacao abre SweetAlert e envia POST para `vitima/validar`.

No backend:

- Busca prescricao.
- Define `aceite = 1`.
- Para cada item, decrementa `estoque.estoque` com SQL aritmetico.
- Redireciona para `home`.

### Destino na migracao

Substituir por Livewire `Victims\PrescriptionApproval`.

Recomendacoes:

- Aprovar em transacao.
- Validar saldo antes de aprovar.
- Registrar movimento de estoque.
- Registrar usuario aprovador e `approved_at`.
- Redirecionar para painel de prescricoes pendentes ou detalhe da ocorrencia, nao genericamente para `home`.

## `Vitima/detalhe.php`

### Papel atual

View antiga de detalhe da ocorrencia com acesso a vitimas. Exibe dados da ocorrencia e botoes de vitima, mas nao apresenta o detalhe clinico completo da vitima.

### Problemas

- Botao "Imprimir Relatorio" aponta para `vitima/create`.
- Exibe botoes fixos "01 - Vitima" e "02 - Vitima".
- Script final envia para `ocorrencia/update`, nao para vitima.

### Destino na migracao

Nao migrar diretamente.

Recomendacao:

- O detalhe de vitimas deve fazer parte de `IncidentDetail` ou `VictimShow`.
- Listar vitimas reais da ocorrencia com situacao, prescricoes e acoes permitidas.

## `Vitima/edit.php`

### Papel atual

Suposta tela de edicao de vitima.

### Problemas

- Inclui `Ocorrencia/_form`.
- POST vai para `ocorrencia/update`.
- Titulo e variaveis indicam ocorrencia, nao vitima.

### Destino na migracao

Descartar como base funcional.

Recomendacao:

- Criar edicao real em Livewire `Victims\EditVictim`, reaproveitando o estado de `CreateVictim`.

## `Vitima/finaliza.php`

### Papel atual

Wizard experimental usando `jquery.steps`.

### Problemas

- Campos ficticios em ingles.
- Formulario envia para `ocorrencia/insert`.
- Nao representa o dominio atual de vitimas.

### Destino na migracao

Nao migrar.

Se houver necessidade de wizard, criar fluxo novo em Livewire/Flux UI com etapas reais:

- Dados basicos.
- Situacao.
- Atendimento clinico.
- Procedimentos/ferimentos.
- Revisao e confirmacao.

## `Vitima/index.php`

### Papel atual

Apesar de estar na pasta `Vitima`, a view lista viaturas (`viatura/getall`) e exibe colunas base, placa, prefixo, modelo, ano, status.

### Problemas

- Conteudo pertence a `Viatura`, nao `Vitima`.
- Botao "Criar nova viatura".
- Form de destroy aponta para `viatura/destroy`.

### Destino na migracao

Nao migrar no modulo de vitimas.

## `Vitima/pendente.php`

### Papel atual

Lista ocorrencias usando `ocorrencia/getall`.

### Problemas

- Conteudo pertence a ocorrencias pendentes, nao vitimas.
- Form de destroy aponta para `ocorrencia/destroy`.

### Destino na migracao

Nao migrar no modulo de vitimas.

Se necessario, essa funcionalidade deve ir para `Incidents\PendingIncidents`.

## `Vitima/print.php`

### Papel atual

Arquivo de geracao de PDF via TCPDF, mas o conteudo usa variaveis de processo administrativo:

- `$procAdm`
- `$procAdmNote`
- `$procAdmMove`
- `$procAdmDocs`
- `$parecer_description`

### Problemas

- Nao usa dados de vitima.
- Cabecalho e conteudo sao de outro dominio.
- Gera arquivo `resources/procadm/archived.pdf`.

### Destino na migracao

Nao migrar como view de vitima.

Se houver relatorio de vitima, criar novo template especifico:

- Dados da ocorrencia.
- Dados da vitima.
- Sinais vitais.
- Procedimentos.
- Ferimentos.
- Transporte/unidade de saude.
- Prescricoes.

## `Vitima/printnow.php`

### Papel atual

View de impressao de processo administrativo, tambem usando `procAdm`.

### Problemas

- Nao representa vitima.
- Conteudo e layout pertencem a outro modulo.

### Destino na migracao

Nao migrar no modulo de vitimas.

## Consolidacao para Livewire

### Views que viram componente principal

- `_form.php`
- `create.php`
- `atendida.php`
- `recusa.php`

Componente alvo:

- `Victims\CreateVictim`

### Views que viram componentes de prescricao

- `prescricao.php`
- `validar.php`

Componentes alvo:

- `Victims\PrescriptionForm`
- `Victims\PrescriptionApproval`
- `Victims\PendingPrescriptionAlerts`

### Views a descartar ou revisar

- `obito.php`
- `detalhe.php`
- `edit.php`
- `finaliza.php`
- `index.php`
- `pendente.php`
- `print.php`
- `printnow.php`

## Recomendacao de estrutura de Blade/Livewire

Arquivos sugeridos:

- `resources/views/livewire/victims/create-victim.blade.php`
- `resources/views/livewire/victims/partials/basic-data.blade.php`
- `resources/views/livewire/victims/partials/clinical-attendance.blade.php`
- `resources/views/livewire/victims/partials/refusal.blade.php`
- `resources/views/livewire/victims/partials/vital-signs.blade.php`
- `resources/views/livewire/victims/partials/injuries-grid.blade.php`
- `resources/views/livewire/victims/prescription-form.blade.php`
- `resources/views/livewire/victims/prescription-approval.blade.php`

## Pontos que exigem decisao de produto

- Obito deve ser uma situacao independente da vitima ou um campo dentro de vitima atendida?
- Testemunha e obrigatoria em toda recusa?
- `totalVitima` ainda sera usado para fechar parte clinica da ocorrencia?
- Matriz de ferimentos deve ser preservada como IDs atuais ou normalizada em tipo de lesao + regiao corporal?
- Unidades de saude devem sair de lista fixa para cadastro `health_units`?
- Prescricao pode ser criada por qualquer medico do turno ou apenas pelo usuario vinculado ao efetivo medico?

## Resultado esperado da migracao das views

A migracao deve transformar varias views parciais, scripts jQuery e HTML dinamico em um fluxo Livewire unico, validado no servidor, transacional e auditavel, preservando a operacao da central e eliminando views legadas que pertencem a outros modulos.
