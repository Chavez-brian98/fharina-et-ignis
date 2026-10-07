'use strict';

/**
 * Relay WebSocket para el tracking de domicilios.
 *
 * Un solo proceso (Node.js + paquete ws) que comparte un HTTP server:
 *
 *   - POST /location  { secret, delivery_id, lat, lng, driver_id, reported_at }
 *       Lo llama la app PHP cada vez que el telonero del navegador del
 *       domiciliero envia su posicion (geolocation API). Valida el secreto,
 *       guarda la ultima posicion en memoria y la difunde a los clientes WS
 *       suscritos a ese pedido.
 *
 *   - POST /event     { secret, delivery_id, type, label, at }
 *       Lo llama la app PHP cuando cambia el estado de un pedido (tomado,
 *       preparando, en_camino, finalizado). Se difunde a los suscriptores y se
 *       guarda en el buffer de eventos del delivery.
 *
 *   - GET /health     respuesta 200 para el healthcheck de compose.
 *
 *   - GET /ws?delivery=<id>
 *       Handshake WebSocket al que se conectan las pantallas de seguimiento.
 *       Al conectar reciben la ultima posicion y el buffer de eventos.
 *
 * Protocolo (relay -> cliente):
 *   { delivery_id, type: 'location', lat, lng, reported_at }
 *   { delivery_id, type: 'event',    state, label, at }
 *
 * La ultima posicion de cada delivery se conserva en data/snapshot.json para
 * sobrevivir reinicios del contenedor.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { WebSocketServer, WebSocket } = require('ws');

// ------------------------------- env -------------------------------
function loadEnv(file) {
    const out = {};
    try {
        const raw = fs.readFileSync(file, 'utf8');
        for (const line of raw.split(/\r?\n/)) {
            const m = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);
            if (m) out[m[1]] = m[2].replace(/^["']|["']$/g, '');
        }
    } catch (_) { /* el .env no existe: usamos defaults */ }
    return out;
}

const ENV = loadEnv(path.join(__dirname, '.env'));
const SECRET = (ENV.WS_SECRET || 'dev-secret').trim();
const PORT = parseInt(ENV.WS_PORT || '8090', 10);
const DATA_DIR = path.join(__dirname, 'data');
const SNAPSHOT = path.join(DATA_DIR, 'snapshot.json');

const subscriptions = new Map(); // delivery_id (num)  -> Set<ws>
const positions = new Map();     // delivery_id (num)  -> {lat,lng,reported_at,driver_id}
const events = new Map();        // delivery_id (num)  -> array (intactos, max 20)

const MAX_EVENTS = 20;

function safeEqual(a, b) {
    const la = Buffer.byteLength(String(a));
    const lb = Buffer.byteLength(String(b));
    if (la !== lb) return false;
    let diff = 0;
    const ba = Buffer.from(String(a));
    const bb = Buffer.from(String(b));
    for (let i = 0; i < la; i++) diff |= ba[i] ^ bb[i];
    return diff === 0;
}

function publish(deliveryId, msg) {
    const list = subscriptions.get(Number(deliveryId));
    if (!list) return;
    const data = JSON.stringify(Object.assign({ delivery_id: Number(deliveryId) }, msg));
    for (const ws of list) {
        if (ws.readyState === WebSocket.OPEN) ws.send(data);
    }
}

function saveSnapshot() {
    try {
        fs.mkdirSync(DATA_DIR, { recursive: true });
        fs.writeFileSync(SNAPSHOT, JSON.stringify({ positions: [...positions.entries()] }, null, 2));
    } catch (_) { /* best effort */ }
}

function loadSnapshot() {
    try {
        const snap = JSON.parse(fs.readFileSync(SNAPSHOT, 'utf8'));
        for (const [id, p] of snap.positions || []) {
            positions.set(Number(id), p);
        }
    } catch (_) { /* no hay snapshot todavia */ }
}

// --------------------------- http handlers --------------------------
function readBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', (chunk) => { body += chunk; if (body.length > 1e6) req.destroy(); });
        req.on('end', () => resolve(body));
        req.on('error', reject);
    });
}

async function handleHttp(req, res) {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);

    if (req.method === 'GET' && url.pathname === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
        return;
    }

    if (req.method === 'POST' && url.pathname === '/location') {
        await handleLocation(req, res);
        return;
    }

    if (req.method === 'POST' && url.pathname === '/event') {
        await handleEvent(req, res);
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not_found' }));
}

async function handleLocation(req, res) {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    let payload;
    try { payload = JSON.parse(await readBody(req)); }
    catch (_) { res.end(JSON.stringify({ ok: false, error: 'bad_json' })); return; }

    if (!safeEqual(payload.secret || '', SECRET)) {
        res.end(JSON.stringify({ ok: false, error: 'forbidden' }));
        return;
    }

    const deliveryId = Number(payload.delivery_id);
    const lat = Number(payload.lat);
    const lng = Number(payload.lng);
    if (!Number.isFinite(deliveryId) || deliveryId <= 0 || !Number.isFinite(lat) || !Number.isFinite(lng)) {
        res.end(JSON.stringify({ ok: false, error: 'bad_params' }));
        return;
    }

    const pos = {
        lat, lng,
        driver_id: payload.driver_id ? Number(payload.driver_id) : null,
        reported_at: payload.reported_at || new Date().toISOString(),
    };
    positions.set(deliveryId, pos);
    saveSnapshot();
    publish(deliveryId, Object.assign({ type: 'location' }, pos));
    res.end(JSON.stringify({ ok: true }));
}

async function handleEvent(req, res) {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    let payload;
    try { payload = JSON.parse(await readBody(req)); }
    catch (_) { res.end(JSON.stringify({ ok: false, error: 'bad_json' })); return; }

    if (!safeEqual(payload.secret || '', SECRET)) {
        res.end(JSON.stringify({ ok: false, error: 'forbidden' }));
        return;
    }

    const deliveryId = Number(payload.delivery_id);
    if (!Number.isFinite(deliveryId) || deliveryId <= 0 || !payload.state) {
        res.end(JSON.stringify({ ok: false, error: 'bad_params' }));
        return;
    }

    const ev = {
        state: String(payload.state),
        label: String(payload.label || payload.state),
        at: payload.at || new Date().toISOString(),
        by: payload.by || null,
    };
    const list = events.get(deliveryId) || [];
    list.push(ev);
    while (list.length > MAX_EVENTS) list.shift();
    events.set(deliveryId, list);
    publish(deliveryId, Object.assign({ type: 'event' }, ev));
    res.end(JSON.stringify({ ok: true }));
}

// ----------------------------- websocket ----------------------------
function handleWs(ws, req) {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const raw = url.searchParams.get('delivery');
    const deliveryId = Number(raw);
    if (!Number.isFinite(deliveryId) || deliveryId <= 0) {
        ws.close(4001, 'missing_delivery');
        return;
    }

    let list = subscriptions.get(deliveryId);
    if (!list) { list = new Set(); subscriptions.set(deliveryId, list); }
    list.add(ws);

    // snapshot inicial: ultima posicion + buffer de eventos
    const pos = positions.get(deliveryId);
    if (pos) {
        ws.send(JSON.stringify({ delivery_id: deliveryId, type: 'location', ...pos }));
    }
    for (const ev of events.get(deliveryId) || []) {
        ws.send(JSON.stringify({ delivery_id: deliveryId, type: 'event', ...ev }));
    }

    ws.on('close', () => {
        list.delete(ws);
        if (list.size === 0) subscriptions.delete(deliveryId);
    });
    ws.on('error', () => { /* el close lo quita */ });
}

// ------------------------------- boot -------------------------------
loadSnapshot();

const server = http.createServer(handleHttp);
const wss = new WebSocketServer({ server });
wss.on('connection', handleWs);

server.listen(PORT, () => {
    console.log(`[ws-relay] escuchando en :${PORT}`);
});

setInterval(() => saveSnapshot(), 10000);