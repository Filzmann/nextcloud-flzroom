(function() {
    'use strict';

    /**
     * Zweck: Blendet die „Erste Schritte“-Karte im Adminbereich ein, solange noch kein Raum existiert.
     * Vertrag: Die Karte ist rein informativ und erteilt keine Rechte. Sie verschwindet, sobald
     * mindestens ein Raum geladen wurde; bei Ladefehlern bleibt sie unverändert verborgen.
     */
    class FirstStart {
        constructor(options) {
            this.card = options.card ?? null;
            this.button = options.button ?? null;
            this.focusTarget = options.focusTarget ?? null;
            if (this.button && this.focusTarget) {
                this.button.addEventListener('click', () => {
                    this.focusTarget.focus();
                });
            }
        }

        render(rooms) {
            if (!this.card) return;
            const empty = !Array.isArray(rooms) || rooms.length === 0;
            this.card.hidden = !empty;
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.FirstStart = FirstStart;
}());
