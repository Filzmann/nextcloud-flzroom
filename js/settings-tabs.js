(function() {
    'use strict';

    window.FlzRoom = window.FlzRoom || {};

    class SettingsTabs {
        constructor(root = document) {
            this.root = root;
            this.tablist = root.querySelector('[role="tablist"]');
            this.tabs = this.tablist ? Array.from(this.tablist.querySelectorAll('[role="tab"]')) : [];
            this.tabs.forEach(tab => {
                tab.addEventListener('click', () => this.activate(tab, false));
                tab.addEventListener('keydown', event => this.onKeydown(event, tab));
            });
            const hashTarget = this.hashTarget(window.location?.hash || '');
            const selected = this.tabs.find(tab => {
                const panelId = tab.getAttribute('aria-controls');
                const panel = panelId ? this.root.getElementById(panelId) : null;
                return hashTarget && panel && (panel === hashTarget || panel.contains(hashTarget));
            }) || this.tabs.find(tab => tab.getAttribute('aria-selected') === 'true') || this.tabs[0];
            if (selected) this.activate(selected, false);
        }

        hashTarget(hash) {
            if (!hash.startsWith('#') || hash.length < 2) return null;
            try {
                return this.root.getElementById(decodeURIComponent(hash.slice(1)));
            } catch (error) {
                return null;
            }
        }

        activate(target, focus = false) {
            this.tabs.forEach(tab => {
                const selected = tab === target;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.setAttribute('tabindex', selected ? '0' : '-1');
                const panelId = tab.getAttribute('aria-controls');
                const panel = panelId ? this.root.getElementById(panelId) : null;
                if (panel) panel.hidden = !selected;
            });
            if (focus) target.focus();
        }

        onKeydown(event, tab) {
            const index = this.tabs.indexOf(tab);
            if (index < 0) return;
            let targetIndex = null;
            if (event.key === 'ArrowLeft') targetIndex = (index - 1 + this.tabs.length) % this.tabs.length;
            if (event.key === 'ArrowRight') targetIndex = (index + 1) % this.tabs.length;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = this.tabs.length - 1;
            if (targetIndex === null) return;
            event.preventDefault();
            this.activate(this.tabs[targetIndex], true);
        }
    }

    window.FlzRoom.SettingsTabs = SettingsTabs;
    new SettingsTabs(document);
}());
