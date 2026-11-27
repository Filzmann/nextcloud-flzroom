(function() {
    'use strict';

    const shared = window.OCA.FlzRoom?.Shared?.ui;
    if (!shared?.AdminAccessOverlay || !window.OCA.FlzRoom?.Shared?.api?.ApiClient) return;

    const client = new window.OCA.FlzRoom.Shared.api.ApiClient({ appId: 'flzroom' });

    new shared.AdminAccessOverlay({
        overlayId: 'flz-room-admin-access-overlay',
        triggerId: 'flz-room-admin-grant-warning',
        closeId: 'flz-room-admin-access-overlay-close',
        formId: 'flz-room-full-access-form',
        historyId: 'flz-room-full-access-history',
        statusId: 'flz-room-full-access-status',
        api: {
            getState: () => client.request('/api/admin/full-access'),
            activate: (targetUid, durationMinutes) => client.request('/api/admin/full-access', {
                method: 'POST',
                body: JSON.stringify({ targetUid, durationMinutes }),
            }),
            revoke: (targetUid) => client.request(`/api/admin/full-access/${encodeURIComponent(targetUid)}`, { method: 'DELETE' }),
        },
    });
}());
