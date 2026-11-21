/* Progressive enhancements: ordinary links and forms work without JavaScript. */
'use strict';
(() => {
 const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
 let preferredReduced=false;try{preferredReduced=localStorage.getItem('edupulse-motion')==='true';}catch{}
 const status=document.createElement('div');
 status.className='flow-status'; status.setAttribute('role','status'); status.setAttribute('aria-live','polite');
 document.body.append(status);
 let toastTimer;
 function announce(message){status.textContent=message;status.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>status.classList.remove('visible'),4500);}
 const resetForms=()=>document.querySelectorAll('form[data-submitting]').forEach(form=>{
  delete form.dataset.submitting;form.removeAttribute('aria-busy');
  form.querySelectorAll('button[data-original-label]').forEach(button=>{button.textContent=button.dataset.originalLabel;button.disabled=false;delete button.dataset.originalLabel;});
 });
 window.addEventListener('pageshow',resetForms);
 document.querySelectorAll('form').forEach(form=>{
  form.addEventListener('submit',event=>{
   if(form.dataset.submitting){event.preventDefault();return;}
   form.dataset.submitting='true';form.setAttribute('aria-busy','true');
   const button=event.submitter||form.querySelector('button[type="submit"]');
   if(button){button.dataset.originalLabel=button.textContent;button.textContent=form.classList.contains('filters')?'Updating overview...':form.action.includes('logout.php')?'Leaving workspace...':form.querySelector('[name="password"]')?'Authorizing...':'Working...';button.disabled=true;}
  });
 });
 const password=document.querySelector('input[name="password"]');
 if(password){
  const wrap=document.createElement('span');wrap.className='password-field';password.parentNode.insertBefore(wrap,password);wrap.append(password);
  const toggle=document.createElement('button');toggle.type='button';toggle.className='password-toggle';toggle.textContent='Show';toggle.setAttribute('aria-label','Show password');toggle.setAttribute('aria-pressed','false');wrap.append(toggle);
  toggle.addEventListener('click',()=>{const show=password.type==='password';password.type=show?'text':'password';toggle.textContent=show?'Hide':'Show';toggle.setAttribute('aria-label',show?'Hide password':'Show password');toggle.setAttribute('aria-pressed',String(show));});
 }
 const filters=document.querySelector('.filters');
 if(filters){
  const button=filters.querySelector('button[type="submit"]');const original=new URLSearchParams(new FormData(filters)).toString();
  filters.addEventListener('change',()=>{const changed=new URLSearchParams(new FormData(filters)).toString()!==original;button.classList.toggle('filters-pending',changed);button.textContent=changed?'Apply changes':'Apply Filters';});
 }
 document.querySelectorAll('a[href^="export.php"]:not([href*="format=print"])').forEach(link=>link.addEventListener('click',()=>announce('Report download requested. Check your browser downloads.')));
 const result=document.querySelector('.prediction-result')||document.querySelector('.error[role="alert"]');
 if(result){result.focus({preventScroll:true});result.scrollIntoView({behavior:reducedMotion.matches||preferredReduced?'instant':'smooth',block:'nearest'});}
 if(!reducedMotion.matches&&!preferredReduced&&'IntersectionObserver' in window){
  const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('flow-enter');observer.unobserve(entry.target);}}),{threshold:0.08});
  document.querySelectorAll('.kpi-card,.chart-card,.insight-card').forEach((card,i)=>{card.style.setProperty('--enter-delay',`${i%5*45}ms`);observer.observe(card);});
 }
})();
