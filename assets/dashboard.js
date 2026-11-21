/* Responsive SVG charts share a consistent viewport and readable type sizes. */
'use strict';
(() => {
 const source=document.getElementById('chartData');if(!source)return;
 const d=JSON.parse(source.textContent),colors=['#4799d2','#168b82','#d59936','#687dab','#b873a4'];
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const configs=[['trendChart','line',d.periods,[{values:d.passRates,label:'Course pass rate (%)',unit:'%',scale:1},{values:d.seteScores,label:'SETE mean (/5)',unit:' / 5',scale:20}]],['deptChart','bar',d.departments,d.departmentScores,5,' / 5'],['riskChart','bar',d.riskDepartments,d.riskValues,Math.max(1,...d.riskValues),''],['programChart','bar',d.programs,d.programPass,100,'%'],['subjectChart','bar',d.subjects,d.subjectPass,100,'%'],['gradeChart','bar',d.gradeBands,d.gradeCounts,Math.max(1,...d.gradeCounts),''],['yearChart','bar',d.yearLabels,d.yearPass,100,'%'],['responseChart','line',d.responsePeriods,[{values:d.responses,label:'SETE participation (%)',unit:'%',scale:1}]],['riskTrendChart','line',d.periods,[{values:d.riskRates,label:'Academic records flagged (%)',unit:'%',scale:1}]]];
 const sizes=new WeakMap();
 const charts=configs.map(config=>({config,area:document.getElementById(config[0])?.parentElement})).filter(c=>c.area);
 function paint({config,area}){
  const [id,type,labels,values,max,suffix]=config;
  area.classList.toggle('chart-line',type==='line');
  area.classList.toggle('chart-bars',type==='bar');
  // Stay inside fractional grid widths; reserve the real scrollbar height.
  const visibleHeight=Math.max(120,area.clientHeight-1);
  const availableWidth=Math.max(1,Math.min(area.clientWidth,Math.floor(area.getBoundingClientRect().width))-1);
  const width=Math.max(type==='line'?420:260,availableWidth);
  const height=type==='bar'?Math.max(visibleHeight,labels.length*34+42):visibleHeight;
  const signature=width+'x'+height;
  if(sizes.get(area)===signature)return;
  sizes.set(area,signature);
  let svg='';
  if(!labels.length)svg=`<text x="${width/2}" y="${visibleHeight/2}" text-anchor="middle" fill="#707889">No data for the selected scope</text>`;
  else if(type==='bar'){
   const left=105,right=65,plot=width-left-right,top=28,bottom=height-25;
   for(let i=0;i<=4;i++){const x=left+plot*i/4;svg+=`<path d="M${x} 13V${bottom}" stroke="#e9edf3"/><text x="${x}" y="${height-5}" text-anchor="middle" fill="#707889" font-size="11">${Number((max*i/4).toFixed(1))}</text>`;}
   const gap=Math.min(54,(bottom-top)/labels.length),offset=top+((bottom-top)-labels.length*gap)/2;
   labels.forEach((label,i)=>{const y=offset+i*gap,bar=Math.max(0,Math.min(1,values[i]/max))*plot,text=String(label),short=text.length>16?text.slice(0,14)+'…':text;
    svg+=`<text x="${left-12}" y="${y+17}" text-anchor="end" fill="#526070" font-size="12"><title>${esc(text)}</title>${esc(short)}</text><rect x="${left}" y="${y}" width="${plot}" height="24" rx="5" fill="#f0f4f8"/><rect tabindex="0" x="${left}" y="${y}" width="${bar}" height="24" rx="5" fill="${colors[i%colors.length]}" aria-label="${esc(text+': '+values[i]+suffix)}"><title>${esc(text)}: ${values[i]}${esc(suffix)}</title></rect><text x="${width-right+12}" y="${y+17}" fill="#202b3b" font-size="12" font-weight="600">${values[i]}${esc(suffix)}</text>`;
   });
  }else{
   const left=45,right=values.length>1?38:18,top=20,bottom=height-76,plot=width-left-right;
   const x=i=>left+i*plot/Math.max(1,labels.length-1),y=v=>bottom-v*(bottom-top)/100;
   for(let i=0;i<=4;i++){const yy=y(i*25);svg+=`<path d="M${left} ${yy}H${width-right}" stroke="#e9edf3"/><text x="${left-9}" y="${yy+4}" text-anchor="end" fill="#707889" font-size="11">${i*25}%</text>`;if(values.length>1)svg+=`<text x="${width-right+10}" y="${yy+4}" fill="#707889" font-size="11">${i*1.25}</text>`;}
   values.forEach((series,k)=>{let path='',previous=false;series.values.forEach((v,i)=>{if(v===null){previous=false;return;}path+=`${previous?'L':'M'}${x(i)} ${y(v*series.scale)} `;previous=true;});svg+=`<path d="${path}" fill="none" stroke="${colors[k]}" stroke-width="2.5" stroke-linejoin="round"/>`;series.values.forEach((v,i)=>{if(v!==null)svg+=`<circle tabindex="0" cx="${x(i)}" cy="${y(v*series.scale)}" r="4" fill="${colors[k]}" aria-label="${esc(labels[i]+': '+v+series.unit)}"><title>${esc(labels[i])}: ${v}${esc(series.unit)}</title></circle>`;});const legendX=left+k*220;svg+=`<circle cx="${legendX}" cy="${height-12}" r="4" fill="${colors[k]}"/><text x="${legendX+12}" y="${height-8}" fill="#526070" font-size="12">${esc(series.label)}</text>`;});
   labels.forEach((label,i)=>{const text=String(label),parts=text.split(' · ');svg+=`<text x="${x(i)}" y="${bottom+24}" text-anchor="middle" fill="#707889" font-size="11">${esc(parts[0])}${parts[1]?`<tspan x="${x(i)}" dy="15">${esc(parts[1])}</tspan>`:''}</text>`;});
  }
  const label=type==='line'?values.map(s=>s.label).join(' and '):labels.map((l,i)=>`${l}: ${values[i]}${suffix}`).join('; ');
  area.innerHTML=`<svg id="${id}" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" role="img" aria-label="${esc(label||'No data for selected scope')}" style="display:block;width:${width}px;height:${height}px;font:13px system-ui"><title>${esc(label||'No data for selected scope')}</title>${svg}</svg>`;
 }
 charts.forEach(paint);
 if('ResizeObserver' in window){const widths=new WeakMap();let frame;const observer=new ResizeObserver(entries=>{let changed=false;entries.forEach(entry=>{const w=entry.target.clientWidth+'x'+entry.target.clientHeight;if(widths.get(entry.target)!==w){widths.set(entry.target,w);changed=true;}});if(changed){cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>charts.forEach(paint));}});charts.forEach(({area})=>observer.observe(area));}
 window.addEventListener('resize',()=>charts.forEach(paint));
})();
