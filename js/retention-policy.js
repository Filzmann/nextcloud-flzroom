(function()
{
    'use strict';

    const form = document.getElementById('flz-room-retention-policy-form');
    const status = document.getElementById('flz-room-retention-policy-status');
    if (!form || !status)
    {
        return;
    }

    const client = new window.OCA.FlzRoom.Shared.api.ApiClient({appId: 'flzroom'});
    const periodFields = [
        'durationPeriod',
        'adminHistoryDurationPeriod',
        'interventionAuditDurationPeriod',
        'failedNotificationDurationPeriod',
    ];

    const render = policy =>
    {
        for (const field of periodFields)
        {
            form.elements[field].value = policy[field];
        }
        form.elements.expectedRevision.value = String(policy.revision);
        status.setAttribute('role', 'status');
        status.textContent = policy.reviewDue
            ? 'Die jährliche Prüfung ist fällig.'
            : 'Policyversion ' + policy.revision + ' ist aktuell.';
    };

    const showError = (error, fallback) =>
    {
        status.setAttribute('role', 'alert');
        status.textContent = error.message || fallback;
    };

    form.addEventListener('submit', async event =>
    {
        event.preventDefault();
        try
        {
            const fields = new FormData(form);
            const policy = {};
            for (const field of periodFields)
            {
                policy[field] = String(fields.get(field));
            }
            policy.expectedRevision = Number(fields.get('expectedRevision'));
            const response = await client.request('/api/privacy/retention-policy', {
                method: 'PUT',
                body: JSON.stringify(policy),
            });
            render(response.retentionPolicy);
        }
        catch (error)
        {
            showError(error, 'Die Policy konnte nicht gespeichert werden.');
        }
    });

    form.querySelector('[data-retention-review]').addEventListener('click', async () =>
    {
        try
        {
            const response = await client.request('/api/privacy/retention-policy/review', {
                method: 'POST',
                body: JSON.stringify({expectedRevision: Number(form.elements.expectedRevision.value)}),
            });
            render(response.retentionPolicy);
        }
        catch (error)
        {
            showError(error, 'Die Prüfung konnte nicht protokolliert werden.');
        }
    });

    void client.request('/api/privacy/retention-policy')
        .then(response => render(response.retentionPolicy))
        .catch(error => showError(error, 'Die Policy konnte nicht geladen werden.'));
}());
