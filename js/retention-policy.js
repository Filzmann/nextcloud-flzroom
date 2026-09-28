(function() {
    'use strict';
    const form=document.getElementById('adr-retention-policy-form');
    const status=document.getElementById('adr-retention-policy-status');
    if(!form||!status)return;
    const client=new window.LocalBase.api.ApiClient({appId:'adroom'});
    const render=policy=>{form.elements.durationPeriod.value=policy.durationPeriod;form.elements.adminHistoryDurationPeriod.value=policy.adminHistoryDurationPeriod;form.elements.expectedRevision.value=String(policy.revision);status.textContent=policy.reviewDue?'Die jährliche Prüfung ist fällig.':'Policyversion '+policy.revision+' ist aktuell.';};
    const load=async()=>{const response=await client.request('/api/privacy/retention-policy');render(response.retentionPolicy);};
    form.addEventListener('submit',async event=>{event.preventDefault();try{const fields=new FormData(form);const response=await client.request('/api/privacy/retention-policy',{method:'PUT',body:JSON.stringify({durationPeriod:String(fields.get('durationPeriod')),adminHistoryDurationPeriod:String(fields.get('adminHistoryDurationPeriod')),expectedRevision:Number(fields.get('expectedRevision'))})});render(response.retentionPolicy);}catch(error){status.setAttribute('role','alert');status.textContent=error.message||'Die Policy konnte nicht gespeichert werden.';}});
    form.querySelector('[data-retention-review]').addEventListener('click',async()=>{try{const response=await client.request('/api/privacy/retention-policy/review',{method:'POST',body:JSON.stringify({expectedRevision:Number(form.elements.expectedRevision.value)})});render(response.retentionPolicy);}catch(error){status.setAttribute('role','alert');status.textContent=error.message||'Die Prüfung konnte nicht protokolliert werden.';}});
    void load().catch(error=>{status.setAttribute('role','alert');status.textContent=error.message||'Die Policy konnte nicht geladen werden.';});
}());
