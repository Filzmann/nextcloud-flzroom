(function() {
    'use strict';

    const byId = window.LocalBase.ui.byId;
    const client = new window.LocalBase.api.ApiClient({ appId: 'flzroom' });
    const repository = new window.FlzRoom.RoomRepository(client);
    const notice = new window.LocalBase.ui.Notice('flz-room-notice', { baseClass: 'flz-room-notice', typeClassPrefix: 'is-' });
    const calendar = new window.FlzRoom.MonthCalendar(byId('flz-room-calendar-head'), byId('flz-room-calendar-body'));
    let month = formatMonth(new Date());
    let loadSequence = 0;
    let workflow;
    const dialog = new window.FlzRoom.BookingDialog(
        byId('flz-room-booking-dialog'),
        byId('flz-room-booking-form'),
        data => workflow.save(data),
    );
    workflow = new window.FlzRoom.BookingWorkflow({ repository, notice, dialog, reload: load });

    async function load() {
        const sequence = ++loadSequence;
        const requestedMonth = month;
        try {
            const data = await repository.month(requestedMonth);
            if (sequence !== loadSequence) return;
            byId('flz-room-month').value = requestedMonth;
            calendar.render(data);
            dialog.setRooms(data.rooms);
        } catch (error) {
            if (sequence === loadSequence) notice.error(error, 'Der Raumplan konnte nicht geladen werden.');
        }
    }

    function shiftMonth(delta) {
        const [year, value] = month.split('-').map(Number);
        const next = new Date(year, value - 1 + delta, 1);
        month = formatMonth(next);
        void load();
    }

    function formatMonth(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    }

    byId('flz-room-previous').addEventListener('click', () => shiftMonth(-1));
    byId('flz-room-next').addEventListener('click', () => shiftMonth(1));
    byId('flz-room-month').addEventListener('change', event => {
        if (!event.target.value) return;
        month = event.target.value;
        void load();
    });
    window.addEventListener('flzroom:add-booking', event => dialog.create(event.detail.room, event.detail.date));
    window.addEventListener('flzroom:edit-booking', event => dialog.edit(event.detail.booking));
    window.addEventListener('flzroom:delete-booking', event => { void workflow.remove(event.detail.booking); });
    void load();
}());
