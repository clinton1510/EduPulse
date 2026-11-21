<?php
require __DIR__.'/lib/analytics.php';
require __DIR__.'/lib/ui.php';
$f=filters($db,$_GET);
$modules=['overview'=>'Intelligence Overview','programs'=>'CICS Programs','subjects'=>'Subject Analytics','students'=>'Student Performance','teaching'=>'Teaching Effectiveness','trends'=>'Academic Trends','warnings'=>'Early Warning System','insights'=>'Academic Insights','reports'=>'Academic Reports','datasets'=>'Dataset Management','guide'=>'Dashboard Guide','settings'=>'Settings'];
$module=scalarParam($_GET,'module','overview');if(!isset($modules[$module])){http_response_code(404);$module='overview';}
require __DIR__.'/lib/formats.php';require __DIR__.'/lib/datasets.php';
$datasetFeedback=[];if($module==='datasets')$datasetFeedback=handleDataset($db);
$a=academicSummary($db,$f);$s=seteSummary($db,$f);$metrics=metrics($a,$s);
workspaceStart($module,$modules[$module],$f);
if(isset($datasetFeedback['error']))echo '<p class="error" role="alert">'.h($datasetFeedback['error']).'</p>';
if(isset($datasetFeedback['message']))echo '<p class="notice" role="status">'.h($datasetFeedback['message']).'</p>';
if(!in_array($module,['datasets','guide','settings'],true))filterPanel($f,$module);
if(in_array($module,['overview','programs','subjects','students','teaching','warnings','trends','insights'],true)){
 if(!$a['records'])echo '<div class="empty-state" role="status">No academic records match the selected scope. Reset the filters to explore other periods or programs.</div>';
 if($f['level']||$f['subject'])echo '<p class="notice">SETE data is available only at program and period level. Teaching KPIs are unavailable for subject or year-level filters.</p>';
 kpiCards($metrics);
}
if($module==='overview'){
 echo '<section class="overview-banner"><div><p class="banner-label">CICS ACADEMIC INTELLIGENCE</p><h2>A clearer picture.<br>A better path forward.</h2><p>Connect performance, teaching quality, and academic support.</p></div><div class="scope-summary"><strong>'.number_format((int)$a['records']).'</strong><span>academic records in this scope</span><small>'.h($f['year'].' · '.$f['sem']).'</small></div></section><div class="section-heading"><h2>Patterns that matter</h2><span class="muted">Historical charts retain program, level, and subject scope.</span></div><section class="charts-grid">';
 chartCard('trendChart','Performance across semesters','Pass rate (%) and weighted SETE average (/5).',true,'tour-trends');chartCard('deptChart','Teaching effectiveness','Weighted mean by attributed CICS program.',false,'tour-teaching');chartCard('riskChart','Academic support needs','Flagged academic records by program.');echo '</section><section class="lower-grid">';insights($metrics);echo '<article class="insight-card" id="tour-ml"><p class="eyebrow">RESEARCH EXPLORER</p><h2>Explore the model. Understand the limits.</h2><p>Try the preserved logistic regression model trained on external UCI data. It is separate from CICS rule-based flags.</p><a class="btn-secondary" href="predict.php">Open ML Risk Explorer</a></article></section>';
 echo '<section class="module-links"><a id="tour-programs" href="dashboard.php?'.h(filterQuery($f,['module'=>'programs'])).'">'.icon('book').'<strong>Explore CICS programs</strong><span>Compare performance and support needs</span></a><a id="tour-subjects" href="dashboard.php?'.h(filterQuery($f,['module'=>'subjects'])).'">'.icon('chart').'<strong>Explore subject analytics</strong><span>Inspect passing rates and grade distributions</span></a><a id="tour-reports" href="dashboard.php?'.h(filterQuery($f,['module'=>'reports'])).'">'.icon('report').'<strong>Academic reports</strong><span>CSV, Excel, PDF, and print</span></a></section>';
 dataTable(programRows($db,$f),'Program performance',$f);
}
if($module==='programs'){
 echo '<section class="program-grid">';foreach($f['programs'] as $p)echo '<a class="program-card" href="dashboard.php?'.h(filterQuery($f,['module'=>'programs','program'=>$p['code'],'subject'=>''])).'"><span class="program-code">'.h($p['code']).'</span><h2>'.h($p['title']).'</h2><p>Explore academic outcomes and support indicators</p></a>';echo '</section><p class="notice">Initial configurable categories and illustrative subjects; these are not verified official campus offerings.</p><section class="charts-grid">';chartCard('programChart','CICS program comparison','Unit-weighted course pass rates.');chartCard('trendChart','Program performance over time','Selected program scope across all academic periods.');echo '</section>';dataTable(programRows($db,$f),'Program comparison',$f);dataTable(distribution($db,$f,'levels'),'Year-level distribution',$f);dataTable(subjectRows($db,$f),'Program subject list',$f,true);insights($metrics);
}
if($module==='subjects'){
 echo '<p class="notice">SYN subject codes identify illustrative synthetic curriculum entries, not official university courses. Passing + failing + pending = enrolled.</p><section class="charts-grid">';chartCard('subjectChart','Subject passing rates','Completed passing grades / all subject enrollments.');chartCard('gradeChart','Grade distribution','Pending grades are shown separately.');echo '</section>';dataTable(subjectRows($db,$f),$f['subject']?'Subject detail · '.$f['year'].' · '.$f['sem']:'Subject performance',$f,true);dataTable(distribution($db,$f,'grades'),'Grade bands',$f);
}
if($module==='students'){
 echo '<p class="notice">Population counts refer to synthetic period-specific cohorts. No names or individual grade records are published.</p><section class="charts-grid">';chartCard('gradeChart','Subject grade distribution','Aggregate grades in the selected scope.');chartCard('riskChart','Academic support by program','Rule-based flags, separate from ML estimates.');echo '</section>';dataTable(distribution($db,$f,'levels'),'Student population by year level',$f);dataTable(programRows($db,$f),'Student outcomes by program',$f);
}
if($module==='teaching'){
 echo '<p class="notice">Weighted means use total scores / total criterion responses. Participation uses Clarity responses and invitations once per evaluation, avoiding repeated respondents across criteria. SETE should be considered alongside peer review and course context.</p><section class="charts-grid">';chartCard('deptChart','Teaching effectiveness by program','Only evaluations explicitly attributed in the synthetic dataset.');chartCard('responseChart','SETE response trends','Program-period participation percentages.');echo '</section>';dataTable(seteRows($db,$f),'Program SETE comparison',$f);dataTable(seteRows($db,$f,'criterion'),'SETE criteria breakdown',$f);dataTable(seteRows($db,$f,'v.academic_year,v.semester'),'Semester comparisons',$f);echo '<article class="insight-card"><h2>Areas to review</h2><p>Discuss lower-rated criteria with teaching staff, add peer observation, and use neutral participation reminders. These are suggestions for academic support.</p></article>';
}
if($module==='trends'){
 echo '<p class="notice">Historical comparisons show all academic periods while retaining your program, year-level, and subject selections. Academic-year comparisons aggregate both semesters.</p><section class="charts-grid">';chartCard('trendChart','Semester performance trends','Pass rate and weighted SETE average.',true);chartCard('yearChart','Academic year comparison','Unit-weighted pass rates across both semesters.');chartCard('responseChart','SETE response trend','Program-attributed response rates.');echo '</section>';dataTable(trends($db,$f),'Historical academic performance',$f);
}
if($module==='warnings'){
 echo '<section class="summary-strip"><div><strong>'.number_format((int)$a['flagged']).'</strong><span>flagged academic records</span></div><div><strong>'.h($a['risk_rate']===null?'No data':$a['risk_rate'].'%').'</strong><span>of selected academic records</span></div></section><section class="charts-grid">';chartCard('riskChart','Support needs by program','Count of flagged academic records.');chartCard('riskTrendChart','Historical risk trend','Percentage flagged across semesters.');echo '</section>';dataTable(subjectRows($db,$f),'Subject-level support indicators',$f,true);insights($metrics);echo '<article class="insight-card"><h2>Suggested academic support</h2><ul><li>Offer tutoring for subjects with persistent grade difficulties.</li><li>Invite attendance check-ins and review access barriers.</li><li>Discuss support options with academic advisers before taking action.</li></ul><p>These actions are suggestions, not guaranteed outcomes. Subject-level flags may differ from period-average flags.</p></article>';
}
if($module==='insights'){
 insights($metrics);echo '<article class="insight-card"><h2>From signals to decisions</h2><ul>';foreach($metrics as $m)if($m['low'])echo '<li>'.h($m['label']).': review the underlying program and subject comparisons before choosing support actions.</li>';echo '</ul><p>Confirm contextual explanations with academic staff. Proposed targets are case-study assumptions, not official university policies.</p></article>';dataTable(programRows($db,$f),'Evidence behind the signals',$f);
}
if($module==='reports')require __DIR__.'/views/reports.php';
if($module==='datasets')require __DIR__.'/views/datasets.php';
if($module==='guide')require __DIR__.'/views/guide.php';
if($module==='settings')require __DIR__.'/views/settings.php';
?>
<script type="application/json" id="filterOptions"><?=js(queryAll($db,'SELECT c.program_id,p.code program,c.subject_id,c.year_level,c.semester,s.code subject_code,s.title subject_title FROM curriculum_subjects c JOIN programs p ON p.id=c.program_id JOIN subjects s ON s.id=c.subject_id'))?></script>
<script type="application/json" id="chartData"><?=js(chartData($db,$f))?></script><script src="assets/dashboard.js?v=3" defer></script>
<?php workspaceEnd(); ?>
