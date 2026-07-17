# Cicatriz de incêndio e focos de calor

**Contexto:** o sistema atende o CIVAP (Vale do Paranapanema/SP). Este documento descreve
dois fluxos relacionados: (1) como a **cicatriz de incêndio** (burn scar) é criada, nos dois
modos de provedor; e (2) como os **focos de calor** (INPE) chegam ao sistema e são exibidos.

---

## 1. Cicatriz de incêndio (burn scar)

### 1.1 Fluxo comum (independe do provedor)

1. **Entrada** — em `/relatorios/cicatrizes` (`App\Livewire\Operations\Fire\FireScarAnalysisManage`)
   lista-se as ocorrências de um período; ao selecionar, cria-se um registro
   `App\Models\FireScarAnalysis` com status `PENDENTE`, **puxando latitude/longitude,
   município, UF e data da própria ocorrência**. Também é possível criar:
   - avulsa por coordenada (seção "Análise avulsa");
   - a partir do relatório final de incêndio florestal (botão "Solicitar cicatriz");
   - pelo link "Analisar cicatriz" no popup do mapa de focos.
2. **Processamento assíncrono** — `App\Jobs\RequestFireScarAnalysis` chama
   `App\Integrations\BurnScar\BurnScarService::process()`:
   - `PENDENTE → PROCESSANDO`;
   - chama `BurnScarProvider->analyze()` — o provedor é resolvido por
     `config('burnscar.provider')` no `AppServiceProvider`;
   - mapeia o `BurnScarResult` para as colunas, normaliza a severidade e faz
     `PROCESSANDO → CONCLUIDO` (ou `ERRO`, gravando a mensagem).
3. **Saída** — índices, severidade, `area_ha`, `perimeter_km` e `geometry_geojson` ficam no
   registro e são exibidos no mapa Leaflet (polígono da cicatriz) e no PDF.

Config em `config/burnscar.php` / `.env`:

```
BURNSCAR_PROVIDER=external   # serviço remoto (padrão)
BURNSCAR_PROVIDER=focos      # estimativa local pelos focos de calor
```

### 1.2 `BURNSCAR_PROVIDER=external`

`App\Integrations\BurnScar\Providers\ExternalBurnScarProvider` → `BurnScarClient` faz um
**POST para `BURNSCAR_BASE_URL`** (path `BURNSCAR_ANALYZE_PATH`, padrão `analyze`) com o JSON de
requisição (`location` / `period` / `satellite_source`, conforme a skill
`wildfire-scar-analysis`). Usa token, timeout, verify_ssl e um **circuit breaker**
(`BurnScarCircuitBreaker`) que pausa as chamadas após falhas consecutivas.

A resposta (`indices` / `burn_severity` / `burned_area`) vira o `BurnScarResult`.

- É a fonte **completa**: índices espectrais reais (NBR/dNBR/RBR) e classe de severidade.
- Depende do serviço remoto, que é quem processa as imagens de satélite (Sentinel-2/Landsat).

### 1.3 `BURNSCAR_PROVIDER=focos` (local, sem API)

`App\Integrations\BurnScar\Providers\FocosBurnScarProvider` calcula **localmente** (em PHP,
sem chamar nenhuma API) a partir dos **focos de calor** — lê da tabela `focos_satelite`
(model `App\Models\FocoSatelite`, conexão `fire_monitor`), a **mesma origem dos focos do mapa**.

> "focos" aqui = o **cálculo** é local; a **fonte** de dados são os focos de calor
> (não a tabela-espelho local `focos`).

Passo a passo:

1. Usa a **coordenada** e a **data** do `FireScarAnalysis` (que vieram da ocorrência).
2. Define a **janela de tempo**: se há data pré e pós-fogo, usa `[pré, pós]`; senão,
   `pós ± window_days` (padrão 10 dias) em torno da data pós-fogo.
3. Busca no `FocoSatelite` os focos **dentro de `radius_km`** (padrão 15 km) da coordenada e
   dentro da janela — bounding box + filtro fino por distância (haversine).
4. Projeta lat/lon → metros; gera um **círculo-buffer** de `buffer_m` (padrão 500 m) ao redor
   de cada foco e monta o **polígono via convex hull** (contorno externo do conjunto).
5. Calcula **`area_ha`** (shoelace) e **`perimeter_km`**, e grava o `geometry_geojson`.
6. **Não** calcula índices espectrais nem severidade (exigiria imagem): `severity_class` fica
   nulo; `confidence` = baixa/média conforme o nº de focos; `notes` registra que é aproximação.

Casos de borda:
- Sem focos no raio/janela → `CONCLUIDO` com área nula + nota explicativa;
- Falha de conexão ao `fire_monitor` → `ERRO`.

Parâmetros (`config/fire.php` já não; ficam em `config/burnscar.php` › `focos`):

```
BURNSCAR_FOCOS_RADIUS_KM=15
BURNSCAR_FOCOS_WINDOW_DAYS=10
BURNSCAR_FOCOS_BUFFER_M=500
```

### 1.4 Resumo da diferença

| | `external` | `focos` (local) |
|---|---|---|
| Fonte | serviço remoto (imagens de satélite) | focos de calor (`focos_satelite`) |
| Índices NBR/dNBR/RBR | sim | não |
| Severidade | sim (classe USGS) | não (nulo) |
| Área / perímetro / polígono | sim | sim (aproximado) |
| Dependência externa | HTTP + credenciais | nenhuma |

---

## 2. Focos de calor (INPE)

### 2.1 Origem (fora do sistema)

Os focos vêm de um **banco externo `fire_monitor`** (conexão pgsql separada — `FIRE_DB_*`),
na tabela **`focos_satelite`** (`App\Models\FocoSatelite`). Essa tabela é **populada por um
sistema externo de integração** (INPE/satélite); o Laravel apenas consome. Cada linha = 1 foco:
lat/lon, satélite, sensor, FRP, temperatura de brilho, confiança, bioma, município/UF, datas,
tamanho de pixel, risco de fogo INPE e `status_integracao`.

### 2.2 Importação para o banco local (espelho)

O comando `focos:importar-satelite` (`App\Domain\Fire\Actions\ImportarFocosSateliteAction`)
roda **agendado a cada 10 s** (`withoutOverlapping`, em `bootstrap/app.php`):

- pega registros `PENDENTE` / `NULL` / `ERRO` / `PROCESSANDO` de `focos_satelite`;
- marca `PROCESSANDO`, faz **upsert por `origem_id`** na tabela local `focos`
  (`App\Models\Foco`) e marca `PROCESSADO` (ou `ERRO`);
- modos: incremental (lote de 100, padrão) e `--all` (bulk de 1000).

> Existe um comando legado `focos:importar` (`App\Console\Commands\ImportarFocosSatelite`);
> o agendado e em uso é o `focos:importar-satelite`.

### 2.3 Exibição e consumo

O mapa de focos, o dashboard e o relatório leem **diretamente de `focos_satelite`**
(`fire_monitor`) via `App\Support\Fire\FireMapFocoQuery` e
`App\Support\Operations\Reports\FireFocosReportQuery`, com janela de dias, limite e a
**padronização geográfica de SP** (`App\Support\Fire\FocosBoundingBox`).

O estimador de cicatriz local (`FocosBurnScarProvider`) também lê de `focos_satelite`.

### 2.4 Padronização geográfica (SP)

A **maioria dos focos vem sem UF** (`estado` NULL). Por isso a padronização para a região do
consórcio **não usa o rótulo de estado**, e sim uma **bounding box** (retângulo de SP) por
coordenada, descartando focos rotulados com outra UF que caem na borda do retângulo:

```
FIRE_FOCOS_MIN_LAT=-25.5
FIRE_FOCOS_MAX_LAT=-19.7
FIRE_FOCOS_MIN_LON=-53.2
FIRE_FOCOS_MAX_LON=-44.0
FIRE_FOCOS_STATE_LABEL=SP   # mantém estado NULL ou = SP; exclui MG/RJ/etc. na borda
```

No relatório de focos, digitar uma UF no filtro sobrescreve a bbox (usa o rótulo).

---

## Arquivos-chave

| Papel | Arquivo |
|---|---|
| Registro/estado da cicatriz | `app/Models/FireScarAnalysis.php` |
| Orquestração (status) | `app/Integrations/BurnScar/BurnScarService.php` |
| Provedor externo | `app/Integrations/BurnScar/Providers/ExternalBurnScarProvider.php` |
| Provedor local por focos | `app/Integrations/BurnScar/Providers/FocosBurnScarProvider.php` |
| Seleção do provedor | `app/Providers/AppServiceProvider.php` (`config('burnscar.provider')`) |
| Job | `app/Jobs/RequestFireScarAnalysis.php` |
| UI cicatriz | `app/Livewire/Operations/Fire/FireScarAnalysis{Manage,Show}.php` |
| Importação de focos | `app/Domain/Fire/Actions/ImportarFocosSateliteAction.php` |
| Agendamento | `bootstrap/app.php` |
| Consulta de focos (mapa/relatório) | `app/Support/Fire/FireMapFocoQuery.php`, `app/Support/Operations/Reports/FireFocosReportQuery.php` |
| Bounding box SP | `app/Support/Fire/FocosBoundingBox.php` |
