<?php
require __DIR__.'/config/app.php';
require __DIR__.'/lib/analytics.php';
require __DIR__.'/lib/ui.php';
$f=filters($db,$_GET);
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
   $features=is_array($_POST['feature']??null)?$_POST['feature']:[];
   $raw=$features[$name]??null;
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
workspaceStart('predict','ML Risk Explorer',$f);
?>
<p class="notice">External research model ? Educational use. This estimate is separate from the rule-based academic flags on the CICS dashboard and never runs automatically on local records.</p>
<div class="model-grid"><article class="insight-card"><p class="eyebrow">PRESERVED LOGISTIC REGRESSION MODEL</p><h2>Explore a student outcome estimate</h2><p>The trained model uses first-semester academic performance and admission grade from a public higher-education dataset in Portugal. Enter an illustrative profile using the original dataset scales.</p>
<?php if(!$model):?><div class="alert">The trained model is unavailable. Follow the documented training workflow before using this explorer.</div><?php else:?>
<form class="model-form" method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<?php foreach($values as $name=>$value):?><label><?=h($name)?> <span class="muted">(<?=h($bounds[$name][0])?>?<?=h($bounds[$name][1])?>)</span><input type="number" step="any" name="feature[<?=h($name)?>]" value="<?=h($value)?>" min="<?=h($bounds[$name][0])?>" max="<?=h($bounds[$name][1])?>" required></label><?php endforeach;?>
<button type="submit" class="btn-primary">Calculate risk estimate</button></form>
<?php endif;?>
<?php if($error):?><p class="error" role="alert" tabindex="-1"><?=h($error)?></p><?php endif;?>
</article><div><article class="insight-card model-result <?= $prediction!==null?'prediction-result':'' ?>" <?= $prediction!==null?'role="status" tabindex="-1"':'' ?>><p class="eyebrow">MODEL OUTPUT</p><h2><?= $prediction!==null?'Calculated risk estimate':'Your estimate appears here' ?></h2>
<?php if($prediction!==null):?><div class="kpi-value"><?=h(number_format($prediction*100,1))?>%</div><div class="risk-gauge" role="img" aria-label="Estimated dropout likelihood <?=h(number_format($prediction*100,1))?> percent"><span class="risk-marker" style="--risk:<?=h(number_format($prediction*100,2,'.',''))?>%"></span></div><strong><?=h($prediction>=.5?'Higher model-estimated dropout likelihood':'Lower model-estimated dropout likelihood')?></strong><p>Classification cutoff: 50%. This score is not an institution-calibrated probability and does not establish an individual's future.</p><?php else:?><p>Enter a profile and calculate an estimate. No predictions are saved.</p><?php endif;?></article>
<?php if($metrics):?><article class="insight-card"><h2>Actual holdout evaluation</h2><div class="model-metrics"><?php foreach(['roc_auc'=>'ROC-AUC','accuracy'=>'Accuracy','dropout_recall'=>'Dropout recall','dropout_precision'=>'Dropout precision'] as $key=>$label):?><div><strong><?=h($metrics[$key])?></strong><span><?=h($label)?></span></div><?php endforeach;?></div><p class="hint"><?=h($metrics['test_count'])?> test records ? <?=h($metrics['split'])?></p></article><?php endif;?></div></div>
<article class="insight-card"><h2>Dataset source and limitations</h2><p>Source: <a href="https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success">UCI Predict Students' Dropout and Academic Success, dataset 697</a> ? CC BY 4.0. The dataset contains 4,424 records from Portugal. The model has not been trained on CICS students and is not validated for Batangas State University or Philippine universities.</p><p>First-semester features cannot establish risk at admission. The model does not make decisions or save predictions. Academic staff should consider student context, other evidence, and appropriate validation before operational use.</p></article>
<?php workspaceEnd(); ?>
