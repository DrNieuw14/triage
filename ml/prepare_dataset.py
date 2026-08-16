"""
Converts the public KTAS (Korean Triage and Acuity Scale) dataset —
Park et al., "A generalizable prediction model..." PLOS ONE 2019,
supplementary file pone.0216972.s001.xlsx — into the CSV shape
train_model.py expects.

KTAS is the Korean equivalent of the Australasian Triage Scale: both are
5-level scales (1 = most urgent, 5 = least), so KTAS_expert stands in for
the "expert-verified triage category" the spec calls for as a public,
de-identified research dataset. It is NOT literally ATS-labelled data —
document this substitution wherever the model's provenance is discussed.

Column mapping (source -> our schema):
    Age        -> age
    SBP        -> sbp
    DBP        -> dbp
    HR         -> hr
    RR         -> rr
    BT         -> temp
    NRS_pain   -> pain_score
    KTAS_expert -> ats_category   (expert-verified, not the ED nurse's own KTAS_RN call)

A handful of vitals are recorded as the Korean sentinel "측불" (literally
"unable to measure") instead of a number — those rows are dropped, same as
train_model.py's own missing-data policy.

NRS_pain (pain score) is missing for ~44% of rows overall, but that
missingness is NOT random: cross-tabulating against Mental status shows it's
concentrated in unconscious/unresponsive patients, who by definition can't
self-report a pain score. Naively dropping every row with a missing pain
score would delete 23 of the 26 KTAS-1 (most urgent) cases in the *entire*
dataset — precisely the high-acuity class this tool most needs to recall
correctly. So missing pain scores are imputed with the dataset median
instead of dropped. This is a real, documented limitation, not a fix: it
means "pain score" for the sickest patients is a guess, not a real
observation. Missing vitals are still dropped outright rather than
imputed — fabricating a blood pressure or heart rate for a critical patient
is a materially different (and worse) kind of guess than imputing a
symptom-report field, so that's not done here even though it would recover
more KTAS-1 rows.
"""

import pandas as pd

SOURCE_XLSX = r"D:\My Documents\1.1 CvSU\Bes May\data set\pone.0216972.s001.xlsx"
SOURCE_SHEET = "N=1267 data"
OUT_CSV = "data/ktas_triage_data.csv"

COLUMN_MAP = {
    "Age": "age",
    "SBP": "sbp",
    "DBP": "dbp",
    "HR": "hr",
    "RR": "rr",
    "BT": "temp",
    "NRS_pain": "pain_score",
    "KTAS_expert": "ats_category",
}

if __name__ == "__main__":
    df = pd.read_excel(SOURCE_XLSX, sheet_name=SOURCE_SHEET)
    print(f"Loaded {len(df)} rows from source.")

    df = df[list(COLUMN_MAP.keys())].rename(columns=COLUMN_MAP)

    for col in ["age", "sbp", "dbp", "hr", "rr", "temp", "pain_score"]:
        df[col] = pd.to_numeric(df[col], errors="coerce")

    vitals = ["age", "sbp", "dbp", "hr", "rr", "temp"]

    missing_pain = df["pain_score"].isna().sum()
    pain_median = df["pain_score"].median()
    df["pain_score"] = df["pain_score"].fillna(pain_median)
    print(f"Imputed {missing_pain} missing pain_score value(s) with the dataset median ({pain_median}) "
          f"rather than dropping — see module docstring for why.")

    before = len(df)
    df = df.dropna(subset=vitals)
    print(f"Dropped {before - len(df)} row(s) with an unmeasured vital.")
    print(f"{len(df)} rows remain.")

    df["ats_category"] = df["ats_category"].astype(int)
    df["age"] = df["age"].round(1)

    print("\nats_category distribution:")
    print(df["ats_category"].value_counts().sort_index())

    df.to_csv(OUT_CSV, index=False)
    print(f"\nWrote {OUT_CSV}")
