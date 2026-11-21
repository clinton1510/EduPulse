# EduPulse predictive analytics (Part IV)

The dashboard's five descriptive KPIs remain separate from the optional machine-learning explorer.

## Research dataset
UCI Machine Learning Repository, **Predict Students' Dropout and Academic Success**, dataset ID 697. Source: https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success . License: **CC BY 4.0**. Approximately 4,424 records from higher education in Portugal.

## Analytical question
Can **first-semester academic performance and admission grade** help identify students who may benefit from academic advising? The target is binary: `Dropout` versus `Enrolled` / `Graduate`. It is intended to prioritize supportive discussion, never punitive or automatic academic decisions.

## Training
Run on an internet-connected machine:

```bash
python -m pip install -r ml/requirements.txt
python ml/train.py
```

Alternatively run the **Train student outcome model** GitHub Actions workflow. Both paths fetch the original UCI ZIP, train a logistic regression model, and save `model/dropout_logistic.json` and `model/metrics.json`.

## Model design
- Inputs: admission grade, first-semester curricular units enrolled, approved, and average grade.
- Model: standardized logistic regression with balanced class weights.
- Split: 80% training, 20% holdout; stratified, random seed 42.
- Evaluation: ROC-AUC, accuracy, dropout recall, dropout precision, and confusion matrix.
- Seven modeling steps: (1) define problem, (2) understand data, (3) prepare data, (4) select features/model, (5) train on training subset, (6) assess on untouched test subset, (7) review limitations, report results and plan monitoring.

## Use from PHP
Once the trained files exist, visit `predict.php`. PHP reads the exported standardized coefficients and performs logistic-regression inference **without installing Python on the web server**. The model is separate from the simulated PHP/MySQL dashboard records; they cannot be combined as if they were from the same institution.

## Responsible use
The UCI records come from Portugal, not the Philippines; no transportability or fairness validation has been done for a local university. First-semester features cannot be used to claim predictions at admission time. Scores may vary across institutions and cohorts, so they are only a teaching demonstration. No SETE scores, identity attributes, or sensitive socioeconomic features are included in this model. The system does not automatically act on predictions.
