(function() {
    'use strict';

    /**
     * Zweck: Berechnet die gemeinsamen Zeitpunkte, Rasterhöhen und Grid-Linien eines Buchungstags.
     * Zusammenspiel: MonthCalendar verwendet dieselbe Instanz für alle Raumspalten eines Tages.
     * Vertrag: Der sichtbare Tag umfasst den ganzen Kalendertag; jede Buchungsgrenze wird zu einer gemeinsamen Grid-Linie.
     */
    class BookingTimeline {
        constructor(dayStart = 0, dayEnd = 1440) {
            this.dayStart = dayStart;
            this.dayEnd = dayEnd;
        }

        points(bookings) {
            const bookingPoints = bookings.flatMap(booking => [this.minute(booking.startsAt), this.minute(booking.endsAt)]);
            return [...new Set([this.dayStart, this.dayEnd, ...bookingPoints])].sort((a, b) => a - b);
        }

        rows(points) {
            return points.slice(0, -1).map((start, index) => {
                const minutes = points[index + 1] - start;
                const height = Math.max(6, Math.min(36, Math.round(minutes / 5)));
                return `minmax(${height}px, auto)`;
            }).join(' ');
        }

        line(points, minute) {
            return Math.max(1, points.indexOf(minute) + 1);
        }

        minute(value) {
            return window.AdRoom.BookingWallTime.parts(value).minute;
        }
    }

    window.AdRoom = window.AdRoom || {};
    window.AdRoom.BookingTimeline = BookingTimeline;
}());
