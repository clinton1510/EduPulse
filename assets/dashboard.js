/* Local SVG charts: no external scripts or network connection required. */
'use strict';
const d = JSON.parse(document.getElementById('chartData').textContent);
const colors=['#5965e9','#16a89a','#eba64e'];
const escapeText=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function chart(id,content,label){const canvas=document.getElementById(id);canvas.outerHTML=`<svg viewBox="0 0 720 265" role="img" aria-label="${escapeText(label)}" style="width:100%;height:100%;font:12px system-ui;overflow:visible"><title>${escapeText(label)}</title>${content}</svg>`;}
function empty(id){chart(id,'<text x="360" y="130" text-anchor="middle" fill="#758398">No data for the selected scope</text>','No data');}
if(!d.periods.length) empty('trendChart');
else {
 let svg='';
 for(let i=0;i<=4;i++){let y=210-i*45;svg+=`<path d="M48 ${y}H665" stroke="#e6edf4"/><text x="36" y="${y+4}" text-anchor="end" fill="#758398">${i*25}%</text><text x="680" y="${y+4}" fill="#758398">${i*1.25}</text>`;}
 const x=i=>48+i*617/Math.max(1,d.periods.length-1);
 [d.passRates,d.seteScores.map(v=>v===null?null:v*20)].forEach((values,k)=>{let path='';let previous=false;values.forEach((v,i)=>{if(v===null){previous=false;return;}path+=`${previous?'L':'M'}${x(i)} ${210-v*1.8} `;previous=true;});svg+=`<path d="${path}" fill="none" stroke="${colors[k]}" stroke-width="3" stroke-linejoin="round"/>`;values.forEach((v,i)=>{if(v!==null)svg+=`<circle cx="${x(i)}" cy="${210-v*1.8}" r="5" fill="${colors[k]}"><title>${escapeText(d.periods[i])}: ${k?(v/20).toFixed(2)+' / 5':v+'%'}</title></circle>`;});});
 d.periods.forEach((p,i)=>{svg+=`<text x="${x(i)}" y="233" text-anchor="middle" fill="#758398" font-size="10">${escapeText(p)}</text>`;});
 svg+='<circle cx="235" cy="257" r="4" fill="#5965e9"/><text x="247" y="261" fill="#52657c">Pass rate (%)</text><circle cx="400" cy="257" r="4" fill="#16a89a"/><text x="412" y="261" fill="#52657c">SETE average (/5)</text>';
 chart('trendChart',svg,'Semester trends: pass rate and teaching effectiveness');
}
function bars(id,labels,values,max,suffix){if(!labels.length){empty(id);return;}let svg='';const height=Math.min(48,185/labels.length);labels.forEach((label,i)=>{let y=24+i*75;svg+=`<text x="0" y="${y}" fill="#52657c">${escapeText(label)}</text><rect x="0" y="${y+12}" width="640" height="${height}" rx="8" fill="#f0f3fa"/><rect x="0" y="${y+12}" width="${Math.max(0,values[i]/max*640)}" height="${height}" rx="8" fill="${colors[i%3]}"><title>${escapeText(label)}: ${values[i]}${suffix}</title></rect><text x="655" y="${y+height/2+17}" fill="#14253c" font-weight="700">${values[i]}${suffix}</text>`;});chart(id,svg,labels.map((l,i)=>`${l}: ${values[i]}${suffix}`).join('; '));}
bars('deptChart',d.departments,d.departmentScores,5,' / 5');
bars('riskChart',d.riskDepartments,d.riskValues,Math.max(1,...d.riskValues),'');
