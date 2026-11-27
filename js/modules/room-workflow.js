(function() {
    'use strict';
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)


    /**
     * Zweck: Koordiniert administrative Raumänderungen, Rückmeldungen und das anschließende Neuladen.
     * Vertrag: Die UI wird nur nach geladener Admin-Capability angeboten; jeder Endpunkt prüft das Adminrecht zusätzlich serverseitig.
     */
    class RoomWorkflow {
        constructor(options) {
            this.repository = options.repository;
            this.notice = options.notice;
            this.reload = options.reload;
        }

        create(payload) {
            return this.perform(
                () => this.repository.createRoom(payload),
                translate('flzroom', 'Raum angelegt.'),
                translate('flzroom', 'Der Raum konnte nicht angelegt werden.'),
            );
        }

        update(id, payload) {
            return this.perform(
                () => this.repository.updateRoom(id, payload),
                translate('flzroom', 'Raum gespeichert.'),
                translate('flzroom', 'Der Raum konnte nicht gespeichert werden.'),
            );
        }

        async remove(room) {
            if (!window.confirm(`Raum „${room.name}“ und alle zugehörigen Buchungen löschen?`)) return false;
            return this.perform(
                () => this.repository.deleteRoom(room.id),
                translate('flzroom', 'Raum gelöscht.'),
                translate('flzroom', 'Der Raum konnte nicht gelöscht werden.'),
            );
        }

        async perform(action, successMessage, errorMessage) {
            try {
                await action();
                this.notice.success(successMessage);
                await this.reload();
                return true;
            } catch (error) {
                this.notice.error(error, errorMessage);
                return false;
            }
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.RoomWorkflow = RoomWorkflow;
}());
