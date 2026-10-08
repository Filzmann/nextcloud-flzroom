(function() {
    'use strict';

    const BaseModel = window.LocalBase.models.Model;

    /** Zweck: Repräsentiert einen hydratisierten Raum aus Monats- und Adminantworten. */
    class Room extends BaseModel {
        constructor(data = {}) {
            super();
            this.id = Number(data.id || 0);
            this.name = String(data.name || '');
            this.description = String(data.description || '');
            this.sortOrder = Number(data.sortOrder || 0);
        }

        toArray() {
            return {
                id: this.id,
                name: this.name,
                description: this.description,
                sortOrder: this.sortOrder,
            };
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.Room = Room;
}());
