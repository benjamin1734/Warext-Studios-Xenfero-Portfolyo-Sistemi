(() => {
'use strict';
const copy = async (value) => {
  if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(value); return; }
  const input=document.createElement('textarea'); input.value=value; input.setAttribute('readonly','readonly'); input.style.position='fixed'; input.style.opacity='0'; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove();
};
document.addEventListener('click',(event)=>{
  const button=event.target.closest('[data-wrxt-copy]'); if(!button)return; event.preventDefault(); const value=button.getAttribute('data-wrxt-copy')||''; if(!value)return;
  copy(value).then(()=>{const old=button.textContent; button.textContent='Bağlantı kopyalandı'; window.setTimeout(()=>button.textContent=old,1600);}).catch(()=>{});
});
})();
