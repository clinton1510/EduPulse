<?php
function reportTypes(): array {return ['academic'=>'CICS Academic Performance Report','program'=>'Individual Program Performance Report','subject'=>'Subject Performance Report','warning'=>'Early Warning Summary','teaching'=>'Teaching Effectiveness Report','participation'=>'SETE Participation Report'];}
function reportRows(PDO $db,array $f,string $type): array {
 $titles=reportTypes();$a=academicSummary($db,$f);$s=seteSummary($db,$f);
 $subjectLabel='All subjects';foreach($f['subjects'] as $subject)if((string)$subject['id']===$f['subject'])$subjectLabel=$subject['code'].' '.$subject['title'];
 $rows=[['EduPulse — '.$titles[$type]],['Program',$f['program']?:'All CICS programs'],['Academic year',$f['year']],['Semester',$f['sem']],['Year level',$f['level']?:'All'],['Subject',$subjectLabel],['Generated',date('Y-m-d H:i:s').' Asia/Manila'],['Data classification','Synthetic academic data; illustrative curriculum; not official institutional records.'],['Academic records',$a['records']],['KPI','Value','Proposed target']];
 foreach(metrics($a,$s) as $m)$rows[]=[$m['label'],$m['value'],$m['target']];
 $detail=match($type){'subject'=>subjectRows($db,$f),'teaching'=>seteRows($db,$f,'criterion'),'participation'=>seteRows($db,$f,'v.academic_year,v.semester'),default=>programRows($db,$f)};
 $rows[]=[];if($detail){$keys=array_values(array_filter(array_keys($detail[0]),fn($k)=>$k!=='id'));$rows[]=$keys;foreach($detail as $row)$rows[]=array_map(fn($k)=>$row[$k]??'No data',$keys);}else $rows[]=['No records for this scope.'];
 $rows[]=['Note','Historical comparisons retain program, level and subject filters and compare all periods.'];$rows[]=['Note','SETE is not attributable to a subject or year level. It is never the sole personnel measure.'];$rows[]=['Rule','At risk: average grade > 3 OR attendance < 75%; proposed case-study thresholds.'];return $rows;
}
