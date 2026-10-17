const $ = id => document.getElementById(id);
const BASE = (window.SERG_CONFIG?.apiBaseUrl || '').replace(/\/+$/, '');
let token = sessionStorage.getItem('serg.session') || '';
let page = 1, pages = 1;
function showMessage(text, error = false, shortUrl = '') {
  const box = $('message'); box.replaceChildren(document.createTextNode(text));
  box.className = error ? 'error' : 'notice'; box.hidden = false;
  if (shortUrl) { const b=document.createElement('button'); b.className='copy'; b.textContent='Copy link ↗'; b.addEventListener('click',()=>copyLink(shortUrl,b)); box.append(b); }
}
function authView(active) {
  $('gate').hidden=active; $('studio').hidden=!active; $('logout').hidden=!active;
}
function clearSession() { token=''; sessionStorage.removeItem('serg.session'); authView(false); }
async function api(action, data, query = {}) {
  if (!BASE) throw new Error('Set your API address in config.js first.');
  const url = new URL(BASE + '/api/handler'); url.searchParams.set('action',action);
  Object.entries(query).forEach(([key,value])=>url.searchParams.set(key,value));
  const controller=new AbortController(), timeout=setTimeout(()=>controller.abort(),20000);
  try {
    const response=await fetch(url,{method:data===undefined?'GET':'POST',headers:{...(data===undefined?{}:{'Content-Type':'application/json'}),...(token?{Authorization:'Bearer '+token}:{})},body:data===undefined?undefined:JSON.stringify(data),signal:controller.signal,credentials:'omit',cache:'no-store'});
    const result=await response.json();
    if(!response.ok){ if(response.status===401 && action!=='login') clearSession(); throw new Error(result.error || 'Request failed.'); }
    return result;
  } catch(error) { if(error.name==='AbortError') throw new Error('The backend took too long. Try again shortly.'); if(error instanceof TypeError) throw new Error('Cannot reach the API. Check its address, deployment and allowed origin.'); throw error; }
  finally { clearTimeout(timeout); }
}
async function busy(form,work) {
  const buttons=[...form.querySelectorAll('button')]; buttons.forEach(b=>b.disabled=true);
  try { await work(); } catch(error) { showMessage(error.message,true); }
  finally { buttons.forEach(b=>b.disabled=false); }
}
async function copyLink(url,button) {
  try { await navigator.clipboard.writeText(url); const old=button.textContent; button.textContent='Copied ✓'; setTimeout(()=>button.textContent=old,1800); }
  catch { window.prompt('Copy your short link:',url); }
}
function element(tag,className,text) { const el=document.createElement(tag); el.className=className; if(text!==undefined) el.textContent=text; return el; }
function renderLink(row) {
  const article=element('article','link-row'+(row.active?'':' paused'));
  const main=element('div','link-main'), title=element('div','link-title');
  const url='https://ser.gt.tc/'+row.code;
  const a=element('a','','ser.gt.tc/'); a.href=url; a.target='_blank'; a.rel='noopener noreferrer';
  a.append(element('strong','',row.code),document.createTextNode(' ↗')); title.append(a);
  if(!row.active) title.append(element('span','paused-tag','PAUSED'));
  const destination=element('p','destination',row.destination); destination.title=row.destination;
  main.append(title,element('p','link-label',row.label || 'Untitled link'),destination);
  const meta=element('div','link-meta'); const date=element('time','',new Date(row.createdAt).toLocaleDateString()); date.dateTime=row.createdAt;
  meta.append(element('strong','',Number(row.clicks).toLocaleString()),element('small','','redirects'),date);
  const actions=element('div','link-actions'), copy=element('button','copy','Copy ↗'); copy.setAttribute('aria-label','Copy link '+row.code); copy.addEventListener('click',()=>copyLink(url,copy));
  const toggle=element('button','text-button',row.active?'Pause':'Resume'); toggle.setAttribute('aria-label',(row.active?'Pause ':'Resume ')+row.code);
  toggle.addEventListener('click',async()=>{ toggle.disabled=true; try{ await api('toggle',{code:row.code,active:!row.active}); await loadLinks(); } catch(error){showMessage(error.message,true);}finally{toggle.disabled=false;} });
  actions.append(copy,toggle); article.append(main,meta,actions); return article;
}
let loadVersion=0;
async function loadLinks() {
  const version=++loadVersion;
  const result=await api('links',undefined,{page,q:$('q').value});
  if(version!==loadVersion)return;
  page=result.page; pages=result.pages; $('total').textContent=result.total.toLocaleString(); $('clicks').textContent=result.clicks.toLocaleString()+' redirect requests';
  $('links').replaceChildren();
  if(!result.links.length){ const empty=element('div','empty'); empty.append(element('span','','↗'),element('h3','',$('q').value?'Nothing here by that name.':'Your first shortcut starts above.'),element('p','','Paste a destination. We’ll take care of the five digits.')); $('links').append(empty); }
  else result.links.forEach(row=>$('links').append(renderLink(row)));
  $('prev').hidden=page<=1; $('next').hidden=page>=pages; $('page-label').textContent=pages>1?`${page} / ${pages}`:'';
}
$('login-form').addEventListener('submit',event=>{event.preventDefault();busy(event.currentTarget,async()=>{
  const result=await api('login',{password:$('password').value}); token=result.token; sessionStorage.setItem('serg.session',token); $('password').value='';
  authView(true); $('message').hidden=true; await loadLinks();
});});
$('create-form').addEventListener('submit',event=>{event.preventDefault();busy(event.currentTarget,async()=>{
  const result=await api('create',{destination:$('url').value.trim(),label:$('label').value.trim()});
  $('create-form').reset(); $('q').value=''; page=1;
  showMessage('Created https://ser.gt.tc/'+result.code,false,'https://ser.gt.tc/'+result.code); await loadLinks();
});});
$('search-form').addEventListener('submit',event=>{event.preventDefault();page=1;busy(event.currentTarget,loadLinks);});
$('prev').addEventListener('click',()=>{if(page>1){page--;loadLinks().catch(error=>showMessage(error.message,true));}});
$('next').addEventListener('click',()=>{if(page<pages){page++;loadLinks().catch(error=>showMessage(error.message,true));}});
$('logout').addEventListener('click',async()=>{let failed=false;try{await api('logout',{});}catch{failed=true;} clearSession();if(failed)showMessage('Signed out on this browser. The backend could not revoke the session; it will expire within one hour.',true);else $('message').hidden=true;});
if(!BASE){$('setup-note').hidden=false;$('login-button').disabled=true;}
else if(token){ api('session').then(async()=>{authView(true);await loadLinks();}).catch(error=>showMessage(error.message,true)); }
