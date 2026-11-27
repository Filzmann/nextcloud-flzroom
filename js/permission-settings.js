(function() {
    'use strict';
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)


    window.FlzRoom = window.FlzRoom || {};

    class PermissionSettings {
        constructor({ form, status, client, document: ownerDocument = document }) {
            this.form = form;
            this.status = status;
            this.client = client;
            this.document = ownerDocument;
            this.body = form.querySelector('[data-permission-matrix-body]');
            this.submit = form.querySelector('[type="submit"]');
            this.configuration = null;
            this.checkboxes = [];
            if (this.submit) this.submit.disabled = true;
            form.addEventListener('submit', event => {
                event.preventDefault();
                void this.save();
            });
        }

        render(configuration) {
            this.configuration = configuration;
            this.body.replaceChildren();
            this.checkboxes = [];
            const available = configuration.status === 'VALID';
            if (!available) {
                if (this.submit) this.submit.disabled = true;
                this.setStatus(configuration.message || translate('flzroom', 'Die Organigrammrollen sind derzeit nicht verfügbar.'), true);
                return;
            }

            const roles = Array.isArray(configuration.roles) ? configuration.roles : [];
            const permissions = Array.isArray(configuration.permissions) ? configuration.permissions : [];
            const assignments = configuration.assignments || {};
            roles.forEach(role => {
                const row = this.document.createElement('tr');
                const heading = this.document.createElement('th');
                heading.setAttribute('scope', 'row');
                heading.textContent = role.label;
                row.append(heading);
                permissions.forEach(permission => {
                    const cell = this.document.createElement('td');
                    const checkbox = this.document.createElement('input');
                    checkbox.setAttribute('type', 'checkbox');
                    checkbox.setAttribute('aria-label', `${permission.label}: ${role.label}`);
                    checkbox.dataset.permissionKey = permission.permissionKey;
                    checkbox.value = role.roleKey;
                    checkbox.checked = Array.isArray(assignments[permission.permissionKey])
                        && assignments[permission.permissionKey].includes(role.roleKey);
                    cell.append(checkbox);
                    row.append(cell);
                    this.checkboxes.push(checkbox);
                });
                this.body.append(row);
            });
            if (this.submit) this.submit.disabled = false;
            this.setStatus(translate('flzroom', 'Die Rechtezuordnung ist geladen. Änderungen werden erst mit „Rechte speichern“ wirksam.'));
        }

        setStatus(message, isError = false) {
            this.status.setAttribute('role', isError ? 'alert' : 'status');
            this.status.textContent = message;
        }

        showError(error) {
            this.setStatus(error?.message || translate('flzroom', 'Die Rechtezuordnung konnte nicht gespeichert werden.'), true);
        }

        async load() {
            this.render(await this.client.request('/api/privacy/permission-settings'));
        }

        async save() {
            const assignments = {};
            (this.configuration?.permissions || []).forEach(permission => {
                assignments[permission.permissionKey] = [];
            });
            this.checkboxes.forEach(checkbox => {
                if (checkbox.checked && assignments[checkbox.dataset.permissionKey]) {
                    assignments[checkbox.dataset.permissionKey].push(checkbox.value);
                }
            });
            if (this.submit) this.submit.disabled = true;
            try {
                const response = await this.client.request('/api/privacy/permission-settings', {
                    method: 'PUT',
                    body: JSON.stringify({ assignments }),
                });
                this.render({ ...this.configuration, ...response, status: 'VALID', message: null });
                this.setStatus(translate('flzroom', 'Die Rechtezuordnung wurde gespeichert.'));
            } catch (error) {
                this.showError(error);
                if (this.submit && this.configuration?.status === 'VALID') this.submit.disabled = false;
            }
        }
    }

    window.FlzRoom.PermissionSettings = PermissionSettings;

    const form = document.getElementById('flz-room-permission-settings-form');
    const status = document.getElementById('flz-room-permission-settings-status');
    if (!form || !status) return;
    const settings = new PermissionSettings({
        form,
        status,
        client: new window.OCA.FlzRoom.Shared.api.ApiClient({ appId: 'flzroom' }),
        document,
    });
    void settings.load().catch(error => settings.showError(error));
}());
