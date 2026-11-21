<?php
require __DIR__.'/config/app.php';
$user=auth();
$path=__DIR__.'/model/dropout_logistic.json';
$metricsPath=__DIR__.'/model/metrics.json';
$model=is_file($path)?json_decode(file_get_contents($path),true):null;
$metrics=is_file($metricsPath)?json_decode(file_get_contents($metricsPath),true):null;
$values=['Admission grade'=>140, 'Curricular units 1st sem (enrolled)'=>6, 'Curricular units 1st sem (approved)'=>4, 'Curricular units 1st sem (grade)'=>12];
$bounds=['Admission grade'=>[0,200], 'Curricular units 1st sem (enrolled)'=>[0,30], 'Curricular units 1st sem (approved)'=>[0,30], 'Curricular units 1st sem (grade)'=>[0,20]];
$prediction=null;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 if(!$model){$error='Model is not yet trained. See the README for training instructions.';}
 else {
  foreach($values as $name=>$unused){
   $raw=$_POST['feature'][$name]??null;
   if(!is_scalar($raw)||!is_numeric($raw)){$error='Enter valid numeric inputs.';break;}
   $number=(float)$raw;
   if(!is_finite($number)||$number<$bounds[$name][0]||$number>$bounds[$name][1]){$error='Input outside the allowed range.';break;}
   $values[$name]=$number;
  }
  if(!$error && $values['Curricular units 1st sem (approved)']>$values['Curricular units 1st sem (enrolled)']) $error='Approved units cannot exceed enrolled units.';
  if(!$error){
   $z=(float)$model['intercept'];
   foreach($model['features'] as $i=>$name) $z+=((float)$values[$name]-(float)$model['means'][$i])/(float)$model['scales'][$i]*(float)$model['coefficients'][$i];
   $prediction=1/(1+exp(-max(-35,min(35,$z))));
  }
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EduPulse · AI Risk Explorer</title><link rel="stylesheet" href="assets/style.css"><script src="assets/interactions.js" defer></script></head>
<body class="login-page"><main class="login-card" style="width:min(650px,94vw)"><div class="brand brand-login"><span class="brand-mark">◈</span><span>Edu<span class="brand-accent">Pulse</span></span></div><p><a href="index.php" class="export-link">← Dashboard</a></p><div class="eyebrow">RESEARCH MODEL · EDUCATIONAL USE</div><h1>Student Outcome Risk Explorer</h1><p class="muted">Try a trained logistic regression model built from a public higher-education dataset. Input values represent an illustrative student profile, not a real person.</p>
<?php if(!$model):?><div class="alert">No trained model has been generated yet. Run <code>python ml/train.py</code> on a machine with internet access and commit the files in <code>model/</code>, or run the GitHub training workflow.</div><?php else:?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<?php foreach($values as $name=>$value):?><label><?=h($name)?> (<?=h($bounds[$name][0])?>–<?=h($bounds[$name][1])?>)<input type="number" step="any" name="feature[<?=h($name)?>]" value="<?=h($value)?>" min="<?=h($bounds[$name][0])?>" max="<?=h($bounds[$name][1])?>" required></label><?php endforeach;?>
<button type="submit" class="btn-primary">Calculate dropout risk estimate</button></form>
<?php if($error):?><p class="error" role="alert" tabindex="-1"><?=h($error)?></p><?php endif;?>
<?php if($prediction!==null):?><div class="filter-panel prediction-result" role="status" tabindex="-1" style="margin-top:20px;display:block"><div class="section-label">MODEL OUTPUT</div><div class="kpi-value"><?=h(number_format($prediction*100,1))?>%</div><strong><?=h($prediction>=.5?'Higher model-estimated dropout likelihood':'Lower model-estimated dropout likelihood')?></strong><p class="muted">Classification cutoff: 50%. This score is not a clinical or institution-calibrated probability and does not establish an individual's future.</p></div><?php endif;?>
<?php if($metrics):?><p class="hint">Holdout ROC-AUC: <?=h($metrics['roc_auc'])?> · Recall (dropout): <?=h($metrics['dropout_recall'])?> · Test records: <?=h($metrics['test_count'])?></p><?php endif;?>
<?php endif;?>
<p class="hint">Source: <a href="https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success">UCI dataset 697</a> (CC BY 4.0). This external dataset represents students in Portugal and is not validated for Philippine universities. Use for an educational example only. No predictions are saved. Always involve academic staff and consider student context.</p>
</main></body></html>
