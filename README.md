# AI-Assisted Triage Priority Decision Support Tool

Suggests an Australasian Triage Scale (ATS) category (1–5) for emergency
department patients from structured triage-stage vitals, with a confidence
score. **This is a decision-support tool, not an automated triage system.**
It always presents a suggestion + confidence score and never auto-assigns a
category — the triage nurse retains full clinical decision authority at all
times.

## ⚠️ Data limitation — read before trusting any number this tool shows you

**There is no real patient data anywhere in this repository.** The model is
trained on the public **KTAS (Korean Triage and Acuity Scale) dataset**
(Park et al., *"A generalizable approach for predicting..."*, PLOS ONE 2019,
DOI [10.1371/journal.pone.0216972](https://doi.org/10.1371/journal.pone.0216972),
1267 de-identified ED presentations). KTAS is the Korean equivalent of ATS —
both are 5-level scales, 1 = most urgent — used here as a stand-in for real
ATS-labelled data, per the project's own design. `KTAS_expert` (the
expert-verified label, not the ED nurse's own on-shift `KTAS_RN` call) was
used as the training target. See `ml/prepare_dataset.py` for the exact
column mapping.

**This is a prototype/thesis-demo model, not something to rely on
clinically, for two compounding reasons:**

1. **Under-triage risk is real and measured, not hypothetical.** Of the 26
   ATS-1 ("Immediate") cases in the *entire* source dataset, 23 had no
   recorded pain score — because critically unresponsive patients can't
   self-report pain — and a first pass at the data pipeline silently
   dropped all of them before training ever started. That was caught and
   fixed (missing pain scores are now imputed with the dataset median
   instead of dropped), but 21 of the 26 ATS-1 cases *also* have a missing
   vital sign recorded as "unable to measure," which was **not** imputed
   (fabricating a blood pressure or heart rate for the sickest patients is a
   different, worse kind of guess than imputing a symptom-report field).
   **Only 5 real ATS-1 examples survive in the entire dataset.** Split
   across train/test, that's 1 example in the test set — a recall number
   computed from n=1 is noise, not a real estimate.

2. **Overall performance is mediocre even where there's enough data to
   measure it.** On the last training run:

   | ATS | Test-set n | Recall |
   |---|---|---|
   | 1 (Immediate) | 1 | 0.000 *(not a meaningful estimate — see above)* |
   | 2 (Emergency) | 43 | 0.326 |
   | 3 (Urgent) | 97 | 0.526 |
   | 4 (Semi-urgent) | 92 | 0.446 |
   | 5 (Non-urgent) | 14 | 0.143 |

   Overall accuracy 43.7% — better than the 20% random-guess baseline for
   5 classes, but not something to act on without a human checking every
   suggestion. Re-run `train_model.py` to regenerate this table against
   whatever model is currently saved.

`/predict` returns a `503` ("Model not trained yet") if no model file
exists at all, rather than ever fabricating a prediction — but a *loaded*
model returning a confident-looking number does not mean that number is
trustworthy. Any real deployment would need a properly sourced, larger,
ethically approved ATS-labelled dataset — especially one with better
high-acuity coverage than this placeholder has.

### Random Forest vs. XGBoost

Both are supported (`--algorithm rf|xgboost` on the CLI, or the dropdown on
the `/train` upload page). On the KTAS dataset, Random Forest wins on every
metric that matters here:

| Metric | Random Forest | XGBoost |
|---|---|---|
| Overall accuracy | 43.7% | 40.5% |
| ATS-2 recall (n=43) | 32.6% | 18.6% |
| ATS-1 recall (n=1) | 0.0% *(noise either way)* | 0.0% *(noise either way)* |

Random Forest is the shipped default. This isn't a general claim that RF
beats XGBoost — with only ~1200 rows and a class distribution this skewed,
boosted trees generally need more data than bagged ones to pull ahead, so
the result may well flip on a larger, more balanced dataset. XGBoost is
offered so that's easy to re-check whenever a better dataset shows up,
not because it's known to underperform in general.

## Architecture

```
[Triage nurse - browser]
        |
        v
[Laravel web app]  (laravel-app/, port 8020)
  - Blade form collects vitals + validates physiological ranges
  - POSTs validated data to the Python API
        |
        v
[Python model API]  (ml/api.py, Flask, port 5055)
  - Loads triage_model.pkl (Random Forest)
  - POST /predict -> {ats_category, confidence}
        |
        v
[Laravel app]
  - Displays the suggestion + confidence to the nurse
  - Saves the record to MySQL (predictions table)
```

## Setup

### 1. Model — already trained, or retrain it yourself

`ml/triage_model.pkl` is already trained on the KTAS dataset (see the
limitation section above). Two ways to retrain:

**Via the browser** — go to `/train` (linked at the bottom of every page as
"Model Training (admin)"), log in with the page password (`TRAIN_PAGE_PASSWORD`
in `laravel-app/.env`, default `triage2026`), and upload a CSV. This
**replaces the live prediction model immediately** — the running Python API
hot-reloads the new model in-process, no restart needed — and shows the same
classification report/recall breakdown the CLI prints, right in the browser.
This page and the Python API's own `/train` endpoint are both gated (a page
password for the browser, plus a separate shared secret between Laravel and
the API itself — see `TRAIN_SHARED_SECRET` in both `laravel-app/.env` and
`ml/api.py`) since it can silently replace the model anyone's `/predict`
calls are using.

**Via the CLI**:

```bash
cd ml
venv\Scripts\pip install -r requirements.txt   # already done if venv/ exists

# Using the same KTAS source data (regenerates ml/data/ktas_triage_data.csv):
venv\Scripts\python.exe prepare_dataset.py

# Train (works on any dataset shaped like the one prepare_dataset.py produces):
venv\Scripts\python.exe train_model.py --data data\ktas_triage_data.csv
```

Either path just needs a CSV with columns `age, sbp, dbp, hr, rr, temp,
pain_score, ats_category` (`ats_category` is 1-5, extra columns are
ignored) — both the CLI script and the browser upload call the exact same
`train_model.load_dataset()`/`train()` functions, so they can't drift out of
sync. Recall on ATS 1-2 is always broken out separately from overall
accuracy — under-triage of high-acuity patients is the failure mode that
matters most here.

### 2. Run everything

Double-click `start.bat`, or manually:

```bash
cd ml && venv\Scripts\python.exe api.py          # http://127.0.0.1:5055
cd laravel-app && php artisan serve --port=8020  # http://127.0.0.1:8020/triage
```

Database: MySQL, `triage_db`, same XAMPP instance as the other local
projects (`localhost:3307`).

## Folder structure

```
triage-decision-support/
├── ml/
│   ├── prepare_dataset.py    # converts the source KTAS xlsx into training CSV shape
│   ├── train_model.py        # trains the RF classifier, saves triage_model.pkl
│   ├── api.py                 # Flask API, POST /predict
│   ├── triage_model.pkl       # trained model (KTAS dataset — see limitations above)
│   ├── data/                  # ktas_triage_data.csv lives here
│   ├── requirements.txt
│   └── venv/
└── laravel-app/
    ├── app/Http/Controllers/TriageController.php
    ├── app/Http/Controllers/TrainingController.php   # /train upload+retrain page
    ├── app/Http/Middleware/EnsureTrainingAuth.php     # password-gates /train
    ├── app/Models/Prediction.php
    ├── resources/views/triage/{form,result}.blade.php
    ├── resources/views/train/{login,index}.blade.php
    ├── routes/web.php
    └── database/migrations/  # predictions table
```
