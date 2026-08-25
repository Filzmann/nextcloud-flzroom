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
    const fullAccessForm = byId('adr-full-access-form');
    const fullAccessHistory = byId('adr-full-access-history');
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

    fullAccessForm.addEventListener('submit', async event => {
        event.preventDefault();
        const fields = new FormData(fullAccessForm);
        if (fields.get('enabled') !== 'on') return;
        try {
            await client.request('/api/admin/full-access', {
                method: 'POST',
                body: JSON.stringify({
                    targetUid: String(fields.get('targetUid') || '').trim(),
                    durationMinutes: Number(fields.get('durationMinutes')),
                }),
            });
            fullAccessForm.elements.enabled.checked = false;
            notice.success('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');
            await loadFullAccess();
            await load();
        } catch (error) {
            notice.error(error, 'Der Vollzugriff konnte nicht aktiviert werden.');
        }
    });

    fullAccessHistory.addEventListener('click', async event => {
        const button = event.target.closest('button[data-revoke-uid]');
        if (!button) return;
        button.disabled = true;
        try {
            await client.request(`/api/admin/full-access/${encodeURIComponent(button.dataset.revokeUid)}`, { method: 'DELETE' });
            notice.success('Der Vollzugriff wurde widerrufen.');
            await loadFullAccess();
            await load();
        } catch (error) {
            button.disabled = false;
            notice.error(error, 'Der Vollzugriff konnte nicht widerrufen werden.');
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

    async function loadFullAccess() {
        try {
            const state = await client.request('/api/admin/full-access');
            fullAccessHistory.replaceChildren();
            if (!state.history?.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 6;
                cell.textContent = 'Noch keine Freigabe protokolliert.';
                row.append(cell);
                fullAccessHistory.append(row);
                return;
            }
            state.history.forEach(grant => fullAccessHistory.append(renderFullAccessRow(grant)));
        } catch (error) {
            notice.error(error, 'Die Vollzugriffshistorie konnte nicht geladen werden.');
        }
    }

    function renderFullAccessRow(grant) {
        const row = document.createElement('tr');
        const now = Date.now();
        const active = !grant.revokedAt && Date.parse(grant.endsAt) > now;
        const values = [grant.targetUid, grant.grantedBy, formatDateTime(grant.startsAt), formatDateTime(grant.endsAt), `${formatDateTime(grant.revokedAt || grant.endsAt)} · ${active ? 'aktiv' : (grant.revokedAt ? 'widerrufen' : 'abgelaufen')}`];
        values.forEach(value => {
            const cell = document.createElement('td');
            cell.textContent = String(value || '—');
            row.append(cell);
        });
        const action = document.createElement('td');
        if (active) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.revokeUid = grant.targetUid;
            button.textContent = 'Widerrufen';
            action.append(button);
        } else {
            action.textContent = '—';
        }
        row.append(action);
        return row;
    }

    function formatDateTime(value) {
        if (!value) return '—';
        return new Intl.DateTimeFormat('de-DE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
    }

    void Promise.all([load(), loadAdminSettings(), loadFullAccess()]);
}());
