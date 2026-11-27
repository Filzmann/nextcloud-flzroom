import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const bundleRoot = new URL('../../js/shared/', import.meta.url);
const lock = JSON.parse(readFileSync(new URL('flz-shared.lock.json', bundleRoot), 'utf8'));
if (lock.component !== 'browser'
    || lock.app_id !== 'flzroom'
    || lock.app_namespace !== 'FlzRoom'
    || lock.browser_global !== 'window.OCA.FlzRoom.Shared'
    || Object.keys(lock.packages || {}).join(',') !== 'browser') {
    throw new Error('Der Browser-Lock beschreibt nicht ausschließlich das isolierte flzroom-Bundle.');
}
readFileSync(new URL('LICENSE', bundleRoot), 'utf8');

const requests = [];
const noticeElement = { className: '', hidden: true, textContent: '' };
const window = {
    fetch: async (url, options) => {
        requests.push({ url, options });
        return { ok: true, status: 200, text: async () => '{"result":"ok"}' };
    },
};
window.window = window;
window.OC = {
    generateUrl: path => `/index.php${path}`,
    requestToken: 'room-token',
};
const context = vm.createContext({
    document: { getElementById: id => id === 'notice' ? noticeElement : null },
    encodeURIComponent,
    Error,
    JSON,
    Object,
    OC: window.OC,
    String,
    window,
});
for (const file of ['api-client.js', 'model.js', 'overlay.js', 'repository.js', 'ui.js']) {
    const sourcePath = fileURLToPath(new URL(file, bundleRoot));
    vm.runInContext(readFileSync(sourcePath, 'utf8'), context, { filename: sourcePath });
}
for (const file of ['models/room.js', 'models/booking.js', 'repositories/room-repository.js']) {
    const sourcePath = fileURLToPath(new URL(`../../js/${file}`, import.meta.url));
    vm.runInContext(
        readFileSync(sourcePath, 'utf8'),
        context,
        { filename: sourcePath },
    );
}

const shared = window.OCA?.FlzRoom?.Shared;
if (!shared?.api?.ApiClient || !shared?.models?.Model || !shared?.repositories?.Repository || !shared?.ui?.Notice || !shared?.ui?.Overlay || !shared?.ui?.AdminAccessOverlay) {
    throw new Error('Das app-lokale Browser-Bundle stellt seine Primitives nicht vollständig isoliert bereit.');
}
if ('LocalBase' in window) {
    throw new Error('Das app-lokale Browser-Bundle erzeugt ein window.LocalBase-Global.');
}

const client = new shared.api.ApiClient({ appId: 'flzroom' });
await client.request('/api/rooms');
if (requests.length !== 1
    || requests[0].url !== '/index.php/apps/flzroom/api/rooms'
    || requests[0].options.headers.requesttoken !== 'room-token') {
    throw new Error('Der gebündelte ApiClient bewahrt URL- und CSRF-Vertrag nicht.');
}

const room = shared.models.Model.get({ id: 7 });
if (!(room instanceof shared.models.Model)) {
    throw new Error('Das gebündelte Modell bewahrt den get-Vertrag nicht.');
}

const repository = new shared.repositories.Repository(client);
await repository.post('/api/rooms', { name: 'Nord' });
if (requests.at(-1)?.options.method !== 'POST'
    || requests.at(-1)?.options.body !== '{"name":"Nord"}') {
    throw new Error('Das gebündelte Repository bewahrt den POST-Vertrag nicht.');
}

const roomRepository = new window.FlzRoom.RoomRepository({
    encode: value => encodeURIComponent(String(value)),
    request: async path => ({
        month: path.split('/').at(-1),
        rooms: [{ id: 7, name: 'Nord', description: '', sortOrder: 1 }],
        bookings: [{ id: 9, roomId: 7, purpose: 'Sitzung', title: 'Planung' }],
    }),
});
const month = await roomRepository.month('2026-11');
if (!(month.rooms[0] instanceof window.FlzRoom.Room)
    || !(month.bookings[0] instanceof window.FlzRoom.Booking)
    || month.month !== '2026-11') {
    throw new Error('Die Raumplaner-Modelle und das Repository konsumieren das isolierte Bundle nicht korrekt.');
}

const notice = new shared.ui.Notice('notice', {
    baseClass: 'flz-room-notice',
    typeClassPrefix: 'is-',
});
notice.success('Bereit');
if (noticeElement.textContent !== 'Bereit'
    || noticeElement.hidden
    || noticeElement.className !== 'flz-room-notice is-success') {
    throw new Error('Die gebündelte Notice bewahrt ihren sichtbaren Erfolgszustand nicht.');
}

console.log('flzroom isolated Category-A browser bundle smoke passed');
