import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import './incident-osm.js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

/** Mesmo host da barra de endereço quando o .env usa localhost — evita WS para 127.0.0.1 errado ao abrir por samu192.local, etc. */
function resolveReverbHost() {
    const fromEnv = import.meta.env.VITE_REVERB_HOST;
    if (!fromEnv || fromEnv === 'localhost' || fromEnv === '127.0.0.1') {
        return window.location.hostname;
    }

    return fromEnv;
}

const host = resolveReverbHost();

let operationalCallIntakeEchoBound = false;

let dashboardCallStatsEchoBound = false;

let operationalLiveUpdatesBound = false;

/** Echo/Reverb pode entregar `data` como string JSON ou objeto aninhado em `.data`. */
function normalizeOperationalCallIntakeBroadcast(raw) {
    let p = raw;
    if (typeof p === 'string') {
        try {
            p = JSON.parse(p);
        } catch {
            return {};
        }
    }
    if (!p || typeof p !== 'object') {
        return {};
    }
    if (Object.prototype.hasOwnProperty.call(p, 'data')) {
        const inner = p.data;
        if (typeof inner === 'string') {
            try {
                return JSON.parse(inner);
            } catch {
                return {};
            }
        }
        if (inner && typeof inner === 'object') {
            return inner;
        }
    }

    return p;
}

function leaveOperationalCallIntakeChannel() {
    if (!window.Echo) {
        return;
    }

    window.Echo.leave('operations.call-intake');
    operationalCallIntakeEchoBound = false;
}

function subscribeOperationalCallIntake(force = false) {
    const body = document.body;
    if (!body?.dataset?.broadcastCallIntake || body.dataset.broadcastCallIntake !== '1') {
        return;
    }
    if (!window.Echo) {
        return;
    }
    if (operationalCallIntakeEchoBound && !force) {
        return;
    }
    if (force && operationalCallIntakeEchoBound) {
        leaveOperationalCallIntakeChannel();
    }

    operationalCallIntakeEchoBound = true;

    const channel = window.Echo.private('operations.call-intake');

    channel.listen('.operational.call-intake', (payload) => {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.dispatch !== 'function') {
            return;
        }

        const p = normalizeOperationalCallIntakeBroadcast(payload);
        const latRaw = p.latitude;
        const lngRaw = p.longitude;
        const phoneRaw = p.phone ?? p.phoneDigits;
        Livewire.dispatch('operational-call-intake', {
            form_url: p.form_url ?? '',
            phone:
                phoneRaw !== null && phoneRaw !== undefined && phoneRaw !== ''
                    ? String(phoneRaw)
                    : '',
            expires_at: p.expires_at ?? '',
            caller_name: p.caller_name ?? null,
            latitude:
                latRaw !== null && latRaw !== undefined && latRaw !== ''
                    ? String(latRaw)
                    : null,
            longitude:
                lngRaw !== null && lngRaw !== undefined && lngRaw !== ''
                    ? String(lngRaw)
                    : null,
            call_received_at: p.call_received_at ?? null,
            external_reference: p.external_reference ?? null,
            uniqueid: p.uniqueid ?? null,
        });
    });

    if (typeof channel.error === 'function') {
        channel.error(() => {
            operationalCallIntakeEchoBound = false;
        });
    }
}

function subscribeDashboardCallStats() {
    const body = document.body;
    if (!body?.dataset?.broadcastOperations || body.dataset.broadcastOperations !== '1') {
        return;
    }
    if (!window.Echo) {
        return;
    }
    if (dashboardCallStatsEchoBound) {
        return;
    }
    dashboardCallStatsEchoBound = true;

    const refreshDashboardCallStats = () => {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.dispatch !== 'function') {
            return;
        }
        Livewire.dispatch('dashboard-call-stats-refresh');
    };

    window.Echo.private('operations.dispatch').listen('.incident.created', refreshDashboardCallStats);
    window.Echo.private('operations.dispatch').listen('.dashboard.call-stats-invalidate', refreshDashboardCallStats);
}

/**
 * Substitui o antigo wire:poll: cada evento Reverb no canal operations.dispatch
 * dispara os eventos Livewire correspondentes. Componentes não montados na página
 * simplesmente ignoram o dispatch — só re-renderiza quem está ouvindo.
 */
function subscribeOperationalLiveUpdates() {
    const body = document.body;
    if (!body?.dataset?.broadcastOperations || body.dataset.broadcastOperations !== '1') {
        return;
    }
    if (!window.Echo || operationalLiveUpdatesBound) {
        return;
    }
    operationalLiveUpdatesBound = true;

    const fire = (...events) => {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.dispatch !== 'function') {
            return;
        }
        events.forEach((name) => Livewire.dispatch(name));
    };

    const channel = window.Echo.private('operations.dispatch');

    // Mapeia cada evento de broadcast para os componentes que devem atualizar.
    channel.listen('.incident.created', () =>
        fire('dispatch-board-refresh', 'incident-index-refresh', 'tactical-map-refresh'));

    channel.listen('.incident.call-request', () =>
        fire('dispatch-board-refresh', 'incident-index-refresh', 'incident-detail-refresh'));

    channel.listen('.incident.description-appended', () =>
        fire('dispatch-board-refresh', 'incident-detail-refresh'));

    channel.listen('.unit.dispatched', () =>
        fire('dispatch-board-refresh', 'incident-index-refresh', 'tactical-map-refresh', 'incident-detail-refresh'));

    channel.listen('.unit.released', () =>
        fire('dispatch-board-refresh', 'incident-index-refresh', 'tactical-map-refresh', 'incident-detail-refresh'));

    channel.listen('.dispatch.stage.advanced', () =>
        fire('dispatch-board-refresh', 'incident-detail-refresh'));

    channel.listen('.shift.updated', () =>
        fire('dispatch-board-refresh', 'fleet-shifts-refresh'));

    channel.listen('.incident.finalized', () =>
        fire('dispatch-board-refresh', 'incident-index-refresh', 'tactical-map-refresh', 'incident-detail-refresh'));

    const refreshCallAlerts = () =>
        fire('dispatch-board-refresh', 'call-alert-index-refresh');

    channel.listen('.operational.call-alert', refreshCallAlerts);
    channel.listen('.operational.call-alert-cluster', refreshCallAlerts);
    channel.listen('.operational.call-alert-aborted', refreshCallAlerts);
}

if (key) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${window.location.origin}/broadcasting/auth`,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
        },
        withCredentials: true,
    });

    // Inscreve assim que o Echo existe (não depende de livewire:init; o handler só usa Livewire quando o evento chega).
    subscribeOperationalCallIntake();
    subscribeDashboardCallStats();
    subscribeOperationalLiveUpdates();

    document.addEventListener('livewire:init', () => {
        const Livewire = window.Livewire;
        if (!Livewire || typeof Livewire.on !== 'function') {
            return;
        }

        Livewire.on('call-intake-preference-changed', ({ enabled }) => {
            if (!window.Echo) {
                return;
            }

            if (enabled) {
                document.body.dataset.broadcastCallIntake = '1';
                subscribeOperationalCallIntake(true);

                return;
            }

            document.body.dataset.broadcastCallIntake = '0';
            leaveOperationalCallIntakeChannel();
        });
    });

    document.addEventListener('livewire:navigated', () => {
        subscribeOperationalCallIntake();
    });
}
