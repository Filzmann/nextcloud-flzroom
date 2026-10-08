(function() {
    'use strict';

    const BaseModel = window.LocalBase.models.Model;

    /** Zweck: Repräsentiert eine Raumbuchung einschließlich Besitzeranzeige und serverseitig berechneter UI-Capability. */
    class Booking extends BaseModel {
        constructor(data = {}) {
            super();
            this.id = Number(data.id || 0);
            this.roomId = Number(data.roomId || 0);
            this.userUid = String(data.userUid || '');
            this.userName = String(data.userName || data.userUid || '');
            this.purpose = String(data.purpose || '');
            this.title = String(data.title || '');
            this.startsAt = String(data.startsAt || '');
            this.endsAt = String(data.endsAt || '');
            this.canManage = Boolean(data.canManage);
            this.requiresInterventionReason = Boolean(data.requiresInterventionReason);
        }

        toArray() {
            return {
                id: this.id,
                roomId: this.roomId,
                userUid: this.userUid,
                userName: this.userName,
                purpose: this.purpose,
                title: this.title,
                startsAt: this.startsAt,
                endsAt: this.endsAt,
                canManage: this.canManage,
                requiresInterventionReason: this.requiresInterventionReason,
            };
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.Booking = Booking;
}());
