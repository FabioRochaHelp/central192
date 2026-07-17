#!/usr/bin/env python3
"""
calc_burn_indices.py

Calcula indices espectrais de cicatriz de incendio (NBR, dNBR, RBR, NDVI, BAI)
a partir de arrays de bandas pre/pos-fogo, classifica severidade e imprime
um JSON no esquema padrao desta skill.

Uso tipico (dentro de outro script Python, ou via CLI com arrays .npy):

    python calc_burn_indices.py \
        --nir-pre nir_pre.npy --swir2-pre swir2_pre.npy --red-pre red_pre.npy \
        --nir-post nir_post.npy --swir2-post swir2_post.npy --red-post red_post.npy \
        --pixel-size-m 10 \
        --output result.json

Os arrays devem estar em refletancia (0-1) ou numeros digitais consistentes
entre pre e pos (mesma unidade). Reflectancia (0-1) e recomendada.
"""

import argparse
import json
import sys

import numpy as np


def safe_ratio(a, b):
    """(a - b) / (a + b) evitando divisao por zero."""
    denom = a + b
    with np.errstate(divide="ignore", invalid="ignore"):
        result = np.where(denom != 0, (a - b) / denom, 0.0)
    return result


def compute_nbr(nir, swir2):
    return safe_ratio(nir, swir2)


def compute_ndvi(nir, red):
    return safe_ratio(nir, red)


def compute_bai(red, nir):
    return 1.0 / ((0.1 - red) ** 2 + (0.06 - nir) ** 2 + 1e-9)


def compute_dnbr(nbr_pre, nbr_post):
    return nbr_pre - nbr_post


def compute_rbr(dnbr, nbr_pre):
    return dnbr / (nbr_pre + 1.001)


# Limiares USGS/FIREMON, convencao NAO escalada (-1 a 1)
SEVERITY_THRESHOLDS = [
    (-1.000, -0.251, "rebrota_alta"),
    (-0.251, -0.101, "rebrota_baixa"),
    (-0.101, 0.099, "nao_queimado"),
    (0.099, 0.269, "baixa"),
    (0.269, 0.439, "moderada_baixa"),
    (0.439, 0.659, "moderada_alta"),
    (0.659, 1.500, "alta"),
]


def classify_severity_array(dnbr_or_rbr):
    classes = np.full(dnbr_or_rbr.shape, "indefinido", dtype=object)
    for low, high, label in SEVERITY_THRESHOLDS:
        mask = (dnbr_or_rbr > low) & (dnbr_or_rbr <= high)
        classes[mask] = label
    return classes


def dominant_class(classes):
    values, counts = np.unique(classes, return_counts=True)
    return str(values[np.argmax(counts)])


def estimate_burned_area_ha(classes, pixel_size_m):
    burned_mask = ~np.isin(classes, ["nao_queimado", "rebrota_alta", "rebrota_baixa"])
    n_burned_pixels = int(np.sum(burned_mask))
    pixel_area_m2 = pixel_size_m ** 2
    return round(n_burned_pixels * pixel_area_m2 / 10000.0, 2)


def build_result(nir_pre, swir2_pre, red_pre, nir_post, swir2_post, red_post,
                  pixel_size_m, use_rbr=False, meta=None):
    nbr_pre = compute_nbr(nir_pre, swir2_pre)
    nbr_post = compute_nbr(nir_post, swir2_post)
    dnbr = compute_dnbr(nbr_pre, nbr_post)
    rbr = compute_rbr(dnbr, nbr_pre)
    ndvi_pre = compute_ndvi(nir_pre, red_pre)
    ndvi_post = compute_ndvi(nir_post, red_post)
    bai = compute_bai(red_post, nir_post)

    index_for_classification = rbr if use_rbr else dnbr
    classes = classify_severity_array(index_for_classification)
    severity_class = dominant_class(classes)
    burned_area_ha = estimate_burned_area_ha(classes, pixel_size_m)

    meta = meta or {}

    result = {
        "analysis_id": meta.get("analysis_id"),
        "location": {
            "lat": meta.get("lat"),
            "lon": meta.get("lon"),
            "municipio": meta.get("municipio"),
            "estado": meta.get("estado"),
            "bioma": meta.get("bioma"),
        },
        "period": {
            "pre_fire_date": meta.get("pre_fire_date"),
            "post_fire_date": meta.get("post_fire_date"),
        },
        "satellite_source": {
            "sensor": meta.get("sensor", "nao_informado"),
            "bands_used": ["NIR", "SWIR2", "RED"],
            "resolution_m": pixel_size_m,
        },
        "indices": {
            "nbr_pre": round(float(np.mean(nbr_pre)), 4),
            "nbr_post": round(float(np.mean(nbr_post)), 4),
            "dnbr": round(float(np.mean(dnbr)), 4),
            "rbr": round(float(np.mean(rbr)), 4),
            "ndvi_pre": round(float(np.mean(ndvi_pre)), 4),
            "ndvi_post": round(float(np.mean(ndvi_post)), 4),
            "bai": round(float(np.mean(bai)), 4),
        },
        "burn_severity": {
            "class": severity_class,
            "dnbr_range": "ver references/burn_severity_classification.md",
            "confidence": meta.get("confidence", "media"),
        },
        "burned_area": {
            "area_ha": burned_area_ha,
            "perimeter_km": None,
            "geometry_geojson": None,
        },
        "vegetation_type": meta.get("vegetation_type"),
        "notes": meta.get(
            "notes",
            "Valores de indices sao media da area analisada; area queimada estimada por contagem de pixels classificados acima do limiar 'nao_queimado'.",
        ),
    }
    return result


def main():
    parser = argparse.ArgumentParser(description="Calcula indices de cicatriz de incendio a partir de arrays .npy")
    parser.add_argument("--nir-pre", required=True)
    parser.add_argument("--swir2-pre", required=True)
    parser.add_argument("--red-pre", required=True)
    parser.add_argument("--nir-post", required=True)
    parser.add_argument("--swir2-post", required=True)
    parser.add_argument("--red-post", required=True)
    parser.add_argument("--pixel-size-m", type=float, default=10.0)
    parser.add_argument("--use-rbr", action="store_true", help="Usar RBR em vez de dNBR para classificacao (recomendado para Cerrado/Caatinga)")
    parser.add_argument("--output", default=None, help="Caminho do JSON de saida; se omitido, imprime no stdout")

    args = parser.parse_args()

    nir_pre = np.load(args.nir_pre)
    swir2_pre = np.load(args.swir2_pre)
    red_pre = np.load(args.red_pre)
    nir_post = np.load(args.nir_post)
    swir2_post = np.load(args.swir2_post)
    red_post = np.load(args.red_post)

    result = build_result(
        nir_pre, swir2_pre, red_pre,
        nir_post, swir2_post, red_post,
        pixel_size_m=args.pixel_size_m,
        use_rbr=args.use_rbr,
    )

    output_json = json.dumps(result, indent=2, ensure_ascii=False)

    if args.output:
        with open(args.output, "w", encoding="utf-8") as f:
            f.write(output_json)
        print(f"Resultado salvo em {args.output}", file=sys.stderr)
    else:
        print(output_json)


if __name__ == "__main__":
    main()
