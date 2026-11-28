import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {runInNewContext} from 'node:vm';

const jsPath = file => fileURLToPath(new URL(`../../js/${file}`, import.meta.url));
const loadSource = file => readFileSync(jsPath(file), 'utf8');
const flush = async () =>
{
    for (let i = 0; i < 20; i++)
    {
        await new Promise(resolve => setImmediate(resolve));
    }
};

function makeElement(tag = 'div', attributes = {})
{
    const el = {
        tag,
        children: [],
        attributes: new Map(Object.entries(attributes)),
        listeners: {},
        dataset: {},
        value: '',
        textContent: '',
        checked: false,
        disabled: false,
        hidden: false,
        type: '',
        colSpan: 0,
        className: '',
        classList: {
            classes: new Set(),
            add(...names)
            {
                names.forEach(name => this.classes.add(name));
            },
            contains(name)
            {
                return this.classes.has(name);
            },
            toggle(name, force)
            {
                const on = force === undefined ? !this.classes.has(name) : Boolean(force);
                if (on) this.classes.add(name); else this.classes.delete(name);
                return on;
            },
            remove(...names)
            {
                names.forEach(name => this.classes.delete(name));
            },
        },
        setAttribute(name, value)
        {
            this.attributes.set(name, String(value));
        },
        getAttribute(name)
        {
            return this.attributes.get(name) ?? null;
        },
        append(...nodes)
        {
            this.children.push(...nodes);
        },
        replaceChildren(...nodes)
        {
            this.children = nodes;
        },
        addEventListener(name, listener)
        {
            (this.listeners[name] ??= []).push(listener);
        },
        async dispatch(name, event = {})
        {
            event.target ??= el;
            event.preventDefault ??= () =>
            {
                event.defaultPrevented = true;
            };
            for (const listener of this.listeners[name] || []) await listener(event);
        },
        querySelector(selector)
        {
            return this._queries?.[selector] ?? null;
        },
        closest()
        {
            return this._closest ?? null;
        },
        focus()
        {
            this.focused = true;
        },
    };
    return el;
}

class FakeFormData
{
    constructor(form)
    {
        this.form = form;
    }

    get(name)
    {
        return this.form._fields?.[name] ?? null;
    }
}

function makeShared(calls, responses = {})
{
    class ApiClient
    {
        constructor(options)
        {
            this.options = options;
        }

        async request(path, options)
        {
            calls.push(['request', path, options]);
            const key = `${options?.method || 'GET'} ${path}`;
            const response = responses[key] ?? responses[path];
            if (response instanceof Error) throw response;
            if (typeof response === 'function') return response(options);
            return response ?? {};
        }
    }
    class Notice
    {
        constructor(id, options)
        {
            this.id = id;
            this.options = options;
        }

        success(message)
        {
            calls.push(['notice-success', message]);
        }

        error(error, fallback)
        {
            calls.push(['notice-error', error?.message || fallback]);
        }
    }
    return {
        api: {ApiClient},
        ui: {Notice, byId: id => calls.byId(id)},
    };
}

function baseContext(window, document)
{
    return {
        window, document, FormData: FakeFormData, Intl, Date, JSON, Number, String, encodeURIComponent, Promise, console,
    };
}

// ---------------------------------------------------------------------------
// js/retention-policy.js
// ---------------------------------------------------------------------------
{
    const calls = [];
    const status = makeElement();
    const reviewButton = makeElement('button');
    const form = makeElement('form');
    form._fields = {durationPeriod: 'P7Y', adminHistoryDurationPeriod: 'P3Y', interventionAuditDurationPeriod: 'P90D', failedNotificationDurationPeriod: 'P7D', expectedRevision: '4'};
    form.elements = {
        durationPeriod: {value: ''},
        adminHistoryDurationPeriod: {value: ''}, interventionAuditDurationPeriod: {value: ''}, failedNotificationDurationPeriod: {value: ''},
        expectedRevision: {value: ''},
    };
    form._queries = {'[data-retention-review]': reviewButton};
    const policy = {durationPeriod: 'P7Y', adminHistoryDurationPeriod: 'P3Y', interventionAuditDurationPeriod: 'P90D', failedNotificationDurationPeriod: 'P7D', revision: 4, reviewDue: false};
    const shared = makeShared(calls, {
        'GET /api/privacy/retention-policy': {retentionPolicy: policy},
        'PUT /api/privacy/retention-policy': {retentionPolicy: {...policy, durationPeriod: 'P5Y', revision: 5}},
        'POST /api/privacy/retention-policy/review': {retentionPolicy: {...policy, reviewDue: false}},
    });
    const window = {OCA: {FlzRoom: {Shared: shared}}};
    const document = {getElementById: id => ({'flz-room-retention-policy-form': form, 'flz-room-retention-policy-status': status}[id] ?? null)};
    runInNewContext(loadSource('retention-policy.js'), baseContext(window, document), {filename: jsPath('retention-policy.js')});
    await flush();
    if (form.elements.interventionAuditDurationPeriod.value !== 'P90D' || form.elements.failedNotificationDurationPeriod.value !== 'P7D' || form.elements.durationPeriod.value !== 'P7Y' || form.elements.adminHistoryDurationPeriod.value !== 'P3Y' || form.elements.expectedRevision.value !== '4')
        throw new Error('Retention-Policy rendert die geladenen Werte nicht in das Formular.');
    if (status.textContent !== 'Policyversion 4 ist aktuell.')
    {
        throw new Error(`Retention-Status falsch: ${status.textContent}`);
    }

    await form.dispatch('submit');
    const put = calls.find(call => call[0] === 'request' && call[1] === '/api/privacy/retention-policy' && call[2]?.method === 'PUT');
    if (!put) throw new Error('Retention-Policy sendet keinen PUT-Request beim Speichern.');
    const putBody = JSON.parse(put[2].body);
    if (putBody.interventionAuditDurationPeriod !== 'P90D' || putBody.failedNotificationDurationPeriod !== 'P7D' || putBody.durationPeriod !== 'P7Y' || putBody.adminHistoryDurationPeriod !== 'P3Y' || putBody.expectedRevision !== 4)
        throw new Error(`Retention-PUT-Body falsch: ${put[2].body}`);
    if (form.elements.durationPeriod.value !== 'P5Y' || status.textContent !== 'Policyversion 5 ist aktuell.')
        throw new Error('Retention-Policy rendert die Antwort des PUT nicht.');

    await reviewButton.dispatch('click');
    const review = calls.find(call => call[0] === 'request' && call[2]?.method === 'POST' && call[1] === '/api/privacy/retention-policy/review');
    if (!review || JSON.parse(review[2].body).expectedRevision !== 5)
        throw new Error('Retention-Review sendet keine korrekte expectedRevision.');

    // Negativ: reviewDue=true und Fehlerpfade
    const calls2 = [];
    const status2 = makeElement();
    const form2 = makeElement('form');
    form2._fields = {durationPeriod: 'P7Y', adminHistoryDurationPeriod: 'P3Y', interventionAuditDurationPeriod: 'P90D', failedNotificationDurationPeriod: 'P7D', expectedRevision: '9'};
    form2.elements = {durationPeriod: {value: ''}, adminHistoryDurationPeriod: {value: ''}, interventionAuditDurationPeriod: {value: ''}, failedNotificationDurationPeriod: {value: ''}, expectedRevision: {value: ''}};
    form2._queries = {'[data-retention-review]': makeElement('button')};
    const shared2 = makeShared(calls2, {
        'GET /api/privacy/retention-policy': {retentionPolicy: {durationPeriod: 'P7Y', adminHistoryDurationPeriod: 'P3Y', interventionAuditDurationPeriod: 'P90D', failedNotificationDurationPeriod: 'P7D', revision: 9, reviewDue: true}},
        'PUT /api/privacy/retention-policy': new Error('Konflikt'),
    });
    const window2 = {OCA: {FlzRoom: {Shared: shared2}}};
    const document2 = {getElementById: id => ({'flz-room-retention-policy-form': form2, 'flz-room-retention-policy-status': status2}[id] ?? null)};
    runInNewContext(loadSource('retention-policy.js'), baseContext(window2, document2), {filename: jsPath('retention-policy.js')});
    await flush();
    if (status2.textContent !== 'Die jährliche Prüfung ist fällig.')
    {
        throw new Error(`Review-fälliger Status falsch: ${status2.textContent}`);
    }
    await form2.dispatch('submit');
    if (status2.getAttribute('role') !== 'alert' || status2.textContent !== 'Konflikt')
        throw new Error('Retention-Policy zeigt Speicherfehler nicht als Alert.');

    // Negativ: Formular fehlt -> Skript darf nicht werfen
    runInNewContext(loadSource('retention-policy.js'), baseContext({OCA: {}}, {getElementById: () => null}), {filename: jsPath('retention-policy.js')});
    console.log('retention-policy.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/room-management-settings.js
// ---------------------------------------------------------------------------
{
    const calls = [];
    const section = makeElement('section');
    const body = makeElement('tbody');
    const submit = makeElement('button');
    const form = makeElement('form');
    form._queries = {'button[type="submit"]': submit};
    const elements = {'flz-room-settings-rooms': section, 'flz-room-settings-room-body': body, 'flz-room-settings-room-form': form};
    const rendered = [];
    class RoomRepository
    {
        constructor(client)
        {
            this.client = client;
        }

        async rooms()
        {
            calls.push(['rooms']);
            return {rooms: [{id: 1, name: 'Nord'}]};
        }
    }
    class RoomWorkflow
    {
        constructor(deps)
        {
            this.deps = deps;
        }
    }
    class RoomSettings
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        render(rooms, flag)
        {
            rendered.push([rooms, flag]);
        }
    }
    const shared = makeShared(calls, {});
    shared.ui.byId = id => elements[id] ?? null;
    const window = {OCA: {FlzRoom: {Shared: shared}}, FlzRoom: {RoomRepository, RoomWorkflow, RoomSettings}};
    const document = {getElementById: () => null};
    runInNewContext(loadSource('room-management-settings.js'), baseContext(window, document), {filename: jsPath('room-management-settings.js')});
    await flush();
    if (rendered.length !== 1 || rendered[0][0][0].name !== 'Nord' || rendered[0][1] !== true)
        throw new Error('Room-Management-Settings rendert die geladenen Räume nicht.');

    // Negativ: Ladefehler -> Notice + deaktivierter Submit-Button
    const calls2 = [];
    const submit2 = makeElement('button');
    const form2 = makeElement('form');
    form2._queries = {'button[type="submit"]': submit2};
    const elements2 = {'flz-room-settings-rooms': makeElement(), 'flz-room-settings-room-body': makeElement(), 'flz-room-settings-room-form': form2};
    class FailingRepository
    {
        async rooms()
        {
            throw new Error('Netzwerkfehler');
        }
    }
    const shared2 = makeShared(calls2, {});
    shared2.ui.byId = id => elements2[id] ?? null;
    const window2 = {OCA: {FlzRoom: {Shared: shared2}}, FlzRoom: {RoomRepository: FailingRepository, RoomWorkflow, RoomSettings}};
    runInNewContext(loadSource('room-management-settings.js'), baseContext(window2, document), {filename: jsPath('room-management-settings.js')});
    await flush();
    if (!calls2.some(call => call[0] === 'notice-error' && call[1] === 'Netzwerkfehler')) throw new Error('Room-Management-Settings meldet Ladefehler nicht.');
    if (!submit2.disabled) throw new Error('Room-Management-Settings deaktiviert den Submit-Button nach Ladefehler nicht.');

    // Negativ: fehlende Elemente -> kein Werfen, keine Requests
    const calls3 = [];
    const shared3 = makeShared(calls3, {});
    shared3.ui.byId = () => null;
    runInNewContext(loadSource('room-management-settings.js'), baseContext({OCA: {FlzRoom: {Shared: shared3}}, FlzRoom: {}}, document), {filename: jsPath('room-management-settings.js')});
    await flush();
    if (calls3.length) throw new Error('Room-Management-Settings ohne DOM-Elemente darf keine Requests auslösen.');
    console.log('room-management-settings.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/admin-access.js
// ---------------------------------------------------------------------------
{
    const calls = [];
    const status = makeElement();
    const history = makeElement('tbody');
    const enabled = {checked: true};
    const form = makeElement('form');
    form._fields = {enabled: 'on', targetUid: '  admin2  ', durationMinutes: '30'};
    form.elements = {enabled};
    const now = Date.now();
    const grants = [
        {targetUid: 'admin1', grantedBy: 'dpo1', startsAt: new Date(now - 3600e3).toISOString(), endsAt: new Date(now + 3600e3).toISOString(), revokedAt: null},
        {targetUid: 'admin2', grantedBy: 'dpo1', startsAt: new Date(now - 7200e3).toISOString(), endsAt: new Date(now - 3600e3).toISOString(), revokedAt: null},
        {targetUid: 'admin3', grantedBy: 'dpo1', startsAt: new Date(now - 7200e3).toISOString(), endsAt: new Date(now + 3600e3).toISOString(), revokedAt: new Date(now - 60e3).toISOString()},
    ];
    const shared = makeShared(calls, {
        'GET /api/admin/full-access': {history: grants},
        'POST /api/admin/full-access': {},
        'DELETE /api/admin/full-access/admin1': {},
    });
    const window = {OCA: {FlzRoom: {Shared: shared}}};
    const overlay = makeElement('div');
    overlay.hidden = true;
    const trigger = makeElement('button');
    const closeButton = makeElement('button');
    const document = {
        getElementById: id => ({'flz-room-full-access-form': form, 'flz-room-full-access-history': history, 'flz-room-full-access-status': status, 'flz-room-admin-access-overlay': overlay, 'flz-room-admin-grant-warning': trigger, 'flz-room-admin-access-overlay-close': closeButton}[id] ?? null),
        createElement: tag => makeElement(tag),
        addEventListener() {},
        querySelectorAll: () => [],
    };
    runInNewContext(loadSource('shared/overlay.js'), baseContext(window, document), {filename: jsPath('shared/overlay.js')});
    runInNewContext(loadSource('admin-access.js'), baseContext(window, document), {filename: jsPath('admin-access.js')});
    await flush();
    if (calls.length) throw new Error('Admin-Access lädt die Historie vor dem Öffnen des Overlays.');
    await trigger.dispatch('click', {target: trigger});
    await flush();
    if (overlay.hidden !== false || trigger.getAttribute('aria-expanded') !== 'true' || !closeButton.focused) throw new Error('Warn-Icon öffnet das Overlay nicht korrekt.');
    if (history.children.length !== 3) throw new Error('Admin-Access rendert nicht alle Freigaben.');
    const [activeRow, expiredRow, revokedRow] = history.children;
    if (!activeRow.children[4].textContent.includes('aktiv') || activeRow.children[4].textContent.includes('abgelaufen'))
        throw new Error(`Aktive Freigabe falsch: ${activeRow.children[4].textContent}`);
    if (!expiredRow.children[4].textContent.includes('abgelaufen'))
    {
        throw new Error(`Abgelaufene Freigabe falsch: ${expiredRow.children[4].textContent}`);
    }
    if (!revokedRow.children[4].textContent.includes('widerrufen'))
    {
        throw new Error(`Widerrufene Freigabe falsch: ${revokedRow.children[4].textContent}`);
    }
    const revokeButton = activeRow.children[5].children[0];
    if (!revokeButton || revokeButton.dataset.revokeUid !== 'admin1' || revokeButton.textContent !== 'Widerrufen')
        throw new Error('Aktive Freigabe bietet keinen Widerrufen-Button.');
    if (expiredRow.children[5].children.length || revokedRow.children[5].children.length)
        throw new Error('Inaktive Freigaben dürfen keinen Widerrufen-Button bieten.');

    // Submit mit enabled=on
    await form.dispatch('submit');
    const post = calls.find(call => call[0] === 'request' && call[2]?.method === 'POST');
    if (!post) throw new Error('Admin-Access sendet keinen POST beim Aktivieren.');
    const postBody = JSON.parse(post[2].body);
    if (postBody.targetUid !== 'admin2' || postBody.durationMinutes !== 30)
        throw new Error(`Admin-Access POST-Body falsch: ${post[2].body}`);
    if (enabled.checked) throw new Error('Admin-Access setzt die Checkbox nach Erfolg nicht zurück.');
    if (status.textContent !== 'Der zeitlich begrenzte Vollzugriff wurde aktiviert.' || status.getAttribute('role') !== 'status')
        throw new Error('Admin-Access meldet Erfolg nicht als Status.');

    // Negativ: Submit ohne enabled=on -> kein Request
    const requestCount = calls.filter(call => call[0] === 'request').length;
    form._fields = {enabled: null, targetUid: 'x', durationMinutes: '5'};
    await form.dispatch('submit');
    if (calls.filter(call => call[0] === 'request').length !== requestCount)
        throw new Error('Admin-Access sendet ohne aktivierte Checkbox trotzdem einen Request.');

    // Widerrufen
    revokeButton._closest = revokeButton;
    await history.dispatch('click', {target: revokeButton});
    const del = calls.find(call => call[0] === 'request' && call[2]?.method === 'DELETE');
    if (!del || del[1] !== '/api/admin/full-access/admin1')
    {
        throw new Error(`Widerruf-Request falsch: ${del?.[1]}`);
    }
    if (status.textContent !== 'Der Vollzugriff wurde widerrufen.') throw new Error('Widerruf-Statusmeldung fehlt.');

    // Negativ: Klick ohne Button, leere Historie, Fehlerpfade
    const calls2 = [];
    const status2 = makeElement();
    const history2 = makeElement('tbody');
    const form2 = makeElement('form');
    form2._fields = {enabled: 'on', targetUid: 'admin9', durationMinutes: '10'};
    form2.elements = {enabled: {checked: true}};
    const shared2 = makeShared(calls2, {
        'GET /api/admin/full-access': {history: []},
        'POST /api/admin/full-access': new Error('Serverfehler'),
    });
    const window2 = {OCA: {FlzRoom: {Shared: shared2}}};
    const overlay2 = makeElement('div');
    overlay2.hidden = true;
    const trigger2 = makeElement('button');
    const document2 = {
        getElementById: id => ({'flz-room-full-access-form': form2, 'flz-room-full-access-history': history2, 'flz-room-full-access-status': status2, 'flz-room-admin-access-overlay': overlay2, 'flz-room-admin-grant-warning': trigger2, 'flz-room-admin-access-overlay-close': makeElement('button')}[id] ?? null),
        createElement: tag => makeElement(tag),
        addEventListener() {},
        querySelectorAll: () => [],
    };
    runInNewContext(loadSource('shared/overlay.js'), baseContext(window2, document2), {filename: jsPath('shared/overlay.js')});
    runInNewContext(loadSource('admin-access.js'), baseContext(window2, document2), {filename: jsPath('admin-access.js')});
    await flush();
    await trigger2.dispatch('click', {target: trigger2});
    await flush();
    if (overlay2.hidden !== false) throw new Error('Warn-Icon öffnet das Overlay nicht.');
    if (history2.children.length !== 1 || history2.children[0].children[0].textContent !== 'Noch keine Freigaben protokolliert.' || history2.children[0].children[0].colSpan !== 6)
        throw new Error('Admin-Access rendert die Leer-Historie nicht.');
    await history2.dispatch('click', {target: makeElement('span')});
    if (calls2.some(call => call[0] === 'request' && call[2]?.method === 'DELETE')) throw new Error('Klick ohne Widerrufen-Button darf kein DELETE auslösen.');
    await form2.dispatch('submit');
    if (status2.getAttribute('role') !== 'alert' || status2.textContent !== 'Serverfehler')
        throw new Error('Admin-Access zeigt Aktivierungsfehler nicht als Alert.');

    // Negativ: DOM fehlt -> kein Werfen
    runInNewContext(loadSource('shared/overlay.js'), baseContext({OCA: {}}, {getElementById: () => null, createElement: t => makeElement(t)}), {filename: jsPath('shared/overlay.js')});
    runInNewContext(loadSource('admin-access.js'), baseContext({OCA: {}}, {getElementById: () => null, createElement: t => makeElement(t)}), {filename: jsPath('admin-access.js')});
    console.log('admin-access.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/components/organization-dashboard.js (app-lokale Dashboard-Komponente)
// ---------------------------------------------------------------------------
{
    const changes = [];
    const scope = makeElement('section', {'data-dashboard-scope': 'main'});
    scope.dataset.dashboardScope = 'main';
    const widget = makeElement('section', {'data-dashboard-widget': 'rooms'});
    widget.dataset.widgetId = 'rooms';
    const title = makeElement('h3', {'data-dashboard-title': 'Räume'});
    title.textContent = 'Räume';
    const toggle = makeElement('button', {'data-dashboard-toggle': ''});
    toggle.setAttribute('aria-expanded', 'true');
    const content = makeElement('div', {'data-dashboard-content': ''});
    widget.append(title, toggle, content);
    widget.querySelector = selector => ({'[data-dashboard-title]': title, '[data-dashboard-toggle]': toggle, '[data-dashboard-content]': content})[selector] ?? null;
    widget.matches = selector => selector === '[data-dashboard-widget]';
    widget.closest = selector => (selector === '[data-dashboard-widget]' ? widget : null);
    scope.append(widget);
    scope.querySelectorAll = () => [scope];
    scope.matches = selector => selector === '[data-dashboard-scope]';
    const feedback = makeElement('p', {'data-dashboard-feedback': ''});
    const root = makeElement('div');
    root.addEventListener = () => {};
    root.querySelectorAll = selector => (selector === '[data-dashboard-scope]' ? [scope] : []);
    root.querySelector = selector => (selector === '[data-dashboard-feedback]' ? feedback : null);
    const window = {FlzRoom: {}};
    runInNewContext(loadSource('components/organization-dashboard.js'), baseContext(window, {getElementById: () => null}), {filename: jsPath('components/organization-dashboard.js')});
    const Dashboard = window.FlzRoom.OrganizationDashboard;
    if (typeof Dashboard !== 'function') throw new Error('Dashboard-Komponente registriert kein window.FlzRoom.OrganizationDashboard.');
    const dashboard = new Dashboard({root, onChange: layout => changes.push(layout)});
    dashboard.set({version: 1, scopes: {main: {order: ['rooms'], collapsed: []}}, organigram: {zoom: 100}});
    if (dashboard.collectLayout().scopes.main.order[0] !== 'rooms') throw new Error('Dashboard sammelt die Widget-Reihenfolge nicht.');
    dashboard.setOrganigramZoom(150);
    if (changes.length !== 1 || changes[0].organigram.zoom !== 150) throw new Error('Dashboard meldet Zoom-Änderung nicht über onChange.');
    await toggle.dispatch?.('click');
    console.log('organization-dashboard.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/admin.js
// ---------------------------------------------------------------------------
{
    const calls = [];
    const ids = {};
    for (const id of ['flzroom-admin', 'flz-room-admin-room-body', 'flz-room-admin-room-form', 'flz-room-demo-confirm', 'flz-room-demo-install', 'flz-room-demo-notice', 'flz-room-admin-notice', 'flz-room-first-start', 'flz-room-first-start-button'])
        ids[id] = makeElement();
    const submit = makeElement('button');
    const nameInput = makeElement('input');
    ids['flz-room-admin-room-form']._queries = {'button[type="submit"]': submit, 'input[name="name"]': nameInput};
    ids['flz-room-demo-install'].disabled = true; // initialer Zustand laut Template
    const rendered = [];
    const firstStartRenders = [];
    class FirstStart
    {
        constructor(deps)
        {
            this.deps = deps;
            calls.push(['first-start-init', Boolean(deps.card), Boolean(deps.button), Boolean(deps.focusTarget)]);
        }

        render(rooms)
        {
            firstStartRenders.push(rooms);
            calls.push(['first-start-render', Array.isArray(rooms) ? rooms.length : 'invalid']);
        }
    }
    class RoomRepository
    {
        async rooms()
        {
            calls.push(['rooms']);
            return {rooms: [{id: 2, name: 'Süd'}]};
        }
    }
    class RoomWorkflow
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        async create() {}
        async update() {}
        async remove() {}
    }
    class RoomSettings
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        render(rooms, flag)
        {
            rendered.push([rooms, flag]);
        }
    }
    class OrganizationDashboard
    {
        constructor(deps)
        {
            this.deps = deps;
            calls.push(['dashboard']);
        }

        set(layout)
        {
            this.layout = layout;
            calls.push(['dashboard-set', layout]);
        }
    }
    const shared = makeShared(calls, {
        'GET /api/admin/settings': {dashboardLayout: {cards: ['a']}},
        'POST /api/admin/demo-pack/install': {result: {rooms: 4, createdBookings: 12}},
    });
    shared.ui.byId = id => ids[id] ?? null;
    const window = {
        OCA: {FlzRoom: {Shared: shared}},
        FlzRoom: {RoomRepository, RoomWorkflow, RoomSettings, OrganizationDashboard, FirstStart},
    };
    const document = {getElementById: () => null};
    runInNewContext(loadSource('admin.js'), baseContext(window, document), {filename: jsPath('admin.js')});
    await flush();
    if (rendered.length !== 1 || rendered[0][0][0].name !== 'Süd') throw new Error('Admin rendert die Räume nicht.');
    if (!calls.some(call => call[0] === 'dashboard-set' && JSON.stringify(call[1]) === '{"cards":["a"]}'))
        throw new Error('Admin lädt das Dashboard-Layout nicht.');
    if (firstStartRenders.length !== 1 || firstStartRenders[0][0].name !== 'Süd')
        throw new Error('Admin übergibt die geladenen Räume nicht an die Erste-Schritte-Karte.');
    if (!calls.some(call => call[0] === 'first-start-init' && call[1] && call[2] && call[3]))
        throw new Error('Admin verdrahtet die Erste-Schritte-Karte nicht vollständig (Karte, Button, Fokusziel).');

    const demoConfirmation = ids['flz-room-demo-confirm'];
    const demoButton = ids['flz-room-demo-install'];
    const demoNotice = ids['flz-room-demo-notice'];
    if (!demoButton.disabled) throw new Error('Demo-Button ist vor der Bestätigung nicht deaktiviert.');
    demoConfirmation.checked = true;
    await demoConfirmation.dispatch('change');
    if (demoButton.disabled) throw new Error('Demo-Button wird nach Bestätigung nicht freigeschaltet.');
    await demoButton.dispatch('click');
    const install = calls.find(call => call[0] === 'request' && call[1] === '/api/admin/demo-pack/install' && call[2]?.method === 'POST');
    if (!install) throw new Error('Demo-Installation sendet keinen POST.');
    if (demoNotice.textContent !== '4 Räume synchronisiert; 12 Buchungen angelegt.' || !demoNotice.classList.contains('is-success'))
        throw new Error(`Demo-Erfolgsmeldung falsch: ${demoNotice.textContent}`);
    if (demoConfirmation.checked) throw new Error('Demo-Checkbox wird nach Erfolg nicht zurückgesetzt.');
    if (calls.filter(call => call[0] === 'rooms').length < 2) throw new Error('Admin lädt die Räume nach der Demo-Installation nicht neu.');

    // Negativ: Klick ohne Bestätigung, Fehlerpfad, Ladefehler
    const calls2 = [];
    const ids2 = {};
    for (const id of Object.keys(ids)) ids2[id] = makeElement();
    ids2['flz-room-admin-room-form']._queries = {'button[type="submit"]': makeElement('button'), 'input[name="name"]': makeElement('input')};
    class FailingRepository
    {
        async rooms()
        {
            throw new Error('kaputt');
        }
    }
    class FirstStart2
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        render(rooms)
        {
            calls2.push(['first-start-render', rooms]);
        }
    }
    class OrganizationDashboard2
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        set() {}
    }
    const shared2 = makeShared(calls2, {
        'GET /api/admin/settings': {dashboardLayout: {}},
        'POST /api/admin/demo-pack/install': new Error('Demo fehlgeschlagen'),
        'PUT /api/admin/layout': {},
    });
    shared2.ui.byId = id => ids2[id] ?? null;
    const window2 = {
        OCA: {FlzRoom: {Shared: shared2}},
        FlzRoom: {RoomRepository: FailingRepository, RoomWorkflow, RoomSettings, OrganizationDashboard: OrganizationDashboard2, FirstStart: FirstStart2},
    };
    runInNewContext(loadSource('admin.js'), baseContext(window2, document), {filename: jsPath('admin.js')});
    await flush();
    if (!calls2.some(call => call[0] === 'notice-error' && call[1] === 'kaputt')) throw new Error('Admin meldet Raum-Ladefehler nicht.');
    if (!ids2['flz-room-admin-room-form']._queries['button[type="submit"]'].disabled) throw new Error('Admin deaktiviert den Submit-Button nach Ladefehler nicht.');
    if (calls2.some(call => call[0] === 'first-start-render')) throw new Error('Admin rendert die Erste-Schritte-Karte trotz Ladefehler.');
    const demoButton2 = ids2['flz-room-demo-install'];
    await demoButton2.dispatch('click');
    if (calls2.some(call => call[0] === 'request' && call[1] === '/api/admin/demo-pack/install'))
        throw new Error('Demo-Klick ohne Bestätigung darf keinen Request senden.');
    ids2['flz-room-demo-confirm'].checked = true;
    await demoButton2.dispatch('click');
    const notice2 = ids2['flz-room-demo-notice'];
    if (!notice2.classList.contains('is-error') || notice2.textContent !== 'Demo fehlgeschlagen' || demoButton2.disabled)
        throw new Error('Admin zeigt Demo-Fehler nicht korrekt und sperrt den Button nicht erneut.');
    console.log('admin.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/main.js
// ---------------------------------------------------------------------------
{
    const calls = [];
    const ids = {};
    for (const id of ['flz-room-calendar-head', 'flz-room-calendar-body', 'flz-room-month', 'flz-room-previous', 'flz-room-next', 'flz-room-booking-dialog', 'flz-room-booking-form', 'flz-room-notice'])
        ids[id] = makeElement();
    const windowListeners = {};
    const monthData = month => ({month, rooms: [{id: 1}], bookings: []});
    const pending = [];
    class RoomRepository
    {
        async month(month)
        {
            calls.push(['month', month]);
            const gate = {};
            gate.promise = new Promise(resolve =>
            {
                gate.resolve = resolve;
            });
            pending.push(gate);
            return gate.promise.then(() => monthData(month));
        }
    }
    class MonthCalendar
    {
        render(data)
        {
            calls.push(['render', data.month]);
        }
    }
    class BookingDialog
    {
        constructor()
        {
            calls.push(['dialog']);
        }

        setRooms(rooms)
        {
            calls.push(['setRooms', rooms.length]);
        }

        create(room, date)
        {
            calls.push(['create', room, date]);
        }

        edit(booking)
        {
            calls.push(['edit', booking.id]);
        }
    }
    class BookingWorkflow
    {
        constructor(deps)
        {
            this.deps = deps;
        }

        async save(data)
        {
            calls.push(['save', data]);
        }

        async remove(booking)
        {
            calls.push(['remove', booking.id]);
        }
    }
    const shared = makeShared(calls, {});
    shared.ui.byId = id => ids[id] ?? null;
    const window = {
        OCA: {FlzRoom: {Shared: shared}},
        FlzRoom: {RoomRepository, MonthCalendar, BookingDialog, BookingWorkflow},
        addEventListener(name, listener)
        {
            (windowListeners[name] ??= []).push(listener);
        },
    };
    const document = {getElementById: () => null};
    runInNewContext(loadSource('main.js'), baseContext(window, document), {filename: jsPath('main.js')});
    await flush();
    const expectedMonth = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`;
    if (!calls.some(call => call[0] === 'month' && call[1] === expectedMonth)) throw new Error('Main lädt nicht den aktuellen Monat.');
    pending.shift().resolve();
    await flush();
    if (ids['flz-room-month'].value !== expectedMonth) throw new Error('Main setzt den Monatswert nicht.');
    if (!calls.some(call => call[0] === 'render' && call[1] === expectedMonth) || !calls.some(call => call[0] === 'setRooms' && call[1] === 1))
        throw new Error('Main rendert Kalender und Raumliste nicht.');

    // Monatswechsel vor/zurück
    await ids['flz-room-next'].dispatch('click');
    const [y, m] = expectedMonth.split('-').map(Number);
    const nextMonth = `${new Date(y, m, 1).getFullYear()}-${String(new Date(y, m, 1).getMonth() + 1).padStart(2, '0')}`;
    if (!calls.some(call => call[0] === 'month' && call[1] === nextMonth))
    {
        throw new Error(`Nächster Monat falsch: ${nextMonth}`);
    }
    pending.shift().resolve();
    await flush();
    await ids['flz-room-previous'].dispatch('click');
    if (calls.filter(call => call[0] === 'month' && call[1] === expectedMonth).length < 2) throw new Error('Vorheriger Monat lädt nicht zurück.');
    pending.shift().resolve();
    await flush();

    // Negativ: leerer Monatswert löst kein Laden aus
    const count = calls.filter(call => call[0] === 'month').length;
    await ids['flz-room-month'].dispatch('change', {target: {value: ''}});
    if (calls.filter(call => call[0] === 'month').length !== count) throw new Error('Leerer Monatswert darf kein Neuladen auslösen.');
    await ids['flz-room-month'].dispatch('change', {target: {value: '2027-01'}});
    if (!calls.some(call => call[0] === 'month' && call[1] === '2027-01')) throw new Error('Monatswechsel per Eingabe lädt nicht das gewählte Monat.');
    pending.shift().resolve();
    await flush();

    // Negativ: veraltete Antwort wird verworfen
    await ids['flz-room-next'].dispatch('click');
    await ids['flz-room-next'].dispatch('click');
    const stale = pending.shift();
    const latest = pending.shift();
    latest.resolve();
    await flush();
    const rendersBefore = calls.filter(call => call[0] === 'render').length;
    stale.resolve();
    await flush();
    if (calls.filter(call => call[0] === 'render').length !== rendersBefore)
        throw new Error('Veraltete Monatsantwort wird trotzdem gerendert.');

    // Fenster-Events
    for (const [name, detail] of [['flzroom:add-booking', {room: 5, date: '2027-02-01'}], ['flzroom:edit-booking', {booking: {id: 9}}], ['flzroom:delete-booking', {booking: {id: 10}}]])
    {
        for (const listener of windowListeners[name] || [])
        {
            listener({detail});
        }
    }
    await flush();
    if (!calls.some(call => call[0] === 'create' && call[1] === 5 && call[2] === '2027-02-01')) throw new Error('add-booking-Event öffnet keinen Dialog.');
    if (!calls.some(call => call[0] === 'edit' && call[1] === 9)) throw new Error('edit-booking-Event öffnet keinen Dialog.');
    if (!calls.some(call => call[0] === 'remove' && call[1] === 10)) throw new Error('delete-booking-Event ruft keinen Workflow auf.');
    console.log('main.js Verhaltenscharakterisierung OK');
}

// ---------------------------------------------------------------------------
// js/components/first-start.js (Erste-Schritte-Karte im Adminbereich)
// ---------------------------------------------------------------------------
{
    const window = {FlzRoom: {}};
    runInNewContext(loadSource('components/first-start.js'), baseContext(window, {getElementById: () => null}), {filename: jsPath('components/first-start.js')});
    const FirstStart = window.FlzRoom.FirstStart;
    if (typeof FirstStart !== 'function') throw new Error('FirstStart registriert kein window.FlzRoom.FirstStart.');

    const card = makeElement('section');
    card.hidden = true; // initialer Zustand laut Template
    const button = makeElement('button');
    const nameInput = makeElement('input');
    const firstStart = new FirstStart({card, button, focusTarget: nameInput});
    firstStart.render([]);
    if (card.hidden !== false) throw new Error('Leere Raumliste blendet die Erste-Schritte-Karte nicht ein.');
    firstStart.render([{id: 1, name: 'Nord'}]);
    if (card.hidden !== true) throw new Error('Vorhandene Räume blenden die Erste-Schritte-Karte nicht aus.');
    firstStart.render([{id: 2}, {id: 3}]);
    if (card.hidden !== true) throw new Error('Mehrere Räume blenden die Erste-Schritte-Karte nicht aus.');
    firstStart.render('kaputt');
    if (card.hidden !== false) throw new Error('Ungültige Raumantwort wird nicht als leerer Zustand behandelt.');
    await button.dispatch('click');
    if (!nameInput.focused) throw new Error('Erste-Schritte-Button fokussiert nicht das Namensfeld des Raumformulars.');

    // Negativ: fehlende/optionale Elemente -> kein Werfen, kein Listener
    const lonelyCard = makeElement('section');
    const lonely = new FirstStart({card: lonelyCard});
    lonely.render([]);
    if (lonelyCard.hidden !== false) throw new Error('FirstStart ohne Button rendert die Karte nicht.');
    const noButton = new FirstStart({card: makeElement(), focusTarget: makeElement('input')});
    noButton.render([]);
    const empty = new FirstStart({});
    empty.render([]);
    empty.render([{id: 1}]);
    console.log('first-start.js Verhaltenscharakterisierung OK');
}

console.log('Filzmann Raumplaner runtime behavior tests passed');
