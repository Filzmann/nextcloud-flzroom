(function() {
    'use strict';

    const form = document.getElementById('adr-organization-groups-form');
    const status = document.getElementById('adr-organization-groups-status');
    if (!form || !status) return;

    const client = new window.LocalBase.api.ApiClient({ appId: 'adroom' });
    const field = form.elements.organizationGroupIds;
    const render = groupIds => {
        field.value = groupIds.join('\n');
        status.setAttribute('role', 'status');
        status.textContent = groupIds.length === 0
            ? 'Der reguläre Zugriff ist gesperrt, weil keine Organisationsgruppe konfiguriert ist.'
            : `${groupIds.length} Organisationsgruppe${groupIds.length === 1 ? '' : 'n'} ist/sind konfiguriert.`;
    };
    const showError = error => {
        status.setAttribute('role', 'alert');
        status.textContent = error?.message || 'Die Organisationsgruppen konnten nicht gespeichert werden.';
    };
    const load = async () => {
        const response = await client.request('/api/privacy/organization-groups');
        render(response.organizationGroupIds);
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const organizationGroupIds = String(field.value || '')
            .split(/\r?\n/)
            .map(value => value.trim())
            .filter(Boolean);
        try {
            const response = await client.request('/api/privacy/organization-groups', {
                method: 'PUT',
                body: JSON.stringify({ organizationGroupIds }),
            });
            render(response.organizationGroupIds);
        } catch (error) {
            showError(error);
        }
    });
    void load().catch(showError);
}());
