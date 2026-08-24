(function() {
    'use strict';

    /** Zweck: Liest die fachlichen Wandzeitbestandteile eines Buchungszeitpunkts ohne Umrechnung in die Browserzeitzone. */
    class BookingWallTime {
        static parts(value) {
            const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
            if (!match) throw new TypeError('Ungültiger Buchungszeitpunkt.');
            return {
                date: `${match[1]}-${match[2]}-${match[3]}`,
                time: `${match[4]}:${match[5]}`,
                minute: Number(match[4]) * 60 + Number(match[5]),
            };
        }
    }

    window.AdRoom = window.AdRoom || {};
    window.AdRoom.BookingWallTime = BookingWallTime;
}());
