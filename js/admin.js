(function() {
    'use strict';

    const byId = window.LocalBase.ui.byId;
    const client = new window.LocalBase.api.ApiClient({ appId: 'adroom' });
    const repository = new window.AdRoom.RoomRepository(client);
    const notice = new window.LocalBase.ui.Notice('adr-admin-notice', { baseClass: 'adr-notice', typeClassPrefix: 'is-' });
    const workflow = new window.AdRoom.RoomWorkflow({ repository, notice, reload: load });
    const settings = new window.AdRoom.RoomSettings({
        section: byId('adroom-admin'),
        body: byId('adr-admin-room-body'),
        form: byId('adr-admin-room-form'),
        onCreate: payload => workflow.create(payload),
        onUpdate: (id, payload) => workflow.update(id, payload),
        onRemove: room => workflow.remove(room),
    });
    const retentionForm = byId('adr-retention-form');
    let layoutSave = Promise.resolve();
    const dashboard = new window.LocalBase.components.OrganizationDashboard({
        root: byId('adroom-admin'),
        onChange: layout => {
            layoutSave = layoutSave.then(() => client.request('/api/admin/layout', {
                method: 'PUT',
                body: JSON.stringify({ layout }),
            })).catch(error => notice.error(error, 'Das persönliche Kartenlayout konnte nicht gespeichert werden.'));
        },
    });

    retentionForm.addEventListener('submit', async event => {
        event.preventDefault();
        const fields = new FormData(retentionForm);
        try {
            const response = await client.request('/api/admin/retention-policy', {
                method: 'PUT',
                body: JSON.stringify({
                    enabled: fields.get('enabled') === 'on',
                    reviewAfterDays: Number(fields.get('reviewAfterDays')),
                    action: String(fields.get('action')),
                }),
            });
            renderRetention(response.retentionPolicy);
            notice.success('Retention-Regel wurde gespeichert.');
        } catch (error) {
            notice.error(error, 'Die Retention-Regel konnte nicht gespeichert werden.');
        }
    });

    const demoConfirmation = byId('adr-demo-confirm');
    const demoButton = byId('adr-demo-install');
    const demoNotice = byId('adr-demo-notice');
    demoConfirmation.addEventListener('change', () => { demoButton.disabled = !demoConfirmation.checked; });
    demoButton.addEventListener('click', async () => {
        if (!demoConfirmation.checked) return;
        demoButton.disabled = true;
        demoNotice.hidden = false;
        demoNotice.className = 'adr-notice';
        demoNotice.textContent = 'Demo-Pack wird geprüft und installiert …';
        try {
            const response = await client.request('/api/admin/demo-pack/install', { method: 'POST', body: '{}' });
            demoNotice.classList.add('is-success');
            demoNotice.textContent = `${response.result.rooms} Räume synchronisiert; ${response.result.createdBookings} Buchungen angelegt.`;
            demoConfirmation.checked = false;
            await load();
        } catch (error) {
            demoNotice.classList.add('is-error');
            demoNotice.textContent = error.message || 'Das Demo-Pack konnte nicht installiert werden.';
            demoButton.disabled = false;
        }
    });

    async function load() {
        try {
            const now = new Date();
            const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
            const state = await repository.month(month);
            if (!state.capabilities?.canManageRooms) throw new Error('Keine Berechtigung zur Raumverwaltung.');
            settings.render(state.rooms, true);
        } catch (error) {
            notice.error(error, 'Die Räume konnten nicht geladen werden.');
            byId('adr-admin-room-form').querySelector('button[type="submit"]').disabled = true;
        }
    }

    function renderRetention(policy) {
        retentionForm.elements.enabled.checked = policy.enabled === true;
        retentionForm.elements.reviewAfterDays.value = String(policy.reviewAfterDays ?? 0);
        retentionForm.elements.action.value = policy.action || 'REVIEW';
    }

    async function loadAdminSettings() {
        try {
            const response = await client.request('/api/admin/settings');
            renderRetention(response.retentionPolicy);
            dashboard.set(response.dashboardLayout);
        } catch (error) {
            notice.error(error, 'Die Admin-Einstellungen konnten nicht geladen werden.');
            retentionForm.querySelector('button[type="submit"]').disabled = true;
        }
    }

    void Promise.all([load(), loadAdminSettings()]);
}());
