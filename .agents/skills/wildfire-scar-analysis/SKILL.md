---
name: wildfire-scar-analysis
description: "Especialista em incêndios florestais com foco em análise de cicatrizes de incêndio (burn scars) via sensoriamento remoto e índices espectrais (NBR, dNBR, RBR, NDVI, BAI). Use esta skill sempre que o usuário mencionar incêndio florestal, queimada, foco de calor, cicatriz de incêndio, área queimada, severidade de queima, dados INPE, BDQueimadas, MapBiomas Fogo, imagens Sentinel-2, Landsat, MODIS ou VIIRS para detecção de fogo, ou pedir para classificar ou quantificar dano de vegetação após incêndio, mesmo que o pedido não use o termo técnico exato. Também aciona para geração de saídas estruturadas em JSON descrevendo eventos de incêndio para integração em sistemas, como painéis de monitoramento, CCOs e sistemas de gestão ambiental."
---

# Análise de Incêndios Florestais e Cicatrizes de Incêndio

Esta skill dá a Claude o conhecimento técnico de um especialista em incêndios florestais (wildfire/forest fire specialist), com ênfase em **detecção e classificação de cicatrizes de incêndio** por sensoriamento remoto, e produção de **saídas estruturadas em JSON** para integração em sistemas.

## Quando usar cada referência

| Preciso de... | Ler |
|---|---|
| Fórmulas de índices espectrais (NBR, dNBR, RBR, NDVI, BAI, NDWI) e bandas por satélite | `references/spectral_indices.md` |
| Classificar severidade de queima (classes USGS, thresholds) | `references/burn_severity_classification.md` |
| Escolher fonte de dados/satélite (Sentinel-2, Landsat, MODIS, VIIRS, INPE, MapBiomas Fogo) | `references/data_sources.md` |
| Calcular índices a partir de arrays de bandas e gerar JSON pronto | `scripts/calc_burn_indices.py` |
| Esquema de saída JSON padrão para o evento de incêndio | Seção "Esquema de saída JSON" abaixo |

Sempre carregue o(s) arquivo(s) de referência relevante(s) antes de responder perguntas técnicas específicas de fórmula/threshold — não confie apenas na memória, pois os limiares variam por bioma e convenção de escala (ver observação sobre escala em `spectral_indices.md`).

## Fluxo de trabalho típico

1. **Entender o pedido**: é uma pergunta conceitual (ex: "o que é dNBR?"), uma análise de dados reais (bandas/imagens fornecidas), ou geração de relatório/JSON para um evento específico?
2. **Se houver dados de imagem/bandas disponíveis** (arquivo raster, valores de banda, ou já um NBR pré/pós calculado):
   - Calcular NBR pré-fogo e pós-fogo, depois dNBR (e RBR se a vegetação de referência for esparsa/heterogênea — mais robusto que dNBR puro nesses casos).
   - Classificar severidade usando `references/burn_severity_classification.md`.
   - Estimar área queimada (contagem de pixels acima do limiar de "queimado" × resolução do pixel).
   - Preencher o esquema JSON abaixo.
3. **Se não houver dados de imagem**, mas o usuário descrever o contexto (localização, datas, bioma), usar `references/data_sources.md` para recomendar a fonte de imagem mais adequada (resolução espacial vs. frequência de revisita vs. cobertura de nuvens) e explicar o que seria necessário para o cálculo.
4. **Contexto brasileiro**: sempre que a pergunta envolver Brasil, priorizar referências ao INPE (Programa Queimadas/BDQueimadas para focos de calor) e ao MapBiomas Fogo (mapeamento anual de área queimada/cicatriz), que são as fontes de fato usadas operacionalmente no país — não recomendar apenas fontes internacionais (NASA FIRMS/MODIS/VIIRS) sem mencionar as brasileiras.
5. **Nunca inventar coordenadas, datas de imagens reais, ou valores de índice** que não foram fornecidos pelo usuário nem calculados a partir de dados reais — se faltar informação, pedir os dados de banda/raster ou deixar campos como `null` no JSON com uma nota explicando a lacuna.

## Esquema de saída JSON

Quando o usuário pedir dados estruturados (padrão desta skill) para um evento/análise de incêndio, usar este esquema como base, omitindo ou marcando como `null` o que não puder ser determinado:

```json
{
  "analysis_id": "string (opcional, gerado pelo usuário/sistema)",
  "location": {
    "lat": 0.0,
    "lon": 0.0,
    "municipio": "string|null",
    "estado": "string|null",
    "bioma": "string|null"
  },
  "period": {
    "pre_fire_date": "YYYY-MM-DD|null",
    "post_fire_date": "YYYY-MM-DD|null"
  },
  "satellite_source": {
    "sensor": "Sentinel-2 | Landsat 8/9 | MODIS | VIIRS | outro",
    "bands_used": ["NIR", "SWIR2", "RED"],
    "resolution_m": 10
  },
  "indices": {
    "nbr_pre": null,
    "nbr_post": null,
    "dnbr": null,
    "rbr": null,
    "ndvi_pre": null,
    "ndvi_post": null,
    "bai": null
  },
  "burn_severity": {
    "class": "não queimado | baixa | moderada-baixa | moderada-alta | alta | rebrota",
    "dnbr_range": "string",
    "confidence": "alta|média|baixa"
  },
  "burned_area": {
    "area_ha": null,
    "perimeter_km": null,
    "geometry_geojson": null
  },
  "vegetation_type": "string|null",
  "notes": "string"
}
```

Ao gerar o JSON, sempre explicar em 1-2 frases fora do bloco o que foi calculado vs. o que é estimativa/placeholder, para não passar confiança falsa sobre dados que não existem.

## Script utilitário

`scripts/calc_burn_indices.py` recebe arrays de bandas (NIR, SWIR2, RED — pré e pós-incêndio) via numpy e:
- calcula NBR pré/pós, dNBR, RBR, NDVI pré/pós, BAI
- classifica severidade pixel a pixel conforme `references/burn_severity_classification.md`
- estima área queimada (ha) dado o tamanho de pixel em metros
- imprime o JSON no esquema acima

Use-o sempre que o usuário fornecer dados de banda reais (arquivos .tif/.npy/arrays) em vez de calcular fórmulas manualmente — reduz erro de aritmética.
