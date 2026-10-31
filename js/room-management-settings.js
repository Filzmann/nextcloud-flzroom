(function() {
    'use strict';

    const byId = window.LocalBase.ui.byId;
    const section = byId('flz-room-settings-rooms');
    const body = byId('flz-room-settings-room-body');
    const form = byId('flz-room-settings-room-form');
    if (!section || !body || !form) return;

    const client = new window.LocalBase.api.ApiClient({ appId: 'flzroom' });
    const repository = new window.FlzRoom.RoomRepository(client);
    const notice = new window.LocalBase.ui.Notice('flz-room-notice', { baseClass: 'flz-room-notice', typeClassPrefix: 'is-' });
    const workflow = new window.FlzRoom.RoomWorkflow({ repository, notice, reload: load });
    const settings = new window.FlzRoom.RoomSettings({
        section,
        body,
        form,
        onCreate: payload => workflow.create(payload),
        onUpdate: (id, payload) => workflow.update(id, payload),
        onRemove: room => workflow.remove(room),
    });

    async function load() {
        try {
            const state = await repository.rooms();
            settings.render(state.rooms, true);
        } catch (error) {
            notice.error(error, 'Die Räume konnten nicht geladen werden.');
            form.querySelector('button[type="submit"]').disabled = true;
        }
    }

    void load();
}());
