const d = JSON.parse(document.getElementById('chartData').textContent);
Chart.defaults.font.family = "Inter, system-ui, sans-serif";
Chart.defaults.color = '#718198';
const grid = {color:'#eaf0f5'};
const common = {responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{usePointStyle:true,boxWidth:7}}}};
new Chart(document.getElementById('trendChart'),{
 type:'line',data:{labels:d.periods,datasets:[
 {label:'Course pass rate (%)',data:d.passRates,borderColor:'#266edb',backgroundColor:'rgba(38,110,219,.09)',fill:true,tension:.4,yAxisID:'y',pointRadius:4},
 {label:'SETE average (/5)',data:d.seteScores,borderColor:'#18aa9a',backgroundColor:'#18aa9a',tension:.4,yAxisID:'y1',pointRadius:4}]},
 options:{...common,interaction:{mode:'index',intersect:false},scales:{x:{grid:{display:false}},y:{min:0,max:100,grid,title:{display:true,text:'Pass rate (%)'}},y1:{position:'right',min:0,max:5,grid:{drawOnChartArea:false},title:{display:true,text:'SETE (/5)'}}}}
});
new Chart(document.getElementById('deptChart'),{
 type:'bar',data:{labels:d.departments,datasets:[{label:'SETE score',data:d.departmentScores,backgroundColor:['#286fe0','#53b7ab','#89a6e7'],borderRadius:8,maxBarThickness:54}]},
 options:{...common,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{min:0,max:5,grid},y:{grid:{display:false}}}}
});
new Chart(document.getElementById('riskChart'),{
 type:'doughnut',data:{labels:d.riskDepartments,datasets:[{data:d.riskValues,backgroundColor:['#266edb','#18aa9a','#f0af57'],borderWidth:0,hoverOffset:6}]},
 options:{...common,cutout:'68%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:15}}}}
});
