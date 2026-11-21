#!/usr/bin/env python3
"""Train a transparent dropout-risk classroom model on UCI dataset #697.

Outcome is Dropout vs. Enrolled/Graduate, using only first-semester data and
admission grade, to prevent leakage from later academic outcomes.
"""
from pathlib import Path
import io, zipfile, urllib.request, json
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import roc_auc_score, accuracy_score, recall_score, precision_score, confusion_matrix

ROOT=Path(__file__).resolve().parents[1]
OUTPUT=ROOT/"model"
OUTPUT.mkdir(exist_ok=True)
URL="https://archive.ics.uci.edu/static/public/697/predict+students+dropout+and+academic+success.zip"
FEATURES=[
    "Admission grade",
    "Curricular units 1st sem (enrolled)",
    "Curricular units 1st sem (approved)",
    "Curricular units 1st sem (grade)",
]
with urllib.request.urlopen(URL,timeout=90) as response:
    archive=zipfile.ZipFile(io.BytesIO(response.read()))
names=[n for n in archive.namelist() if n.lower().endswith("data.csv")]
if not names:
    raise RuntimeError("UCI dataset archive does not contain data.csv")
data=pd.read_csv(io.BytesIO(archive.read(names[0])),sep=";")
data.columns=[c.strip() for c in data.columns]
missing=set(FEATURES+["Target"])-set(data.columns)
if missing:
    raise RuntimeError(f"Missing expected columns: {missing}")
data=data.dropna(subset=FEATURES+["Target"])
X=data[FEATURES].astype(float)
y=(data["Target"]=="Dropout").astype(int)
xtrain,xtest,ytrain,ytest=train_test_split(X,y,test_size=.2,random_state=42,stratify=y)
scaler=StandardScaler()
xs=scaler.fit_transform(xtrain)
model=LogisticRegression(max_iter=2000,class_weight="balanced",random_state=42)
model.fit(xs,ytrain)
prob=model.predict_proba(scaler.transform(xtest))[:,1]
pred=(prob>=.5).astype(int)
cm=confusion_matrix(ytest,pred,labels=[0,1])
metrics={
 "source":URL,"source_name":"UCI Predict Students' Dropout and Academic Success (ID 697)",
 "license":"CC BY 4.0","sample_count":int(len(data)),
 "training_count":int(len(xtrain)),"test_count":int(len(xtest)),
 "dropout_prevalence":round(float(y.mean()),4),
 "target":"Dropout vs Enrolled/Graduate",
 "split":"Stratified 80/20, random_state=42","threshold":.5,
 "roc_auc":round(float(roc_auc_score(ytest,prob)),4),
 "accuracy":round(float(accuracy_score(ytest,pred)),4),
 "dropout_recall":round(float(recall_score(ytest,pred,zero_division=0)),4),
 "dropout_precision":round(float(precision_score(ytest,pred,zero_division=0)),4),
 "confusion_matrix_labels":["Not dropout","Dropout"],
 "confusion_matrix":cm.tolist(),
 "limitations":"External Portugal higher-education dataset; not validated for a Philippine university. Predictions are illustrative and must not automatically determine interventions."
}
artifact={
 "model_type":"standardized_logistic_regression",
 "features":FEATURES,
 "means":[float(x) for x in scaler.mean_],
 "scales":[float(x) for x in scaler.scale_],
 "coefficients":[float(x) for x in model.coef_[0]],
 "intercept":float(model.intercept_[0]),
 "threshold":.5,
 "provenance":"UCI dataset 697; training script ml/train.py; see model/metrics.json"
}
(OUTPUT/"dropout_logistic.json").write_text(json.dumps(artifact,indent=2)+"\n")
(OUTPUT/"metrics.json").write_text(json.dumps(metrics,indent=2)+"\n")
print(json.dumps(metrics,indent=2))
