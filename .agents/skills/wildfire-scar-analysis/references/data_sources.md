# Fontes de Dados para Incêndio Florestal e Cicatriz de Incêndio

## Focos de calor (detecção de fogo ativo, não cicatriz)

| Fonte | Sensor/base | Resolução | Frequência | Observação |
|---|---|---|---|---|
| INPE — Programa Queimadas / BDQueimadas | Múltiplos satélites (referência: AQUA tarde) | ~1 km (varia por satélite) | Várias vezes/dia | Fonte oficial brasileira; permite filtrar por satélite de referência, bioma, UF, município. Detecta "foco de calor", não delimita a cicatriz/área queimada. |
| NASA FIRMS | MODIS, VIIRS | 375 m (VIIRS) / 1 km (MODIS) | ~diário (MODIS) / múltiplas passagens (VIIRS) | Cobertura global, útil para contexto internacional/comparação. |

**Importante**: foco de calor ≠ cicatriz de incêndio. Foco de calor é um ponto (pixel) detectado no momento da queima ativa; a cicatriz é a área efetivamente queimada, mapeada depois, por diferença espectral (ver `spectral_indices.md`).

## Área queimada / cicatriz de incêndio (produtos prontos)

| Fonte | Base | Resolução | Cobertura | Observação |
|---|---|---|---|---|
| **MapBiomas Fogo** | Landsat (série histórica) + classificação por algoritmo (random forest) | 30 m | Brasil, anual, desde 1985 | Principal fonte brasileira de **cicatriz de incêndio já mapeada** (não é preciso calcular do zero); dados abertos, por bioma/UF/município/ano. Primeira escolha para análises históricas/retrospectivas no Brasil. |
| MODIS MCD64A1 (Burned Area) | MODIS | 500 m | Global, mensal | Bom para análises regionais/globais rápidas, resolução grosseira demais para nível de propriedade/talhão. |
| Copernicus EFFIS (Europa) | Vários | Variável | Europa/global (parcial) | Referência internacional, cobertura do Brasil é limitada. |

## Imagens brutas (quando não há produto pronto e é preciso calcular NBR/dNBR manualmente)

| Sensor | Resolução | Revisita | Vantagem | Limitação |
|---|---|---|---|---|
| Sentinel-2 (A/B/C) | 10-20 m | ~5 dias | Melhor resolução espacial gratuita para análise fina (talhão/propriedade) | Cobertura de nuvens pode reduzir cenas utilizáveis |
| Landsat 8/9 | 30 m | 16 dias (8 dias combinando 8+9) | Série histórica longa (desde 1984 com Landsat 5), essencial para comparação ano-a-ano | Revisita mais espaçada que Sentinel-2 |
| MODIS Terra/Aqua | 250-500 m | Diária | Alta frequência temporal | Resolução espacial grosseira, não serve para talhão individual |
| VIIRS | 375-750 m | Diária | Melhor que MODIS em resolução, boa frequência | Ainda grosseira para análise fina |

## Como escolher

1. **Precisa saber "queimou hoje/esta semana em algum lugar"?** → INPE BDQueimadas ou FIRMS (foco de calor).
2. **Precisa da área/perímetro exato queimado num evento específico recente?** → Calcular dNBR com Sentinel-2 (par pré/pós mais próximo em nuvens < 10%).
3. **Precisa de série histórica de área queimada por município/bioma/ano no Brasil?** → MapBiomas Fogo (já processado, não recalcular do zero).
4. **Precisa de contexto internacional ou área muito extensa com atualização diária?** → MODIS/VIIRS burned area products.

## Contexto operacional (Vale do Paranapanema / SP)

Na região de Presidente Prudente e Vale do Paranapanema, os incêndios mais frequentes estão associados a queima de cana-de-açúcar, pastagens e vegetação de beira de estrada/reserva. Para esse contexto:
- Priorizar Sentinel-2 (10 m) pela resolução fina necessária para talhões agrícolas.
- Cruzar com focos de calor do INPE (BDQueimadas) para validar data/hora do evento antes de escolher o par de imagens pré/pós-fogo.
- Lembrar que a "rebrota" de cana após queima é rápida (semanas), então a janela pós-fogo para cálculo de dNBR deve ser curta.
