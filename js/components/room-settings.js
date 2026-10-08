(function() {
    'use strict';

    /** Zweck: Rendert die administrative Raumtabelle und übergibt Änderungen an den Raumworkflow. */
    class RoomSettings {
        constructor(options) {
            this.section = options.section;
            this.body = options.body;
            this.form = options.form;
            this.onCreate = options.onCreate;
            this.onUpdate = options.onUpdate;
            this.onRemove = options.onRemove;
            this.form.addEventListener('submit', event => this.create(event));
        }

        render(rooms, canManage) {
            this.section.hidden = !canManage;
            if (!canManage) return;
            this.body.replaceChildren(...rooms.map(room => this.row(room)));
        }

        row(room) {
            const row = document.createElement('tr');
            const name = this.input(room.name, 'Raumname');
            const description = this.input(room.description, 'Beschreibung');
            const order = this.input(String(room.sortOrder), 'Reihenfolge', 'number');
            for (const input of [name, description, order]) {
                const cell = document.createElement('td');
                cell.append(input);
                row.append(cell);
            }
            const actions = document.createElement('td');
            const save = this.button('✓', 'Raum speichern');
            save.addEventListener('click', () => {
                void this.onUpdate(room.id, this.payload(name.value, description.value, order.value));
            });
            const remove = this.button('×', 'Raum samt Buchungen löschen');
            remove.addEventListener('click', () => { void this.onRemove(room); });
            actions.append(save, remove);
            row.append(actions);
            return row;
        }

        input(value, label, type = 'text') {
            const input = document.createElement('input');
            input.type = type;
            input.value = value;
            input.setAttribute('aria-label', label);
            if (type === 'number') input.min = '0';
            return input;
        }

        button(icon, label) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flz-room-icon-button';
            button.title = label;
            button.setAttribute('aria-label', label);
            const symbol = document.createElement('span');
            symbol.setAttribute('aria-hidden', 'true');
            symbol.textContent = icon;
            button.append(symbol);
            return button;
        }

        async create(event) {
            event.preventDefault();
            const data = new FormData(this.form);
            const created = await this.onCreate(this.payload(data.get('name'), data.get('description'), data.get('sortOrder')));
            if (created) this.form.reset();
        }

        payload(name, description, sortOrder) {
            return {
                name: String(name || ''),
                description: String(description || ''),
                sortOrder: Number(sortOrder || 0),
            };
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.RoomSettings = RoomSettings;
}());
