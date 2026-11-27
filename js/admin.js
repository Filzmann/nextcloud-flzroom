(function() {
    'use strict';
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)


    const shared = window.OCA.FlzRoom.Shared;
    const byId = shared.ui.byId;
    const client = new shared.api.ApiClient({ appId: 'flzroom' });
    const repository = new window.FlzRoom.RoomRepository(client);
    const notice = new shared.ui.Notice('flz-room-admin-notice', { baseClass: 'flz-room-notice', typeClassPrefix: 'is-' });
    const workflow = new window.FlzRoom.RoomWorkflow({ repository, notice, reload: load });
    const settings = new window.FlzRoom.RoomSettings({
        section: byId('flzroom-admin'),
        body: byId('flz-room-admin-room-body'),
        form: byId('flz-room-admin-room-form'),
        onCreate: payload => workflow.create(payload),
        onUpdate: (id, payload) => workflow.update(id, payload),
        onRemove: room => workflow.remove(room),
    });
    let layoutSave = Promise.resolve();
    const dashboard = new window.LocalBase.components.OrganizationDashboard({
        root: byId('flzroom-admin'),
        onChange: layout => {
            layoutSave = layoutSave.then(() => client.request('/api/admin/layout', {
                method: 'PUT',
                body: JSON.stringify({ layout }),
            })).catch(error => notice.error(error, translate('flzroom', 'Das persönliche Kartenlayout konnte nicht gespeichert werden.')));
        },
    });

    const demoConfirmation = byId('flz-room-demo-confirm');
    const demoButton = byId('flz-room-demo-install');
    const demoNotice = byId('flz-room-demo-notice');
    demoConfirmation.addEventListener('change', () => { demoButton.disabled = !demoConfirmation.checked; });
    demoButton.addEventListener('click', async () => {
        if (!demoConfirmation.checked) return;
        demoButton.disabled = true;
        demoNotice.hidden = false;
        demoNotice.className = 'flz-room-notice';
        demoNotice.textContent = translate('flzroom', 'Demo-Pack wird geprüft und installiert …');
        try {
            const response = await client.request('/api/admin/demo-pack/install', { method: 'POST', body: '{}' });
            demoNotice.classList.add('is-success');
            demoNotice.textContent = `${response.result.rooms} Räume synchronisiert; ${response.result.createdBookings} Buchungen angelegt.`;
            demoConfirmation.checked = false;
            await load();
        } catch (error) {
            demoNotice.classList.add('is-error');
            demoNotice.textContent = error.message || translate('flzroom', 'Das Demo-Pack konnte nicht installiert werden.');
            demoButton.disabled = false;
        }
    });

    async function load() {
        try {
            const state = await repository.rooms();
            settings.render(state.rooms, true);
        } catch (error) {
            notice.error(error, translate('flzroom', 'Die Räume konnten nicht geladen werden.'));
            byId('flz-room-admin-room-form').querySelector('button[type="submit"]').disabled = true;
        }
    }

    async function loadAdminSettings() {
        try {
            const response = await client.request('/api/admin/settings');
            dashboard.set(response.dashboardLayout);
        } catch (error) {
            notice.error(error, translate('flzroom', 'Die Admin-Einstellungen konnten nicht geladen werden.'));
        }
    }

    void Promise.all([load(), loadAdminSettings()]);
}());
