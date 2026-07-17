/**
 * Mapa Leaflet carregado via CDN (unpkg) para não depender de `npm install leaflet` no servidor de build.
 * Usa `$wire` no hospedeiro Livewire.
 */
let leafletCdnPromise = null;

async function loadLeafletFromCdn() {
    if (window.L) {
        return window.L;
    }
    if (leafletCdnPromise) {
        return leafletCdnPromise;
    }

    leafletCdnPromise = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-samu-leaflet-css]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            link.setAttribute('data-samu-leaflet-css', '1');
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve(window.L);
        script.onerror = () => reject(new Error('leaflet_cdn_load_failed'));
        document.head.appendChild(script);
    });

    return leafletCdnPromise;
}

let leafletRoutingCdnPromise = null;

/** Leaflet Routing Machine (LRM) via CDN, sobre o OSRM público. Depende do Leaflet já carregado. */
async function loadLeafletRoutingMachineFromCdn() {
    const L = await loadLeafletFromCdn();
    if (L && L.Routing) {
        return L;
    }
    if (leafletRoutingCdnPromise) {
        return leafletRoutingCdnPromise;
    }

    leafletRoutingCdnPromise = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-samu-lrm-css]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css';
            link.setAttribute('data-samu-lrm-css', '1');
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve(window.L);
        script.onerror = () => reject(new Error('lrm_cdn_load_failed'));
        document.head.appendChild(script);
    });

    return leafletRoutingCdnPromise;
}

let incidentOsmHooksInstalled = false;

function registerIncidentOsmAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data) {
        return;
    }

    if (window.__samuIncidentOsmAlpineDone) {
        return;
    }

    window.__samuIncidentOsmAlpineDone = true;

    Alpine.data('incidentOsmMap', () => ({
        map: null,
        marker: null,

        lw() {
            return this.$wire;
        },

        readLatLng() {
            const wire = this.lw();
            if (!wire) {
                return [-15.793889, -47.882778];
            }
            const lat = parseFloat(wire.latitude);
            const lng = parseFloat(wire.longitude);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                return [lat, lng];
            }

            return [-15.793889, -47.882778];
        },

        syncMarkerFromWire() {
            if (!this.map || !this.marker) {
                return;
            }
            const [lat, lng] = this.readLatLng();
            this.marker.setLatLng([lat, lng]);
            this.map.setView([lat, lng], this.map.getZoom(), { animate: false });
            requestAnimationFrame(() => this.map?.invalidateSize());
        },

        async init() {
            let L;
            try {
                L = await loadLeafletFromCdn();
            } catch {
                return;
            }

            const wire = this.lw();
            if (!wire || !this.$refs.mapEl) {
                return;
            }

            const [lat, lng] = this.readLatLng();
            this.map = L.map(this.$refs.mapEl, { zoomControl: true }).setView([lat, lng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            const icon = L.divIcon({
                className: 'incident-osm-pin',
                html: '<span class="block h-3.5 w-3.5 rounded-full border-2 border-cyan-700 bg-cyan-400 shadow-md ring-2 ring-white dark:border-cyan-300 dark:bg-cyan-500"></span>',
                iconSize: [14, 14],
                iconAnchor: [7, 7],
            });

            this.marker = L.marker([lat, lng], { draggable: true, icon }).addTo(this.map);

            this.marker.on('dragend', () => {
                const p = this.marker.getLatLng();
                wire.set('latitude', p.lat.toFixed(7));
                wire.set('longitude', p.lng.toFixed(7));
            });

            this.map.on('click', (e) => {
                this.marker.setLatLng(e.latlng);
                wire.set('latitude', e.latlng.lat.toFixed(7));
                wire.set('longitude', e.latlng.lng.toFixed(7));
            });

            const invalidate = () => {
                if (!this.map) {
                    return;
                }
                this.syncMarkerFromWire();
                setTimeout(() => this.map.invalidateSize(), 50);
            };

            window.addEventListener('incident-osm-invalidate', invalidate);

            requestAnimationFrame(invalidate);
        },
    }));
}

document.addEventListener('livewire:init', () => {
    registerIncidentOsmAlpine();

    if (!incidentOsmHooksInstalled && window.Livewire?.hook) {
        incidentOsmHooksInstalled = true;
        let morphInvalidateTimer = null;
        Livewire.hook('morph.updated', () => {
            clearTimeout(morphInvalidateTimer);
            morphInvalidateTimer = setTimeout(() => {
                window.dispatchEvent(new CustomEvent('incident-osm-invalidate'));
            }, 200);
        });
    }
});

document.addEventListener('alpine:init', () => {
    registerIncidentOsmAlpine();
});

// ---------------------------------------------------------------------------
// incidentRouteMap — mapa de percurso da ocorrência (detalhe, somente leitura)
// ---------------------------------------------------------------------------

function registerIncidentRouteMapAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuIncidentRouteMapDone) return;
    window.__samuIncidentRouteMapDone = true;

    Alpine.data('incidentRouteMap', (opts = {}) => ({
        map: null,
        _L: null,
        _routeLayer: null,
        _incidentMarker: null,
        state: 'idle', // idle | loading | loaded | empty | error | no_device
        pointCount: 0,
        errorMessage: '',

        routeUrl: opts.routeUrl ?? null,
        incidentLat: parseFloat(opts.incidentLat) || null,
        incidentLng: parseFloat(opts.incidentLng) || null,
        hasDevice: !!opts.hasDevice,
        vehiclePrefix: opts.vehiclePrefix ?? null,

        async init() {
            try {
                this._L = await loadLeafletFromCdn();
            } catch {
                this.state = 'error';
                return;
            }

            if (!this.$refs.routeMapEl) return;

            this._initMap();

            if (!this.hasDevice) {
                this.state = 'no_device';
                return;
            }

            await this.fetchRoute();
        },

        async reloadRoute() {
            if (!this.hasDevice || !this._L) return;
            await this.fetchRoute();
        },

        _initMap() {
            const L = this._L;
            const centerLat = this.incidentLat ?? -15.793889;
            const centerLng = this.incidentLng ?? -47.882778;
            const zoom = (this.incidentLat && this.incidentLng) ? 15 : 5;

            this.map = L.map(this.$refs.routeMapEl, { zoomControl: true }).setView([centerLat, centerLng], zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            this._routeLayer = L.featureGroup().addTo(this.map);

            if (this.incidentLat && this.incidentLng) {
                const incidentIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:14px;height:14px;border-radius:50%;background:#ef4444;border:2px solid #fff;box-shadow:0 0 0 2px #ef4444"></div>`,
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                });
                this._incidentMarker = L.marker([this.incidentLat, this.incidentLng], { icon: incidentIcon })
                    .addTo(this.map)
                    .bindPopup(`<b>Local da ocorrência</b>`);
            }

            requestAnimationFrame(() => this.map?.invalidateSize());
        },

        _clearRouteLayer() {
            if (this._routeLayer) {
                this._routeLayer.clearLayers();
            }
            this.pointCount = 0;
        },

        async fetchRoute() {
            const L = this._L;
            if (!this.routeUrl || !L || !this.map) {
                this.state = 'error';
                return;
            }

            this.state = 'loading';
            this.errorMessage = '';
            this._clearRouteLayer();

            try {
                const res = await fetch(this.routeUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                const json = await res.json();

                if (!res.ok) {
                    this.state = 'error';
                    this.errorMessage = json.error ?? 'Erro ao carregar rota.';
                    this._showErrorPopup(this.errorMessage);
                    return;
                }

                const points = json.points ?? [];
                this.pointCount = json.count ?? points.length;

                if (points.length === 0) {
                    this.state = 'empty';
                    return;
                }

                const latlngs = points.map(p => [p.lat, p.lng]);

                const polyline = L.polyline(latlngs, {
                    color: '#2563eb',
                    weight: 4,
                    opacity: 0.85,
                });
                this._routeLayer.addLayer(polyline);

                const startIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff;box-shadow:0 0 0 2px #22c55e"></div>`,
                    iconSize: [12, 12], iconAnchor: [6, 6],
                });
                const startMarker = L.marker(latlngs[0], { icon: startIcon });
                const startTime = points[0]?.time ? `<br><span style="color:#6b7280;font-size:11px">${points[0].time}</span>` : '';
                startMarker.bindPopup(`<b>Saída</b>${this.vehiclePrefix ? ' — ' + this.vehiclePrefix : ''}${startTime}`);
                this._routeLayer.addLayer(startMarker);

                const endIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:12px;height:12px;border-radius:50%;background:#f59e0b;border:2px solid #fff;box-shadow:0 0 0 2px #f59e0b"></div>`,
                    iconSize: [12, 12], iconAnchor: [6, 6],
                });
                const endMarker = L.marker(latlngs[latlngs.length - 1], { icon: endIcon });
                const endTime = points[points.length - 1]?.time ? `<br><span style="color:#6b7280;font-size:11px">${points[points.length - 1].time}</span>` : '';
                endMarker.bindPopup(`<b>Último ponto</b>${endTime}`);
                this._routeLayer.addLayer(endMarker);

                const bounds = polyline.getBounds();
                if (this._incidentMarker) {
                    bounds.extend(this._incidentMarker.getLatLng());
                }
                this.map.fitBounds(bounds, { padding: [32, 32] });
                this.state = 'loaded';

                requestAnimationFrame(() => this.map?.invalidateSize());
            } catch {
                this.state = 'error';
                this.errorMessage = 'Erro ao carregar rota.';
            }
        },

        _showErrorPopup(message) {
            if (this.incidentLat && this.incidentLng && this.map && this._L) {
                this._L.popup()
                    .setLatLng([this.incidentLat, this.incidentLng])
                    .setContent(`<span style="color:#ef4444">${message}</span>`)
                    .openOn(this.map);
            }
        },
    }));
}

document.addEventListener('alpine:init', () => {
    registerIncidentRouteMapAlpine();
});

document.addEventListener('livewire:init', () => {
    registerIncidentRouteMapAlpine();
});

// ---------------------------------------------------------------------------
// dispatchMap — mapa tático do CCO (ocorrências + alertas de chamada + viaturas, Reverb)
// ---------------------------------------------------------------------------

if (!window.__samuAlertAction) {
    window.__samuAlertAction = function (action, alertId) {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.dispatch !== 'function') {
            return;
        }
        if (action === 'abort-cluster') {
            Livewire.dispatch('tactical-map-alert-cluster-abort', { locationKey: alertId });
        } else if (action === 'create-cluster') {
            Livewire.dispatch('tactical-map-alert-cluster-create-incident', { locationKey: alertId });
        }
    };
}

if (!window.__samuAlertDetails) {
    window.__samuAlertDetails = function (locationKey) {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.dispatch !== 'function') {
            return;
        }
        Livewire.dispatch('call-alert-fire-report', { locationKey });
    };
}

/** Atualiza cluster de alertas imediatamente (ex.: ocorrência salva, abortar, monitorar). */
if (!window.__samuUpdateAlertCluster) {
    window.__samuUpdateAlertCluster = function (cluster) {
        if (!cluster) {
            return;
        }
        document.querySelectorAll('[x-data]').forEach((el) => {
            const data = window.Alpine?.$data?.(el);
            if (!data?._addOrUpdateAlertCluster) {
                return;
            }
            if (cluster.remove) {
                data._removeAlertCluster(cluster.location_key);
            } else {
                data._addOrUpdateAlertCluster(cluster);
            }
        });
    };
}

if (!document.getElementById('samu-map-markers-style')) {
    const style = document.createElement('style');
    style.id = 'samu-map-markers-style';
    style.textContent = '.samu-map-marker.leaflet-div-icon,.samu-alert-marker.leaflet-div-icon,.samu-wind-marker.leaflet-div-icon{background:transparent!important;border:none!important;margin:0!important;padding:0!important;box-sizing:content-box!important}'
        + '.leaflet-marker-icon.samu-map-marker,.leaflet-marker-icon.samu-alert-marker{transition:none!important}'
        + '.samu-map-marker-visual,.samu-alert-dot{box-sizing:border-box;pointer-events:none;transform:translateZ(0)}'
        + '.samu-wind-arrow{display:block;transform-origin:50% 50%}'
        + '@keyframes samu-alert-pulse{0%,100%{opacity:1;box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(245,158,11,.55)}50%{opacity:.88;box-shadow:0 0 0 2px #fff,0 0 0 7px rgba(245,158,11,.2)}}'
        + '@keyframes samu-alert-monitor-pulse{0%,100%{opacity:1;box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(139,92,246,.55)}50%{opacity:.88;box-shadow:0 0 0 2px #fff,0 0 0 7px rgba(139,92,246,.2)}}'
        + '.leaflet-routing-container{display:none!important}'
        + '[x-cloak]{display:none!important}';
    document.head.appendChild(style);
}

// ---------------------------------------------------------------------------
// Camadas base — seletor de vias/satélite/relevo (mapa tático e focos)
// ---------------------------------------------------------------------------

const ESRI_TILE_BASE = 'https://server.arcgisonline.com/ArcGIS/rest/services';

/**
 * Camadas base disponíveis. "Vias" é o padrão e o primeiro a ser adicionado ao mapa.
 *
 * Sobre `maxNativeZoom`: nem todo provedor tem tile em todo zoom. Onde falta, o
 * Leaflet ampliaria o vazio; com `maxNativeZoom` ele amplia o último tile real.
 *  - Satélite (Esri): na região do consórcio a imagem termina no z18 — no z19 o
 *    provedor devolve um tile cinza "Map data not yet available".
 *  - Relevo (OpenTopoMap): só publica até o z17.
 */
function createBaseLayers(L) {
    return {
        'Vias': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }),
        // Imagem pura não serve para despachar: sem nome de rua o operador não se
        // localiza. Os rótulos do Esri (PNG transparente) vão por cima da imagem.
        'Satélite': L.layerGroup([
            L.tileLayer(`${ESRI_TILE_BASE}/World_Imagery/MapServer/tile/{z}/{y}/{x}`, {
                maxZoom: 19,
                maxNativeZoom: 18,
                attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics',
            }),
            L.tileLayer(`${ESRI_TILE_BASE}/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}`, {
                maxZoom: 19,
                maxNativeZoom: 18,
            }),
            L.tileLayer(`${ESRI_TILE_BASE}/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}`, {
                maxZoom: 19,
            }),
        ]),
        'Relevo': L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            maxNativeZoom: 17,
            attribution: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
        }),
    };
}

/**
 * Adiciona a camada padrão ao mapa e pendura o seletor de camadas do Leaflet.
 * `overlays` (opcional) entra como camadas ligáveis/desligáveis no mesmo controle.
 */
function attachBaseLayers(L, map, overlays = null) {
    const baseLayers = createBaseLayers(L);

    Object.values(baseLayers)[0].addTo(map);
    L.control.layers(baseLayers, overlays, { position: 'topright' }).addTo(map);

    return baseLayers;
}

// ---------------------------------------------------------------------------
// Vento — camada opcional do mapa operacional (Open-Meteo, via /operations/map/wind)
// ---------------------------------------------------------------------------

const WIND_CARDINALS = ['N', 'NE', 'L', 'SE', 'S', 'SO', 'O', 'NO'];

/** Rosa dos ventos de 8 pontos, em pt-BR (L = leste, O = oeste). */
function windCardinal(deg) {
    const index = Math.round((((deg % 360) + 360) % 360) / 45) % 8;

    return WIND_CARDINALS[index];
}

/** Faixas de velocidade: quanto mais forte o vento, mais rápido o fogo corre. */
function windColor(speedKmh) {
    if (speedKmh >= 50) return '#dc2626';
    if (speedKmh >= 30) return '#ea580c';
    if (speedKmh >= 12) return '#f59e0b';

    return '#2563eb';
}

const WIND_ARROW_SHAFT = 'M18 8 L18 31';
const WIND_ARROW_HEAD = 'M18 3 L11 14.5 L18 11.5 L25 14.5 Z';

/**
 * Seta apontando para onde o vento SOPRA (`spreads_to_deg`), não de onde ele vem.
 * O desenho aponta para o norte em 0°, e `rotate` é horário — mesma convenção do
 * azimute, então o ângulo entra direto.
 *
 * O traço é desenhado duas vezes: um contorno branco largo por baixo e a cor por
 * cima. Sem esse halo a seta some sobre o satélite, onde o fundo é escuro e
 * texturizado — é o mesmo recurso que os rótulos de rua usam.
 */
function windArrowIcon(L, reading) {
    const color = windColor(reading.speed_kmh);

    const html = '<svg class="samu-wind-arrow" width="36" height="36" viewBox="0 0 36 36"'
        + ` style="transform:rotate(${reading.spreads_to_deg}deg)">`
        + `<path d="${WIND_ARROW_SHAFT}" stroke="#fff" stroke-width="7" stroke-linecap="round" fill="none"/>`
        + `<path d="${WIND_ARROW_HEAD}" fill="#fff" stroke="#fff" stroke-width="5.5" stroke-linejoin="round"/>`
        + `<path d="${WIND_ARROW_SHAFT}" stroke="${color}" stroke-width="3" stroke-linecap="round" fill="none"/>`
        + `<path d="${WIND_ARROW_HEAD}" fill="${color}" stroke="${color}" stroke-width="1.5" stroke-linejoin="round"/>`
        + '</svg>';

    return L.divIcon({
        className: 'samu-wind-marker',
        html,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
    });
}

function windPopupHtml(reading) {
    const gusts = reading.gusts_kmh !== null && reading.gusts_kmh !== undefined
        ? `<br><span style="color:#6b7280;font-size:12px">Rajadas: ${reading.gusts_kmh} km/h</span>`
        : '';

    return '<div style="min-width:170px">'
        + '<strong style="font-size:13px">Vento agora</strong><br>'
        + `<span style="font-size:12px">Sopra para <strong>${windCardinal(reading.spreads_to_deg)}</strong>`
        + ` · ${reading.speed_kmh} km/h</span><br>`
        + `<span style="color:#6b7280;font-size:12px">Vem de ${windCardinal(reading.direction_from_deg)}`
        + ` (${reading.direction_from_deg}°)</span>${gusts}`
        + '</div>';
}

async function fetchWindReadings(windUrl, bounds) {
    const params = new URLSearchParams({
        min_lat: bounds.getSouth().toFixed(4),
        max_lat: bounds.getNorth().toFixed(4),
        min_lng: bounds.getWest().toFixed(4),
        max_lng: bounds.getEast().toFixed(4),
    });

    const res = await fetch(`${windUrl}?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!res.ok) {
        throw new Error('wind_fetch_failed');
    }

    return res.json();
}

// ---------------------------------------------------------------------------
// Cobertura territorial — polígonos IBGE + bases (dashboard e mapa tático)
// ---------------------------------------------------------------------------

function municipioGeoJsonPolygonStyle(feature) {
    const active = feature?.properties?.active ?? true;

    return {
        color: active ? '#2563eb' : '#94a3b8',
        weight: 1.5,
        opacity: active ? 0.85 : 0.5,
        fillColor: active ? '#3b82f6' : '#cbd5e1',
        fillOpacity: active ? 0.13 : 0.08,
    };
}

function municipioGeoJsonPopupHtml(p) {
    const bases = Array.isArray(p.bases) ? p.bases : [];
    const basesHtml = bases.map((b) => {
        const status = b.active
            ? '<span style="color:#16a34a">●</span>'
            : '<span style="color:#dc2626">●</span>';
        const phone = b.phone
            ? `<span style="color:#9ca3af;font-size:10px"> · ${b.phone}</span>`
            : '';

        return `<li style="display:flex;align-items:center;gap:4px;padding:2px 0">` +
            `${status} <span style="font-size:12px">${b.razao_social}</span>${phone}` +
            `</li>`;
    }).join('');

    const allInactive = bases.length > 0 && bases.every((b) => !b.active);

    return `<div style="min-width:200px;line-height:1.5;font-family:inherit">` +
        `<p style="font-weight:700;font-size:14px;margin:0 0 1px;color:#1e293b">${p.nome}</p>` +
        `<p style="color:#64748b;font-size:12px;margin:0 0 6px">${p.uf}` +
        (p.area_km2 ? ` &nbsp;·&nbsp; ${parseFloat(p.area_km2).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} km²` : '') +
        ` &nbsp;·&nbsp; IBGE ${p.codigo_ibge}</p>` +
        `<hr style="border:0;border-top:1px solid #e2e8f0;margin:4px 0">` +
        `<p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;margin:4px 0 2px">` +
        `${bases.length === 1 ? 'Base cadastrada' : 'Bases cadastradas'}</p>` +
        `<ul style="list-style:none;padding:0;margin:0">${basesHtml}</ul>` +
        (allInactive
            ? `<p style="color:#f59e0b;font-size:11px;margin:6px 0 0">⚠ Todas as bases estão inativas</p>`
            : '') +
        `</div>`;
}

function bindMunicipioGeoJsonFeature(L, feature, layer) {
    const p = feature.properties ?? {};
    const bases = Array.isArray(p.bases) ? p.bases : [];

    layer.bindTooltip(
        `<strong>${p.nome} — ${p.uf}</strong>` +
        (bases.length === 1
            ? `<br><span style="font-size:11px">${bases[0].razao_social}</span>`
            : `<br><span style="font-size:11px">${bases.length} bases cadastradas</span>`),
        { sticky: true, direction: 'top', offset: [0, -4] },
    );

    layer.bindPopup(L.popup({ maxWidth: 260 }).setContent(municipioGeoJsonPopupHtml(p)));

    layer.on('mouseover', function () {
        this.setStyle({ fillOpacity: 0.32, weight: 2.5 });
        this.bringToFront();
    });

    layer.on('mouseout', function () {
        const active = feature?.properties?.active ?? true;
        this.setStyle({ fillOpacity: active ? 0.13 : 0.08, weight: 1.5 });
    });

    layer.on('click', function () {
        this.openPopup();
    });
}

async function fetchMunicipiosGeoJson(featuresUrl) {
    const res = await fetch(featuresUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!res.ok) {
        throw new Error('municipios_geojson_fetch_failed');
    }

    return res.json();
}

function addMunicipiosGeoJsonLayer(L, map, fc) {
    return L.geoJSON(fc, {
        style: municipioGeoJsonPolygonStyle,
        onEachFeature: (feature, layer) => bindMunicipioGeoJsonFeature(L, feature, layer),
    }).addTo(map);
}

function registerDispatchMapAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuDispatchMapDone) return;
    window.__samuDispatchMapDone = true;

    Alpine.data('dispatchMap', (opts = {}) => ({
        map: null,
        _L: null,
        _pollTimer: null,

        // marcadores indexados por id / localização
        _incidentMarkers: {},
        _incidentData: {},
        _alertClusterMarkers: {},
        _vehicleMarkers: {},
        _vehicleData: {},
        _municipiosLayer: null,
        _vehiclesBoundsApplied: false,
        _routingControl: null,
        _routedVehicleId: null,
        _windLayer: null,
        _windVisible: false,
        _windTimer: null,
        _windMoveTimer: null,
        _windGeneration: 0,

        // dados iniciais (passados como JSON do blade)
        incidents: opts.incidents ?? [],
        alertClusters: opts.alertClusters ?? [],
        vehicles:  opts.vehicles  ?? [],
        incidentUrlBase: opts.incidentUrlBase ?? '',
        vehiclesUrl:     opts.vehiclesUrl     ?? '',
        municipiosUrl:   opts.municipiosUrl   ?? '',
        windUrl:         opts.windUrl         ?? '',
        nearestVehicleUrlBase: opts.nearestVehicleUrlBase ?? '',

        // painel lateral da ocorrência selecionada (reativo via Alpine)
        selectedIncidentId: null,
        panel: {
            open: false,
            loading: false,
            error: '',
            title: '',
            talao: '',
            statusLabel: '',
            statusOpen: true,
            lat: null,
            lng: null,
            url: '',
            vehiclePrefix: '',
            distance: '',
            duration: '',
            eta: '',
        },

        async init() {
            let L;
            try { L = await loadLeafletFromCdn(); } catch { return; }
            if (!this.$refs.dispatchMapEl) return;

            this._L = L;

            this.map = L.map(this.$refs.dispatchMapEl, {
                zoomControl: true,
                markerZoomAnimation: false,
                zoomAnimation: false,
                fadeAnimation: false,
            }).setView([-15.793889, -47.882778], 5);

            this._initWindLayer(L);

            await this._loadMunicipiosCoverage(L);

            this.incidents.forEach(i => this._addOrUpdateIncident(i));
            this.alertClusters.forEach(c => this._addOrUpdateAlertCluster(c));
            this.vehicles.forEach(v => this._addOrUpdateVehicle(v));

            this._fitToCoverage();
            requestAnimationFrame(() => this.map?.invalidateSize());

            // Reverb — caminho rápido (funciona quando queue+reverb estão rodando)
            this._subscribeReverb();

            // Polling direto ao Traccar via endpoint PHP — fallback confiável (30s)
            this._startPolling();
        },

        /**
         * Registra "Vento" como camada ligável no seletor. Começa desligada: só
         * busca dados quando o operador pede, e para de buscar quando desliga.
         */
        _initWindLayer(L) {
            if (!this.windUrl) {
                attachBaseLayers(L, this.map);

                return;
            }

            this._windLayer = L.layerGroup();

            attachBaseLayers(L, this.map, { 'Vento': this._windLayer });

            this.map.on('overlayadd', (e) => {
                if (e.layer !== this._windLayer) return;

                this._windVisible = true;
                this._loadWind();
                // O bloco `current` do provedor muda a cada 15 min; acompanhar de perto não traria dado novo.
                clearInterval(this._windTimer);
                this._windTimer = setInterval(() => this._loadWind(), 15 * 60 * 1000);
            });

            this.map.on('overlayremove', (e) => {
                if (e.layer !== this._windLayer) return;

                this._windVisible = false;
                clearInterval(this._windTimer);
                clearTimeout(this._windMoveTimer);
                this._windLayer.clearLayers();
            });

            // A grade é recortada pelo viewport, então precisa acompanhar a navegação.
            this.map.on('moveend', () => {
                if (!this._windVisible) return;

                clearTimeout(this._windMoveTimer);
                this._windMoveTimer = setTimeout(() => this._loadWind(), 400);
            });
        },

        async _loadWind() {
            if (!this._windVisible || !this.map || !this._windLayer) return;

            // Duas cargas podem correr juntas (refresh periódico durante um `moveend`).
            // Sem este selo, as duas limpariam e repovoariam a camada, duplicando as setas.
            const generation = ++this._windGeneration;

            let readings;
            try {
                readings = await fetchWindReadings(this.windUrl, this.map.getBounds());
            } catch {
                return; // silencioso — vento é contexto, o mapa segue funcionando sem ele
            }

            // Descarta se o operador desligou a camada ou se outra carga já assumiu.
            if (!this._windVisible || generation !== this._windGeneration) return;

            this._windLayer.clearLayers();

            readings.forEach((reading) => {
                this._L.marker([reading.lat, reading.lng], {
                    icon: windArrowIcon(this._L, reading),
                    // Fica abaixo de incidentes/viaturas: vento é pano de fundo, não alvo de clique.
                    zIndexOffset: -500,
                })
                    .bindPopup(windPopupHtml(reading))
                    .addTo(this._windLayer);
            });
        },

        async _loadMunicipiosCoverage(L) {
            if (!this.municipiosUrl || !this.map) {
                return;
            }

            try {
                const fc = await fetchMunicipiosGeoJson(this.municipiosUrl);
                const features = fc.features ?? [];

                if (features.length === 0) {
                    return;
                }

                this._municipiosLayer = addMunicipiosGeoJsonLayer(L, this.map, fc);
                this._municipiosLayer.bringToBack();
            } catch {
                // silencioso — cobertura territorial é contextual
            }
        },

        destroy() {
            clearInterval(this._pollTimer);
            clearInterval(this._windTimer);
            clearTimeout(this._windMoveTimer);
        },

        // ── Polling de posições (independe de queue/reverb) ─────────────────
        _startPolling() {
            if (!this.vehiclesUrl) return;

            // Primeira busca imediata
            this._fetchVehicles();

            // Depois a cada 30s
            this._pollTimer = setInterval(() => this._fetchVehicles(), 30_000);
        },

        async _fetchVehicles() {
            try {
                const res  = await fetch(this.vehiclesUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const list = await res.json();
                const activeIds = new Set(list.map((v) => String(v.vehicle_id)));

                Object.keys(this._vehicleMarkers).forEach((vehicleId) => {
                    if (!activeIds.has(String(vehicleId))) {
                        this._removeVehicle(vehicleId);
                    }
                });

                list.forEach(v => this._addOrUpdateVehicle(v));

                if (!this._vehiclesBoundsApplied && list.length > 0) {
                    this._fitToCoverage();
                    this._vehiclesBoundsApplied = true;
                }
            } catch {
                // silencioso — Traccar pode estar temporariamente indisponível
            }
        },

        // ── Reverb — sub-segundo quando funcionando ──────────────────────────
        _subscribeReverb() {
            const Echo = window.Echo;
            if (!Echo) return;

            // Reutiliza canal já subscrito pelo app.js (evita duplicata)
            const ch = Echo.private('operations.dispatch');

            ch.listen('.vehicle.position.updated', (e) => {
                this._addOrUpdateVehicle(e);
            });

            ch.listen('.incident.created', (e) => {
                if (e.lat && e.lng) {
                    this._addOrUpdateIncident({
                        id:     e.incident_id,
                        lat:    e.lat,
                        lng:    e.lng,
                        talao:  e.talao,
                        year:   e.dispatch_year,
                        nature: e.nature ?? '—',
                        status: e.status,
                        url:    this._incidentUrl(e.incident_id),
                    });
                }
            });

            ch.listen('.unit.dispatched', (e) => {
                const m = this._incidentMarkers[e.incident_id];
                if (m) {
                    m.setIcon(this._incidentIcon('#2563eb'));
                } else if (e.lat && e.lng) {
                    this._addOrUpdateIncident({
                        id: e.incident_id, lat: e.lat, lng: e.lng,
                        talao: '', year: '', nature: '—',
                        status: e.status, url: this._incidentUrl(e.incident_id),
                    });
                }
                this._fetchVehicles();
            });

            ch.listen('.unit.released', (e) => {
                this._fetchVehicles();
                this._removeIncident(e.incident_id);
            });

            ch.listen('.shift.updated', () => {
                this._fetchVehicles();
            });

            // Ocorrência encerrada/cancelada (relatório final, enfermagem ou cancelamento) — remove do mapa.
            ch.listen('.incident.finalized', (e) => {
                this._removeIncident(e.incident_id);
            });

            ch.listen('.operational.call-alert-cluster', (e) => {
                if (e.remove && e.location_key) {
                    this._removeAlertCluster(e.location_key);
                } else if (e.location_key && e.lat && e.lng) {
                    this._addOrUpdateAlertCluster(e);
                }
            });
        },

        _addOrUpdateIncident(inc) {
            const L = this._L;
            if (!L || !this.map || !inc.lat || !inc.lng) return;

            this._incidentData[inc.id] = inc;

            const color       = inc.status !== 'open' ? '#2563eb' : '#ef4444';
            const highlighted = String(this.selectedIncidentId) === String(inc.id);
            const existing    = this._incidentMarkers[inc.id];
            const popup       = `<div style="min-width:160px">
                <b>Talão ${inc.talao}/${inc.year}</b><br>
                <span style="color:#6b7280;font-size:12px">${inc.nature}</span><br>
                <a href="${inc.url}" style="color:#2563eb;font-size:12px">ver ocorrência →</a>
            </div>`;

            if (existing) {
                existing.setLatLng([inc.lat, inc.lng]);
                existing.getPopup()?.setContent(popup);
                existing.setIcon(this._incidentIcon(color, highlighted));
                return;
            }

            const marker = L.marker([inc.lat, inc.lng], {
                icon: this._incidentIcon(color, highlighted),
            }).addTo(this.map).bindPopup(popup);

            marker.on('click', () => this.selectIncident(inc.id));

            this._incidentMarkers[inc.id] = marker;
        },

        // ── Seleção da ocorrência → painel lateral + rota da viatura mais próxima ──
        selectIncident(incidentId) {
            const inc = this._incidentData[incidentId];
            if (!inc) return;

            const previousId = this.selectedIncidentId;
            this.selectedIncidentId = incidentId;

            // Restaura o ícone da ocorrência anteriormente destacada.
            if (previousId !== null && String(previousId) !== String(incidentId)) {
                this._refreshIncidentIcon(previousId);
            }
            this._refreshIncidentIcon(incidentId);

            this.panel = {
                open: true,
                loading: true,
                error: '',
                title: inc.nature ?? 'Ocorrência',
                talao: `${inc.talao ?? ''}/${inc.year ?? ''}`,
                statusLabel: inc.status === 'open' ? 'Aberta' : 'Despachada',
                statusOpen: inc.status === 'open',
                lat: Number(inc.lat),
                lng: Number(inc.lng),
                url: inc.url ?? this._incidentUrl(incidentId),
                vehiclePrefix: '',
                distance: '',
                duration: '',
                eta: '',
            };

            this._routedVehicleId = null;
            this._refreshVehiclePopups();
            this._resolveNearestAndRoute(incidentId);
        },

        closePanel() {
            const previousId = this.selectedIncidentId;
            this.selectedIncidentId = null;
            this.panel.open = false;
            this._routedVehicleId = null;
            this._clearRoute();
            if (previousId !== null) {
                this._refreshIncidentIcon(previousId);
            }
            this._refreshVehiclePopups();
        },

        _refreshIncidentIcon(incidentId) {
            const marker = this._incidentMarkers[incidentId];
            const inc    = this._incidentData[incidentId];
            if (!marker || !inc) return;

            const color       = inc.status !== 'open' ? '#2563eb' : '#ef4444';
            const highlighted = String(this.selectedIncidentId) === String(incidentId);
            marker.setIcon(this._incidentIcon(color, highlighted));
        },

        async _resolveNearestAndRoute(incidentId) {
            this._clearRoute();

            if (!this.nearestVehicleUrlBase) {
                this.panel.loading = false;
                this.panel.error = 'Roteamento indisponível.';
                return;
            }

            let nearest = null;
            try {
                const url = this.nearestVehicleUrlBase.replace('__ID__', incidentId);
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    const json = await res.json();
                    nearest = json.nearest ?? null;
                }
            } catch {
                // tratado abaixo como ausência de viatura
            }

            // A ocorrência pode ter mudado enquanto a requisição estava em voo.
            if (String(this.selectedIncidentId) !== String(incidentId)) {
                return;
            }

            if (!nearest) {
                this.panel.loading = false;
                this.panel.error = 'Nenhuma viatura disponível para roteamento.';
                return;
            }

            this._routedVehicleId = nearest.vehicle_id ?? null;
            this.panel.vehiclePrefix = nearest.prefix ?? 'Viatura';
            this._refreshVehiclePopups();
            await this._drawRoute(incidentId, nearest);
        },

        async _drawRoute(incidentId, vehicle) {
            const inc = this._incidentData[incidentId];
            if (!inc || !this.map) {
                this.panel.loading = false;
                return;
            }

            let L;
            try {
                L = await loadLeafletRoutingMachineFromCdn();
            } catch {
                this.panel.loading = false;
                this.panel.error = 'Não foi possível carregar o roteamento.';
                return;
            }

            if (String(this.selectedIncidentId) !== String(incidentId)) {
                return;
            }

            this._clearRoute();

            this._routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(Number(vehicle.lat), Number(vehicle.lng)),
                    L.latLng(Number(inc.lat), Number(inc.lng)),
                ],
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1',
                }),
                fitSelectedRoutes: true,
                addWaypoints: false,
                draggableWaypoints: false,
                show: false,
                routeWhileDragging: false,
                lineOptions: {
                    addWaypoints: false,
                    styles: [{ color: '#2563eb', weight: 5, opacity: 0.85 }],
                },
                createMarker: () => null,
            }).addTo(this.map);

            this._routingControl.on('routesfound', (e) => {
                if (String(this.selectedIncidentId) !== String(incidentId)) return;
                const route = e.routes?.[0];
                if (!route) return;

                const meters  = route.summary?.totalDistance ?? 0;
                const seconds = route.summary?.totalTime ?? 0;

                this.panel.distance = this._formatKm(meters);
                this.panel.duration = this._formatDuration(seconds);
                this.panel.eta      = this._formatEta(seconds);
                this.panel.loading  = false;
                this.panel.error    = '';

                // Reflete a distância real por vias no popup da viatura roteada.
                this._refreshVehiclePopups();
            });

            this._routingControl.on('routingerror', () => {
                if (String(this.selectedIncidentId) !== String(incidentId)) return;
                this.panel.loading = false;
                this.panel.error = 'Não foi possível traçar a rota até a ocorrência.';
            });
        },

        _clearRoute() {
            if (this._routingControl && this.map) {
                this.map.removeControl(this._routingControl);
            }
            this._routingControl = null;
        },

        _formatKm(meters) {
            return `${(meters / 1000).toFixed(1).replace('.', ',')} km`;
        },

        _formatDuration(seconds) {
            const totalMin = Math.max(1, Math.round(seconds / 60));
            if (totalMin < 60) {
                return `${totalMin} min`;
            }
            const hours = Math.floor(totalMin / 60);
            const mins  = totalMin % 60;
            return mins > 0 ? `${hours} h ${mins} min` : `${hours} h`;
        },

        _formatEta(seconds) {
            const eta = new Date(Date.now() + seconds * 1000);
            return eta.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        },

        _addOrUpdateAlertCluster(cluster) {
            const L = this._L;
            if (!L || !this.map || !cluster?.location_key || !cluster.lat || !cluster.lng) {
                return;
            }

            const count   = cluster.count ?? (cluster.alerts?.length ?? 1);
            const stacked = count > 1;
            const popup     = this._alertClusterPopup(cluster, stacked, count);
            const existing  = this._alertClusterMarkers[cluster.location_key];

            if (existing) {
                existing.setLatLng([cluster.lat, cluster.lng]);
                existing.getPopup()?.setContent(popup);
                existing.setIcon(this._alertClusterIcon(stacked, count));
                return;
            }

            this._alertClusterMarkers[cluster.location_key] = L.marker([cluster.lat, cluster.lng], {
                icon: this._alertClusterIcon(stacked, count),
                zIndexOffset: 200,
            }).addTo(this.map).bindPopup(popup);
        },

        _removeAlertCluster(locationKey) {
            const marker = this._alertClusterMarkers[locationKey];
            if (!marker) {
                return;
            }
            marker.remove();
            delete this._alertClusterMarkers[locationKey];
        },

        _alertClusterPopup(cluster, stacked, count) {
            const locationKey = cluster.location_key ?? '';
            const alerts      = cluster.alerts ?? [];
            const accent      = stacked ? '#7c3aed' : '#d97706';

            const clusterActions = `
                <div style="margin-top:10px;display:flex;flex-direction:row;gap:6px">
                    <button type="button" onclick="window.__samuAlertDetails('${locationKey}')"
                        style="flex:1;padding:5px 8px;font-size:11px;font-weight:600;border:none;border-radius:6px;background:#f59e0b;color:#fff;cursor:pointer">
                        Detalhes
                    </button>
                    <button type="button" onclick="window.__samuAlertAction('abort-cluster','${locationKey}')"
                        style="flex:1;padding:5px 8px;font-size:11px;font-weight:600;border:none;border-radius:6px;background:#ef4444;color:#fff;cursor:pointer">
                        Abortar
                    </button>
                    <button type="button" onclick="window.__samuAlertAction('create-cluster','${locationKey}')"
                        style="flex:1;padding:5px 8px;font-size:11px;font-weight:600;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer">
                        Ocorrência
                    </button>
                </div>`;

            if (stacked) {
                const counterBadge = `<span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:9999px;background:#1e293b;color:#fff;font-size:12px;font-weight:700">${count}</span>`;
                const callList = alerts.map((alert) => this._alertPopupRow(alert, accent)).join('');

                return `<div style="min-width:200px;max-width:260px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        ${counterBadge}
                        <b style="font-size:12px;color:${accent}">Chamadas neste local</b>
                    </div>
                    <ul style="list-style:none;margin:0;padding:0;max-height:140px;overflow-y:auto">${callList}</ul>
                    ${clusterActions}
                </div>`;
            }

            const alert = alerts[0];
            if (!alert) {
                return '';
            }

            return `<div style="min-width:180px">
                ${this._alertPopupRow(alert, '#d97706', true)}
                ${clusterActions}
            </div>`;
        },

        _alertTemperatureLabel(alert) {
            const raw = alert.temperature ?? alert.metadata?.temperature;
            if (raw === null || raw === undefined || raw === '') {
                return '—';
            }
            const value = Number(raw);
            return Number.isFinite(value) ? `${value.toFixed(2)}°` : '—';
        },

        _alertPopupRow(alert, accent, block = false) {
            const temperature = this._escapeHtml(this._alertTemperatureLabel(alert));
            const reference   = alert.external_reference
                ? `<span style="color:#6b7280;font-size:11px">${this._escapeHtml(alert.external_reference)}</span>`
                : '<span style="color:#9ca3af;font-size:11px">—</span>';

            if (block) {
                return `<div>
                    <b style="color:${accent};font-size:14px">${temperature}</b>
                    <div style="margin-top:4px">${reference}</div>
                </div>`;
            }

            return `<li style="padding:4px 0;border-bottom:1px solid #f1f5f9">
                <span style="color:${accent};font-weight:600;font-size:12px">${temperature}</span>
                <span style="color:#94a3b8;font-size:11px"> · </span>
                ${reference}
            </li>`;
        },

        _alertClusterIcon(stacked, count) {
            const L       = this._L;
            const color   = stacked ? '#8b5cf6' : '#f59e0b';
            const pulse   = stacked ? 'samu-alert-monitor-pulse' : 'samu-alert-pulse';
            const dotSize = 14;
            const size    = 20;
            const badge   = stacked
                ? `<span style="position:absolute;right:-2px;top:-2px;min-width:14px;height:14px;padding:0 3px;border-radius:9999px;background:#1e293b;color:#fff;font-size:9px;font-weight:700;line-height:14px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.35);pointer-events:none">${count}</span>`
                : '';

            return L.divIcon({
                className: 'samu-map-marker samu-alert-marker',
                html: `<div style="position:relative;width:${size}px;height:${size}px;overflow:visible;line-height:0">` +
                    `<div class="samu-alert-dot" style="position:absolute;left:50%;top:50%;width:${dotSize}px;height:${dotSize}px;margin-left:-${dotSize / 2}px;margin-top:-${dotSize / 2}px;border-radius:50%;background:${color};animation:${pulse} 1.5s ease-in-out infinite"></div>` +
                    `${badge}</div>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
            });
        },

        _escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        _removeVehicle(vehicleId) {
            const marker = this._vehicleMarkers[vehicleId];
            if (!marker) {
                return;
            }

            marker.remove();
            delete this._vehicleMarkers[vehicleId];
            delete this._vehicleData[vehicleId];
        },

        _removeIncident(incidentId) {
            const marker = this._incidentMarkers[incidentId];
            if (marker) {
                marker.remove();
                delete this._incidentMarkers[incidentId];
            }
            delete this._incidentData[incidentId];

            if (String(this.selectedIncidentId) === String(incidentId)) {
                this.closePanel();
            }
        },

        _vehicleColor(veh) {
            return veh.on_dispatch ? '#22c55e' : '#64748b';
        },

        _vehicleStatusLabel(veh) {
            return veh.on_dispatch ? 'Em atendimento' : 'Disponível na base';
        },

        _addOrUpdateVehicle(veh) {
            const L = this._L;
            const lat = Number(veh.lat);
            const lng = Number(veh.lng);
            if (!L || !this.map || !Number.isFinite(lat) || !Number.isFinite(lng) || (lat === 0 && lng === 0)) return;

            this._vehicleData[veh.vehicle_id] = veh;

            const color    = this._vehicleColor(veh);
            const existing = this._vehicleMarkers[veh.vehicle_id];
            const popup    = this._vehiclePopupHtml(veh);

            if (existing) {
                existing.setLatLng([lat, lng]);
                existing.getPopup()?.setContent(popup);
                existing.setIcon(this._vehicleIcon(color, veh.prefix));
                return;
            }

            this._vehicleMarkers[veh.vehicle_id] = L.marker([lat, lng], {
                icon: this._vehicleIcon(color, veh.prefix),
                zIndexOffset: 100,
            }).addTo(this.map).bindPopup(popup);
        },

        _vehiclePopupHtml(veh) {
            const distance = this._vehicleDistanceLabel(veh);

            return `<div style="min-width:140px">
                <b>${veh.prefix}</b><br>
                <span style="color:#6b7280;font-size:12px">${this._vehicleStatusLabel(veh)}</span><br>
                <span style="color:#9ca3af;font-size:11px">${(veh.speed_kmh ?? 0).toFixed(0)} km/h</span>
                ${veh.fix_time ? `<br><span style="color:#9ca3af;font-size:11px">${veh.fix_time}</span>` : ''}
                ${distance ? `<br><span style="color:#2563eb;font-size:12px;font-weight:600">${distance}</span>` : ''}
            </div>`;
        },

        /** Distância da viatura até a ocorrência selecionada (rota real p/ viatura roteada; linha reta p/ demais). */
        _vehicleDistanceLabel(veh) {
            if (this.selectedIncidentId === null) return '';

            const inc = this._incidentData[this.selectedIncidentId];
            if (!inc) return '';

            if (String(this._routedVehicleId) === String(veh.vehicle_id) && this.panel.distance) {
                return `${this.panel.distance} até a ocorrência`;
            }

            const km = this._haversineKm(Number(veh.lat), Number(veh.lng), Number(inc.lat), Number(inc.lng));
            if (!Number.isFinite(km)) return '';

            return `${km.toFixed(1).replace('.', ',')} km da ocorrência (linha reta)`;
        },

        _haversineKm(lat1, lng1, lat2, lng2) {
            const rad = (deg) => (deg * Math.PI) / 180;
            const a = Math.sin(rad(lat2 - lat1) / 2) ** 2
                + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;

            return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
        },

        _refreshVehiclePopups() {
            Object.entries(this._vehicleMarkers).forEach(([vehicleId, marker]) => {
                const veh = this._vehicleData[vehicleId];
                if (veh) {
                    marker.getPopup()?.setContent(this._vehiclePopupHtml(veh));
                }
            });
        },

        _incidentIcon(color, highlighted = false) {
            const L = this._L;
            const size = highlighted ? 20 : 14;
            const half = size / 2;
            const ring = highlighted
                ? `box-shadow:0 0 0 3px #fff,0 0 0 6px ${color};`
                : `box-shadow:0 0 0 2px ${color};`;

            return L.divIcon({
                className: 'samu-map-marker',
                html: `<div class="samu-map-marker-visual" style="width:${size}px;height:${size}px;margin:0;border-radius:50%;background:${color};border:2px solid #fff;${ring}"></div>`,
                iconSize: [size, size],
                iconAnchor: [half, half],
            });
        },

        _vehicleIcon(color, prefix) {
            const L     = this._L;
            const label = (prefix ?? '').slice(0, 6);
            const width = 62;
            const height = 46;

            return L.divIcon({
                className: 'samu-map-marker',
                html: `<div style="width:${width}px;height:${height}px;margin:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;line-height:0">` +
                    `<div style="display:flex;align-items:center;gap:4px;background:${color};color:#fff;font-size:12px;font-weight:700;padding:4px 8px;border-radius:7px;white-space:nowrap;border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,.45)">` +
                        `<svg viewBox="0 0 24 24" width="14" height="14" fill="#fff" style="flex:none"><path d="M3 6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2h2.4a2 2 0 0 1 1.6.8l1.6 2.13a2 2 0 0 1 .4 1.2V16a1 1 0 0 1-1 1h-1.05a2.5 2.5 0 0 1-4.9 0H9.95a2.5 2.5 0 0 1-4.9 0H4a1 1 0 0 1-1-1V6Zm8 1h-2v2H7v2h2v2h2v-2h2V9h-2V7Z"/></svg>` +
                        `<span>${label}</span>` +
                    `</div>` +
                    `<div style="width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-top:9px solid #fff;margin-top:-1px"></div>` +
                    `</div>`,
                iconSize: [width, height],
                iconAnchor: [width / 2, height],
            });
        },

        _incidentUrl(id) {
            return this.incidentUrlBase.replace('__ID__', id);
        },

        /** Centraliza o mapa na cobertura territorial e inclui marcadores operacionais. */
        _fitToCoverage() {
            const L = this._L;
            if (!this.map || !L) {
                return;
            }

            const bounds = L.latLngBounds([]);

            if (this._municipiosLayer) {
                const coverageBounds = this._municipiosLayer.getBounds();
                if (coverageBounds.isValid()) {
                    bounds.extend(coverageBounds);
                }
            }

            Object.values(this._incidentMarkers).forEach((marker) => bounds.extend(marker.getLatLng()));
            Object.values(this._alertClusterMarkers).forEach((marker) => bounds.extend(marker.getLatLng()));
            Object.values(this._vehicleMarkers).forEach((marker) => bounds.extend(marker.getLatLng()));

            if (bounds.isValid()) {
                this.map.fitBounds(bounds, { padding: [32, 32], maxZoom: 14, animate: false });

                return;
            }

            this.map.setView([-15.793889, -47.882778], 5, { animate: false });
        },
    }));
}

function registerDashboardFireMapAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuDashboardFireMapDone) return;
    window.__samuDashboardFireMapDone = true;

    Alpine.data('dashboardFireMap', (opts = {}) => ({
        map: null,
        _L: null,
        _focoMarkers: {},
        _loaded: false,
        focosUrl: opts.focosUrl ?? '',
        scarUrl: opts.scarUrl ?? '',
        countLabels: opts.countLabels ?? {
            zero: 'Nenhum foco no mapa',
            one: '1 foco no mapa',
            other: ':count focos no mapa',
            idle: 'Informe o período e carregue o mapa',
        },

        countLabel(count) {
            if (! this._loaded) return this.countLabels.idle;
            if (count === 0) return this.countLabels.zero;
            if (count === 1) return this.countLabels.one;

            return this.countLabels.other.replace(':count', String(count));
        },

        async init() {
            let L;
            try { L = await loadLeafletFromCdn(); } catch { return; }
            if (!this.$refs.fireMapEl) return;

            this._L = L;

            this.map = L.map(this.$refs.fireMapEl, { zoomControl: true })
                .setView([-15.793889, -47.882778], 5);

            attachBaseLayers(L, this.map);

            requestAnimationFrame(() => this.map?.invalidateSize());
        },

        setFocos(focos) {
            this._loaded = true;
            this.focos = focos ?? [];

            Object.values(this._focoMarkers).forEach((marker) => marker.remove());
            this._focoMarkers = {};

            this.focos.forEach((f) => this._addOrUpdateFoco(f));
            this._updateCount(this.focos.length);

            if (this.focos.length === 0) {
                this.map?.setView([-15.793889, -47.882778], 5);
            } else {
                this._fitAll();
            }

            requestAnimationFrame(() => this.map?.invalidateSize());
        },

        _addOrUpdateFoco(foco) {
            const L = this._L;
            if (!L || !this.map || !foco.lat || !foco.lng) return;

            const location = foco.municipio
                ? `${foco.municipio}${foco.estado ? ` / ${foco.estado}` : ''}`
                : `${Number(foco.lat).toFixed(5)}, ${Number(foco.lng).toFixed(5)}`;

            const popup = `<div style="min-width:180px">
                <b>${location}</b><br>
                <span style="color:#6b7280;font-size:12px">${foco.satelite ?? '—'} · ${foco.sensor ?? '—'}</span><br>
                ${foco.bioma ? `<span style="color:#6b7280;font-size:12px">${foco.bioma}</span><br>` : ''}
                ${foco.detected_at ? `<span style="color:#9ca3af;font-size:11px">${foco.detected_at}</span><br>` : ''}
                ${foco.frp !== null && foco.frp !== undefined ? `<span style="color:#9ca3af;font-size:11px">FRP: ${Number(foco.frp).toFixed(2)} MW</span><br>` : ''}
                ${foco.temperatura_brilho !== null && foco.temperatura_brilho !== undefined ? `<span style="color:#9ca3af;font-size:11px">Temp. brilho: ${Number(foco.temperatura_brilho).toFixed(1)} K</span><br>` : ''}
                ${foco.confianca !== null && foco.confianca !== undefined ? `<span style="color:#9ca3af;font-size:11px">Confiança: ${foco.confianca}%</span><br>` : ''}
                ${foco.risco_fogo !== null && foco.risco_fogo !== undefined ? `<span style="color:#9ca3af;font-size:11px">Risco: ${Number(foco.risco_fogo).toFixed(2)}</span><br>` : ''}
                ${this.scarUrl ? `<a href="${this._scarLink(foco)}" style="display:inline-block;margin-top:6px;font-size:12px;color:#b91c1c;font-weight:600;text-decoration:none">🔥 Analisar cicatriz</a>` : ''}
            </div>`;

            const existing = this._focoMarkers[foco.id];

            if (existing) {
                existing.setLatLng([foco.lat, foco.lng]);
                existing.getPopup()?.setContent(popup);
                return;
            }

            this._focoMarkers[foco.id] = L.marker([foco.lat, foco.lng], {
                icon: this._focoIcon(),
            }).addTo(this.map).bindPopup(popup);
        },

        _scarLink(foco) {
            const params = new URLSearchParams({ lat: foco.lat, lng: foco.lng });
            if (foco.municipio) params.set('municipio', foco.municipio);
            if (foco.estado) params.set('estado', foco.estado);
            if (foco.bioma) params.set('bioma', foco.bioma);

            return `${this.scarUrl}?${params.toString()}`;
        },

        _focoIcon() {
            const L = this._L;
            const color = '#f97316';

            return L.divIcon({
                className: '',
                html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 0 2px ${color}"></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7],
            });
        },

        _firstPoint() {
            const f = (this.focos ?? []).find(x => x.lat && x.lng);
            return f ? [f.lat, f.lng] : null;
        },

        _fitAll() {
            const all = Object.values(this._focoMarkers);
            if (all.length === 0 || !this._L || !this.map) return;

            const group = this._L.featureGroup(all);
            this.map.fitBounds(group.getBounds().pad(0.15));
        },

        _updateCount(count) {
            const el = this.$refs.focoCount;
            if (!el) return;

            el.textContent = this.countLabel(count);
        },
    }));
}

function registerFireScarMapAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuFireScarMapDone) return;
    window.__samuFireScarMapDone = true;

    Alpine.data('fireScarMap', (opts = {}) => ({
        map: null,
        _L: null,
        lat: Number(opts.lat ?? 0),
        lng: Number(opts.lng ?? 0),
        geojson: opts.geojson ?? null,
        color: opts.color ?? '#e34a33',

        async init() {
            let L;
            try { L = await loadLeafletFromCdn(); } catch { return; }
            if (!this.$refs.scarMapEl) return;

            this._L = L;

            this.map = L.map(this.$refs.scarMapEl, { zoomControl: true })
                .setView([this.lat || -15.79, this.lng || -47.88], this.lat ? 13 : 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            if (this.lat && this.lng) {
                L.marker([this.lat, this.lng]).addTo(this.map);
            }

            if (this.geojson) {
                try {
                    const layer = L.geoJSON(this.geojson, {
                        style: {
                            color: this.color,
                            weight: 2,
                            fillColor: this.color,
                            fillOpacity: 0.35,
                        },
                    }).addTo(this.map);

                    const bounds = layer.getBounds();
                    if (bounds.isValid()) {
                        this.map.fitBounds(bounds.pad(0.2));
                    }
                } catch { /* geometria inválida — mantém o marcador */ }
            }

            requestAnimationFrame(() => this.map?.invalidateSize());
        },
    }));
}

document.addEventListener('alpine:init', () => {
    registerDispatchMapAlpine();
    registerDashboardFireMapAlpine();
    registerFireScarMapAlpine();
});
document.addEventListener('livewire:init', () => {
    registerDispatchMapAlpine();
    registerDashboardFireMapAlpine();
    registerFireScarMapAlpine();
});

// ---------------------------------------------------------------------------
// municipioIbgeModal — modal com mapa de coordenadas IBGE (cadastro de bases)
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// municipiosOverviewMap — mapa de cobertura territorial do dashboard
// Exibe polígonos de todos os municípios cadastrados com vínculo IBGE.
// ---------------------------------------------------------------------------

function registerMunicipiosOverviewMapAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuMunicipiosOverviewMapDone) return;
    window.__samuMunicipiosOverviewMapDone = true;

    Alpine.data('municipiosOverviewMap', (opts = {}) => ({
        _L: null,
        map: null,
        geojsonLayer: null,
        loading: true,
        empty: false,
        error: false,
        count: 0,
        featuresUrl: opts.featuresUrl ?? '',

        async init() {
            let L;
            try { L = await loadLeafletFromCdn(); } catch { this.loading = false; this.error = true; return; }
            if (!this.$refs.mapEl) return;

            this._L = L;

            this.map = L.map(this.$refs.mapEl, { zoomControl: true })
                .setView([-15.793889, -47.882778], 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            requestAnimationFrame(() => this.map?.invalidateSize());

            await this._loadFeatures(L);
        },

        async _loadFeatures(L) {
            if (!this.featuresUrl) { this.loading = false; return; }

            this.loading = true;

            try {
                const fc = await fetchMunicipiosGeoJson(this.featuresUrl);
                const features = fc.features ?? [];

                if (features.length === 0) {
                    this.empty = true;
                    return;
                }

                this.count = features.length;

                this.geojsonLayer = addMunicipiosGeoJsonLayer(L, this.map, fc);

                const bounds = this.geojsonLayer.getBounds();
                if (bounds.isValid()) {
                    this.map.fitBounds(bounds, { padding: [24, 24] });
                }

                requestAnimationFrame(() => this.map?.invalidateSize());

            } catch {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerMunicipiosOverviewMapAlpine(); });
document.addEventListener('livewire:init', () => { registerMunicipiosOverviewMapAlpine(); });

// ---------------------------------------------------------------------------

function registerMunicipioIbgeModalAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuMunicipioIbgeModalDone) return;
    window.__samuMunicipioIbgeModalDone = true;

    Alpine.data('municipioIbgeModal', () => ({
        open: false,
        ibge: {},
        loadingPolygon: false,

        // Leaflet internals
        _L: null,
        map: null,
        marker: null,
        polygonLayer: null,

        init() {
            window.addEventListener('municipio-ibge-map-open', (e) => {
                this.ibge = e.detail ?? {};
                this.open = true;
                this.$nextTick(async () => await this._initOrUpdate());
            });
        },

        close() {
            this.open = false;
        },

        async _initOrUpdate() {
            let L;
            try { L = await loadLeafletFromCdn(); } catch { return; }
            if (!this.$refs.mapEl) return;

            this._L = L;

            const lat = parseFloat(this.ibge.lat) || -15.793889;
            const lng = parseFloat(this.ibge.lng) || -47.882778;

            // ── Inicializar mapa na primeira abertura ──────────────────────
            if (!this.map) {
                this.map = L.map(this.$refs.mapEl, { zoomControl: true }).setView([lat, lng], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(this.map);

                const icon = L.divIcon({
                    className: '',
                    html: `<div style="width:12px;height:12px;border-radius:50%;background:#2563eb;border:2px solid #fff;box-shadow:0 0 0 2px #2563eb;z-index:10"></div>`,
                    iconSize: [12, 12],
                    iconAnchor: [6, 6],
                });
                this.marker = L.marker([lat, lng], { icon, zIndexOffset: 500 }).addTo(this.map);
            } else {
                // Reposicionar o marcador ao trocar município
                this.marker.setLatLng([lat, lng]);
            }

            const popup =
                `<div style="min-width:150px">` +
                `<b>${this.ibge.nome ?? ''}</b><br>` +
                `<span style="color:#6b7280;font-size:12px">${this.ibge.uf ?? ''} &bull; IBGE ${this.ibge.codigo ?? '&mdash;'}</span><br>` +
                `<span style="color:#9ca3af;font-size:11px">${parseFloat(this.ibge.lat || 0).toFixed(6)}, ${parseFloat(this.ibge.lng || 0).toFixed(6)}</span>` +
                `</div>`;

            this.marker.bindPopup(popup);

            setTimeout(() => this.map?.invalidateSize(), 80);

            // ── Carregar e desenhar polígono ───────────────────────────────
            if (this.ibge.geojsonUrl) {
                await this._loadPolygon();
            } else {
                // Sem URL: apenas centraliza no marcador
                this.map.setView([lat, lng], 10);
            }
        },

        async _loadPolygon() {
            const L = this._L;
            if (!L || !this.map) return;

            // Remover polígono anterior
            if (this.polygonLayer) {
                this.polygonLayer.remove();
                this.polygonLayer = null;
            }

            this.loadingPolygon = true;

            try {
                const res = await fetch(this.ibge.geojsonUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!res.ok) return;

                const feature = await res.json();

                if (!feature?.geometry) {
                    // Geometria não disponível — centraliza no marcador
                    this.map.setView(
                        [parseFloat(this.ibge.lat) || -15.793889, parseFloat(this.ibge.lng) || -47.882778],
                        10
                    );
                    this.marker.openPopup();
                    return;
                }

                this.polygonLayer = L.geoJSON(feature, {
                    style: {
                        color: '#2563eb',
                        weight: 2,
                        opacity: 0.85,
                        fillColor: '#3b82f6',
                        fillOpacity: 0.12,
                    },
                }).addTo(this.map);

                const bounds = this.polygonLayer.getBounds();
                if (bounds.isValid()) {
                    this.map.fitBounds(bounds, { padding: [24, 24] });
                }

                // Abrir popup sobre o marcador após zoom
                setTimeout(() => {
                    this.map?.invalidateSize();
                    this.marker?.openPopup();
                }, 120);

            } catch {
                // silencioso — geometria indisponível
                this.map?.setView(
                    [parseFloat(this.ibge.lat) || -15.793889, parseFloat(this.ibge.lng) || -47.882778],
                    10
                );
            } finally {
                this.loadingPolygon = false;
            }
        },
    }));
}

document.addEventListener('alpine:init', () => {
    registerMunicipioIbgeModalAlpine();
});
document.addEventListener('livewire:init', () => {
    registerMunicipioIbgeModalAlpine();
});

// ---------------------------------------------------------------------------
// municipioForm — máscaras e validações do formulário de cadastro de bases
// ---------------------------------------------------------------------------

function registerMunicipioFormAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuMunicipioFormDone) return;
    window.__samuMunicipioFormDone = true;

    Alpine.data('municipioForm', () => ({
        cnpjError: '',
        phoneError: '',
        cepLoading: false,
        cepError: '',

        // ── Telefone ────────────────────────────────────────────────────────
        maskPhone(event) {
            let v = event.target.value.replace(/\D/g, '').slice(0, 11);
            if (v.length === 0) { event.target.value = ''; this.phoneError = ''; return; }

            if (v.length <= 10) {
                // fixo: (XX) XXXX-XXXX
                v = v.replace(/^(\d{0,2})(\d{0,4})(\d{0,4})$/, (_, a, b, c) => {
                    let r = '';
                    if (a) r += '(' + a;
                    if (a.length === 2) r += ') ';
                    if (b) r += b;
                    if (b.length === 4 && c) r += '-' + c;
                    return r;
                });
            } else {
                // celular: (XX) XXXXX-XXXX
                v = v.replace(/^(\d{2})(\d{5})(\d{0,4})$/, (_, a, b, c) => {
                    let r = '(' + a + ') ' + b;
                    if (c) r += '-' + c;
                    return r;
                });
            }

            event.target.value = v;
            this.validatePhone(event.target.value);
        },

        validatePhone(val) {
            const digits = val.replace(/\D/g, '');
            if (digits.length === 0) { this.phoneError = ''; return; }
            if (digits.length < 10 || digits.length > 11) {
                this.phoneError = 'Telefone inválido';
            } else {
                this.phoneError = '';
            }
        },

        // ── CNPJ / CPF ──────────────────────────────────────────────────────
        maskCnpj(event) {
            const digits = event.target.value.replace(/\D/g, '').slice(0, 14);
            let v = digits;

            if (digits.length <= 11) {
                // CPF: XXX.XXX.XXX-XX
                v = digits
                    .replace(/^(\d{3})(\d)/, '$1.$2')
                    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
            } else {
                // CNPJ: XX.XXX.XXX/0001-XX
                v = digits
                    .replace(/^(\d{2})(\d)/, '$1.$2')
                    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1/$2')
                    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            }

            event.target.value = v;
        },

        validateCnpjBlur(event) {
            const digits = event.target.value.replace(/\D/g, '');
            if (digits.length === 0) { this.cnpjError = ''; return; }

            if (digits.length === 11) {
                this.cnpjError = this._validCpf(digits) ? '' : 'CPF inválido';
            } else if (digits.length === 14) {
                this.cnpjError = this._validCnpj(digits) ? '' : 'CNPJ inválido';
            } else {
                this.cnpjError = 'CNPJ inválido';
            }
        },

        _validCpf(d) {
            if (/^(\d)\1{10}$/.test(d)) return false;
            let s = 0;
            for (let i = 0; i < 9; i++) s += parseInt(d[i]) * (10 - i);
            let r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
            if (r !== parseInt(d[9])) return false;
            s = 0;
            for (let i = 0; i < 10; i++) s += parseInt(d[i]) * (11 - i);
            r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
            return r === parseInt(d[10]);
        },

        _validCnpj(d) {
            if (/^(\d)\1{13}$/.test(d)) return false;
            const calc = (d, n) => {
                let s = 0, pos = n - 7;
                for (let i = 0; i < n; i++) {
                    s += parseInt(d[i]) * pos--;
                    if (pos < 2) pos = 9;
                }
                const r = s % 11;
                return r < 2 ? 0 : 11 - r;
            };
            return calc(d, 12) === parseInt(d[12]) && calc(d, 13) === parseInt(d[13]);
        },

        // ── CEP / ViaCEP ────────────────────────────────────────────────────
        maskCep(event) {
            let v = event.target.value.replace(/\D/g, '').slice(0, 8);
            if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
            event.target.value = v;
            if (this.cepError) this.cepError = '';
        },

        async fetchCep(event) {
            const digits = event.target.value.replace(/\D/g, '');
            if (digits.length !== 8) {
                if (digits.length > 0) this.cepError = 'CEP deve ter 8 dígitos';
                return;
            }

            this.cepLoading = true;
            this.cepError = '';

            try {
                const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
                if (!res.ok) throw new Error('http');
                const data = await res.json();

                if (data.erro) {
                    this.cepError = 'CEP não encontrado';
                    return;
                }

                this.$wire.set('address',  data.logradouro ?? '');
                this.$wire.set('district', data.bairro     ?? '');
                this.$wire.set('city',     data.localidade ?? '');
                this.$wire.set('state',    data.uf         ?? '');
            } catch {
                this.cepError = 'Erro ao consultar o CEP';
            } finally {
                this.cepLoading = false;
            }
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerMunicipioFormAlpine(); });
document.addEventListener('livewire:init', () => { registerMunicipioFormAlpine(); });

// ---------------------------------------------------------------------------
// vehicleForm — máscara de placa (padrão antigo AAA-1111 e Mercosul AAA-1A11)
// ---------------------------------------------------------------------------

function registerVehicleFormAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuVehicleFormDone) return;
    window.__samuVehicleFormDone = true;

    Alpine.data('vehicleForm', () => ({
        plateError: '',

        maskPlate(event) {
            // Remove tudo que não for letra ou dígito e limita a 7 chars úteis
            let raw = event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);

            // Insere o traço após as 3 primeiras letras
            let formatted = raw.length > 3 ? raw.slice(0, 3) + '-' + raw.slice(3) : raw;

            event.target.value = formatted;
            this._validatePlate(raw);
        },

        _validatePlate(raw) {
            if (raw.length === 0) { this.plateError = ''; return; }
            if (raw.length < 7)   { this.plateError = ''; return; } // digitando ainda

            // Padrão antigo: ABC1234
            const old       = /^[A-Z]{3}[0-9]{4}$/.test(raw);
            // Mercosul: ABC1D23
            const mercosul  = /^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(raw);

            this.plateError = (old || mercosul) ? '' : 'Placa inválida (use AAA-1111 ou AAA-1A11)';
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerVehicleFormAlpine(); });
document.addEventListener('livewire:init', () => { registerVehicleFormAlpine(); });

// ---------------------------------------------------------------------------
// staffForm — máscara de CPF e telefone no cadastro de efetivo
// ---------------------------------------------------------------------------

function registerStaffFormAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuStaffFormDone) return;
    window.__samuStaffFormDone = true;

    Alpine.data('staffForm', () => ({
        cpfError: '',
        phoneError: '',
        emailError: '',

        // ── CPF ─────────────────────────────────────────────────────────────
        maskCpf(event) {
            let v = event.target.value.replace(/\D/g, '').slice(0, 11);
            v = v
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
            event.target.value = v;
        },

        validateCpfBlur(event) {
            const d = event.target.value.replace(/\D/g, '');
            if (d.length === 0)  { this.cpfError = ''; return; }
            if (d.length !== 11) { this.cpfError = 'CPF deve ter 11 dígitos'; return; }
            this.cpfError = this._validCpf(d) ? '' : 'CPF inválido';
        },

        _validCpf(d) {
            if (/^(\d)\1{10}$/.test(d)) return false;
            let s = 0;
            for (let i = 0; i < 9; i++) s += parseInt(d[i]) * (10 - i);
            let r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
            if (r !== parseInt(d[9])) return false;
            s = 0;
            for (let i = 0; i < 10; i++) s += parseInt(d[i]) * (11 - i);
            r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
            return r === parseInt(d[10]);
        },

        // ── Telefone ────────────────────────────────────────────────────────
        maskPhone(event) {
            let v = event.target.value.replace(/\D/g, '').slice(0, 11);
            if (v.length === 0) { event.target.value = ''; this.phoneError = ''; return; }

            if (v.length <= 10) {
                v = v.replace(/^(\d{0,2})(\d{0,4})(\d{0,4})$/, (_, a, b, c) => {
                    let r = '';
                    if (a) r += '(' + a;
                    if (a.length === 2) r += ') ';
                    if (b) r += b;
                    if (b.length === 4 && c) r += '-' + c;
                    return r;
                });
            } else {
                v = v.replace(/^(\d{2})(\d{5})(\d{0,4})$/, (_, a, b, c) => {
                    let r = '(' + a + ') ' + b;
                    if (c) r += '-' + c;
                    return r;
                });
            }

            event.target.value = v;
            this.validatePhone(event.target.value);
        },

        validatePhone(val) {
            const digits = val.replace(/\D/g, '');
            if (digits.length === 0) { this.phoneError = ''; return; }
            if (digits.length < 10 || digits.length > 11) {
                this.phoneError = 'Telefone inválido';
            } else {
                this.phoneError = '';
            }
        },

        // ── E-mail ──────────────────────────────────────────────────────────
        validateEmail(val) {
            if (!val || val.trim() === '') { this.emailError = ''; return; }
            this.emailError = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val.trim())
                ? ''
                : 'E-mail inválido';
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerStaffFormAlpine(); });
document.addEventListener('livewire:init', () => { registerStaffFormAlpine(); });

// ---------------------------------------------------------------------------
// shiftForm — validação de datas e aviso de expediente longo no turno
// ---------------------------------------------------------------------------

function registerShiftFormAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuShiftFormDone) return;
    window.__samuShiftFormDone = true;

    Alpine.data('shiftForm', () => ({
        endError: '',
        durationWarning: '',

        // Chamado no change do campo Fim
        validateEnd(val) {
            if (!val) { this.endError = ''; this.durationWarning = ''; return; }
            const end = new Date(val);
            const now = new Date();
            this.endError = end <= now ? 'A data/hora de fim deve ser futura' : '';
            this._updateDuration(end);
        },

        _updateDuration(end) {
            const diffH = (end - Date.now()) / 3_600_000;
            if (diffH <= 0) { this.durationWarning = ''; return; }
            this.durationWarning = diffH > 24
                ? `Expediente longo: ${Math.round(diffH)}h (acima de 24h) — verifique as datas`
                : '';
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerShiftFormAlpine(); });
document.addEventListener('livewire:init', () => { registerShiftFormAlpine(); });

// ---------------------------------------------------------------------------
// incidentForm — seletor de tipo de chamada + visibilidade condicional de seções
// ---------------------------------------------------------------------------

function registerIncidentFormAlpine() {
    const Alpine = window.Alpine;
    if (!Alpine?.data || window.__samuIncidentFormDone) return;
    window.__samuIncidentFormDone = true;

    Alpine.data('incidentForm', (initialType = 'N') => ({
        selectedType: initialType,

        // Tipos que só exigem telefone
        simpleTypes: ['C', 'T', 'A'],

        isSimple() {
            return this.simpleTypes.includes(this.selectedType);
        },

        selectType(code) {
            this.selectedType = code;
        },

        submit() {
            this.$wire.saveWithCallType(this.selectedType);
        },
    }));
}

document.addEventListener('alpine:init',   () => { registerIncidentFormAlpine(); });
document.addEventListener('livewire:init', () => { registerIncidentFormAlpine(); });
