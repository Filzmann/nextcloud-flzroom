(function() {
    'use strict';

    /**
     * Zweck: Koordiniert Speichern und Löschen von Raumbuchungen einschließlich Rückmeldung und Neuladen.
     * Zusammenspiel: BookingDialog liefert Formulardaten; RoomRepository spricht die serverseitig geschützten Endpunkte an.
     * Vertrag: UI-Zustände erteilen keine Rechte. Eigentum, Adminrecht und Überschneidungen werden weiterhin serverseitig geprüft.
     */
    class BookingWorkflow {
        constructor(options) {
            this.repository = options.repository;
            this.notice = options.notice;
            this.dialog = options.dialog;
            this.reload = options.reload;
        }

        async save(data) {
            try {
                if (data.id) await this.repository.updateBooking(data.id, data.payload);
                else await this.repository.createBooking(data.payload);
                this.dialog.close();
                this.notice.success('Buchung gespeichert.');
                await this.reload();
            } catch (error) {
                this.dialog.showError(error, 'Die Buchung konnte nicht gespeichert werden.');
            }
        }

        async remove(booking) {
            if (!window.confirm('Diese Raumbuchung löschen?')) return;
            try {
                await this.repository.deleteBooking(booking.id);
                this.notice.success('Buchung gelöscht.');
                await this.reload();
            } catch (error) {
                this.notice.error(error, 'Die Buchung konnte nicht gelöscht werden.');
            }
        }
    }

    window.AdRoom = window.AdRoom || {};
    window.AdRoom.BookingWorkflow = BookingWorkflow;
}());
