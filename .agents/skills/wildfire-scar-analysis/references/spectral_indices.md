# Índices Espectrais para Detecção de Cicatriz de Incêndio

## Convenção de escala — IMPORTANTE

Os índices abaixo são naturalmente adimensionais no intervalo -1 a +1. Duas convenções aparecem na literatura/software:
- **Não escalada**: valores diretos entre -1 e 1 (ex: dNBR = 0.35).
- **Escalada (USGS/FIREMON)**: multiplicada por 1000, para evitar casas decimais (ex: dNBR = 350).

Sempre declare qual convenção está sendo usada ao reportar um valor, e confira qual convenção os thresholds em `burn_severity_classification.md` estão usando antes de classificar.

## NBR — Normalized Burn Ratio

```
NBR = (NIR - SWIR2) / (NIR + SWIR2)
```
- Vegetação sadia: NIR alto, SWIR2 baixo → NBR alto (próximo de +1).
- Área queimada: NIR baixo (perda de clorofila/estrutura foliar), SWIR2 alto (solo exposto, cinzas, umidade reduzida) → NBR baixo ou negativo.

## dNBR — Differenced NBR (o índice padrão para cicatriz de incêndio)

```
dNBR = NBR_pre-fogo - NBR_pós-fogo
```
- Calculado com imagem de **antes** do incêndio e imagem de **depois** (idealmente logo após, antes de rebrota significativa, mas não tão cedo a ponto de haver fumaça residual).
- dNBR positivo e alto → maior severidade de queima.
- dNBR negativo → geralmente indica rebrota/crescimento vegetativo (ex: green-up sazonal), não queima.

## RBR — Relativized Burn Ratio

```
RBR = dNBR / (NBR_pre + 1.001)
```
- Mais robusto que dNBR puro em paisagens heterogêneas ou com vegetação esparsa (ex: savanas, Cerrado, Caatinga), onde o NBR pré-fogo já é baixo e o dNBR bruto pode subestimar a severidade relativa.
- Recomendado para biomas brasileiros não-florestais (Cerrado, Caatinga, Pampa) em vez de dNBR puro.

## NDVI — Normalized Difference Vegetation Index

```
NDVI = (NIR - RED) / (NIR + RED)
```
- Não é específico para fogo, mas útil como indicador complementar de vigor da vegetação antes/depois e para diferenciar queima de outros tipos de perda de cobertura (desmatamento, estresse hídrico).
- dNDVI (NDVI_pre - NDVI_pós) pode ser usado como checagem cruzada do dNBR.

## BAI — Burn Area Index

```
BAI = 1 / [(0.1 - RED)² + (0.06 - NIR)²]
```
- Realça áreas de carvão/cinzas (baixa reflectância no vermelho e no NIR).
- Mais sensível a ruído em áreas com solo escuro naturalmente (ex: solos orgânicos) — usar como complemento ao dNBR/RBR, não isoladamente.

## NDWI (opcional, contexto)

```
NDWI = (GREEN - NIR) / (GREEN + NIR)
```
- Útil para mascarar corpos d'água antes da análise de queima, evitando falsos positivos de "alta severidade" em pixels de água.

## Bandas por satélite

| Sensor | RED | NIR | SWIR2 | GREEN | Resolução |
|---|---|---|---|---|---|
| Sentinel-2 (MSI) | B4 (665 nm) | B8 (842 nm) | B12 (2190 nm) | B3 (560 nm) | 10-20 m |
| Landsat 8/9 (OLI) | Banda 4 | Banda 5 | Banda 7 | Banda 3 | 30 m |
| MODIS (Terra/Aqua) | Banda 1 | Banda 2 | Banda 7 | Banda 4 | 250-500 m |
| VIIRS | I1 | I2 | I3 | M4 | 375-750 m |

Para cicatriz de incêndio de alta precisão em nível de propriedade/talhão, prefira Sentinel-2 ou Landsat. Para monitoramento regional/biomas inteiros com maior frequência temporal, MODIS/VIIRS (mas resolução espacial grosseira).
