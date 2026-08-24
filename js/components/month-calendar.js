(function() {
    'use strict';

    const dayNames = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'];
    const pad = value => String(value).padStart(2, '0');

    /**
     * Zweck: Rendert die Monatsmatrix mit einer gemeinsamen vertikalen Zeitachse je Tag.
     * Zusammenspiel: BookingTimeline berechnet Grid-Linien; main.js reagiert auf die ausgelösten Buchungsereignisse.
     */
    class MonthCalendar {
        constructor(head, body) {
            this.head = head;
            this.body = body;
            this.state = null;
            this.timeline = new window.AdRoom.BookingTimeline();
        }

        render(state) {
            this.state = state;
            this.renderHead();
            this.renderBody();
        }

        renderHead() {
            const row = document.createElement('tr');
            const day = document.createElement('th');
            day.scope = 'col';
            day.textContent = 'Tag';
            row.append(day);
            for (const room of this.state.rooms) {
                const heading = document.createElement('th');
                heading.scope = 'col';
                heading.textContent = room.name;
                if (room.description) heading.title = room.description;
                row.append(heading);
            }
            this.head.replaceChildren(row);
        }

        renderBody() {
            const [year, month] = this.state.month.split('-').map(Number);
            if (this.state.rooms.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.textContent = 'Noch keine Räume angelegt.';
                row.append(cell);
                this.body.replaceChildren(row);
                return;
            }
            const days = new Date(year, month, 0).getDate();
            this.body.replaceChildren(...Array.from({ length: days }, (_, index) => this.dayRow(year, month, index + 1)));
        }

        dayRow(year, month, day) {
            const date = new Date(year, month - 1, day);
            const dateKey = `${year}-${pad(month)}-${pad(day)}`;
            const holiday = this.state.holidays[dateKey];
            const row = document.createElement('tr');
            if (date.getDay() === 6) row.classList.add('is-saturday');
            if (date.getDay() === 0) row.classList.add('is-sunday');
            if (holiday) row.classList.add('is-holiday');
            row.append(this.dayLabel(date, month, day, holiday));
            const scheduleCell = document.createElement('td');
            scheduleCell.colSpan = this.state.rooms.length;
            scheduleCell.className = 'adr-day-schedule-cell';
            scheduleCell.append(this.daySchedule(dateKey));
            row.append(scheduleCell);
            return row;
        }

        dayLabel(date, month, day, holiday) {
            const label = document.createElement('th');
            label.scope = 'row';
            const value = document.createElement('strong');
            value.textContent = `${dayNames[date.getDay()]}, ${pad(day)}.${pad(month)}.`;
            label.append(value);
            if (holiday) {
                const note = document.createElement('small');
                note.textContent = holiday;
                label.append(note);
            }
            return label;
        }

        daySchedule(dateKey) {
            const bookings = this.bookingsFor(dateKey);
            const points = this.timeline.points(bookings);
            const schedule = document.createElement('div');
            schedule.className = 'adr-day-schedule';
            schedule.setAttribute('role', 'group');
            schedule.setAttribute('aria-label', `Buchungen am ${dateKey}`);
            schedule.style.gridTemplateColumns = `repeat(${this.state.rooms.length}, minmax(150px, 1fr))`;
            schedule.style.gridTemplateRows = this.timeline.rows(points);
            this.state.rooms.forEach((room, index) => schedule.append(this.roomLane(room, index, dateKey, points.length)));
            for (const booking of bookings) this.appendBooking(schedule, booking, points);
            return schedule;
        }

        roomLane(room, index, dateKey, lineCount) {
            const lane = document.createElement('div');
            lane.className = 'adr-room-lane';
            lane.style.gridColumn = String(index + 1);
            lane.style.gridRow = `1 / ${lineCount}`;
            lane.setAttribute('aria-label', room.name);
            const add = this.iconButton('+', `Buchung für ${room.name} anlegen`);
            add.classList.add('adr-add');
            add.addEventListener('click', () => this.dispatch('adroom:add-booking', { room, date: dateKey }));
            lane.append(add);
            return lane;
        }

        appendBooking(schedule, booking, points) {
            const roomIndex = this.state.rooms.findIndex(room => room.id === booking.roomId);
            if (roomIndex < 0) return;
            const card = this.bookingCard(booking);
            card.style.gridColumn = String(roomIndex + 1);
            card.style.gridRow = `${this.timeline.line(points, this.timeline.minute(booking.startsAt))} / ${this.timeline.line(points, this.timeline.minute(booking.endsAt))}`;
            schedule.append(card);
        }

        bookingCard(booking) {
            const card = document.createElement('article');
            card.className = 'adr-booking';
            card.append(
                this.node('strong', `${this.time(booking.startsAt)}–${this.time(booking.endsAt)}`),
                this.node('span', booking.purpose, 'adr-booking-purpose'),
                this.node('span', booking.title, 'adr-booking-title'),
                this.node('small', booking.userName),
            );
            if (booking.canManage) {
                const actions = document.createElement('div');
                actions.className = 'adr-booking-actions';
                actions.append(
                    this.actionButton('✎', 'Buchung bearbeiten', 'adroom:edit-booking', booking),
                    this.actionButton('×', 'Buchung löschen', 'adroom:delete-booking', booking),
                );
                card.append(actions);
            }
            return card;
        }

        actionButton(icon, label, eventName, booking) {
            const button = this.iconButton(icon, label);
            button.addEventListener('click', () => this.dispatch(eventName, { booking }));
            return button;
        }

        iconButton(icon, label) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'adr-icon-button';
            button.title = label;
            button.setAttribute('aria-label', label);
            const symbol = document.createElement('span');
            symbol.setAttribute('aria-hidden', 'true');
            symbol.textContent = icon;
            button.append(symbol);
            return button;
        }

        bookingsFor(dateKey) {
            return this.state.bookings
                .filter(booking => this.dateKey(booking.startsAt) === dateKey)
                .sort((a, b) => this.timeline.minute(a.startsAt) - this.timeline.minute(b.startsAt));
        }

        node(tag, value, className) {
            const element = document.createElement(tag);
            element.textContent = value;
            if (className) element.className = className;
            return element;
        }

        dispatch(name, detail) {
            window.dispatchEvent(new CustomEvent(name, { detail }));
        }

        dateKey(value) {
            return window.AdRoom.BookingWallTime.parts(value).date;
        }

        time(value) {
            return window.AdRoom.BookingWallTime.parts(value).time;
        }
    }

    window.AdRoom = window.AdRoom || {};
    window.AdRoom.MonthCalendar = MonthCalendar;
}());
