# Classificação de Severidade de Queima

## Classes padrão USGS/FIREMON (dNBR, convenção escalada ×1000)

| Classe | dNBR (escalado) | dNBR (não escalado) |
|---|---|---|
| Rebrota alta (enhanced regrowth, high) | < -251 | < -0.251 |
| Rebrota baixa (enhanced regrowth, low) | -251 a -101 | -0.251 a -0.101 |
| Não queimado / inalterado | -100 a +99 | -0.100 a +0.099 |
| Severidade baixa | 100 a 269 | 0.100 a 0.269 |
| Severidade moderada-baixa | 270 a 439 | 0.270 a 0.439 |
| Severidade moderada-alta | 440 a 659 | 0.440 a 0.659 |
| Severidade alta | > 660 | > 0.660 |

Estes limiares foram calibrados originalmente para florestas de coníferas da América do Norte (Key & Benson, USGS FIREMON). Servem como referência de ordem de grandeza, mas **devem ser recalibrados/validados em campo** para biomas brasileiros — a resposta espectral de Cerrado, Caatinga e Pantanal a incêndio difere de floresta temperada.

## Ajustes por bioma brasileiro (orientação geral, não substituem validação de campo)

- **Amazônia (floresta densa)**: thresholds USGS tendem a funcionar razoavelmente bem, já que a estrutura de dossel é mais próxima do contexto original de calibração.
- **Cerrado**: vegetação já esparsa e sazonalmente decídua faz o NBR pré-fogo variar bastante ao longo do ano — prefira **RBR** em vez de dNBR puro, e sempre casar a imagem pré-fogo com a mesma época do ano da pós-fogo (evitar comparar estação seca com chuvosa).
- **Caatinga/Pantanal**: alta variabilidade sazonal de umidade do solo e da vegetação; recomenda-se usar mediana de múltiplas imagens pré-fogo (compósito) em vez de uma única cena, para reduzir ruído.
- **Áreas agrícolas/canavial** (relevante para queimadas de cana, comuns na região de Presidente Prudente/Vale do Paranapanema-SP): o ciclo de queima e replantio é muito mais rápido que em vegetação nativa; janelas pré/pós-fogo devem ser de dias a poucas semanas, não meses, e a "rebrota" após queima de cana não deve ser confundida com recuperação de vegetação nativa.

## Estimativa de área queimada

```
área_queimada_ha = (nº de pixels classificados como queimado) × (resolução_pixel_m²) / 10.000
```

- "Queimado" = qualquer pixel com classe ≥ severidade baixa (ou seja, dNBR/RBR acima do limiar de "não queimado").
- Para reduzir ruído/sal-e-pimenta, aplicar um filtro de área mínima de mancha (ex: descartar manchas < 1-3 pixels contíguos) antes de somar.
- Sempre mascarar nuvens, sombra de nuvem e corpos d'água (via NDWI ou máscara de qualidade do produto) antes de calcular a área — do contrário a área queimada é superestimada.

## Nível de confiança

Reportar `confidence` no JSON como:
- **alta**: par pré/pós-fogo com poucas nuvens (<10%), mesma época do ano, mesmo sensor, dados validados por foco de calor (INPE/FIRMS) coincidente no local/data.
- **média**: par pré/pós-fogo disponível mas com alguma diferença sazonal ou nuvens moderadas (10-30%), ou classificação apenas por um índice (não cruzado com NDVI/RBR).
- **baixa**: dados incompletos, apenas uma imagem (sem par pré/pós), estimativa por analogia/contexto sem cálculo direto, ou nuvens > 30%.
