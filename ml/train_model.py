"""
Trains a classifier that suggests an Australasian Triage Scale (ATS)
category (1-5) from structured triage-stage vitals. Supports two
algorithms — Random Forest (default) and XGBoost — selected via
`--algorithm` on the CLI or the `algorithm` field on the web upload form.

CLI usage:
    venv/Scripts/python.exe train_model.py --data data/triage_data.csv --algorithm rf
    venv/Scripts/python.exe train_model.py --data data/triage_data.csv --algorithm xgboost

Also importable — api.py's /train endpoint calls load_dataset()/train()
directly so the web-upload retrain path and this CLI script share the exact
same logic and can never drift apart.

Expected CSV columns (extra columns are ignored):
    age, sbp, dbp, hr, rr, temp, pain_score, ats_category

`ats_category` is the label (1-5, 1 = most urgent). The other seven columns
are the exact feature set the /predict API accepts — this mirrors the model
input contract in the project spec, so training and serving never drift out
of sync with each other.

Recall on the high-acuity classes (1-2) is printed separately from overall
accuracy: in triage, under-triaging a category-1/2 patient is the dangerous
failure mode, so that number matters more than aggregate accuracy.
"""

import argparse
import sys

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.utils.class_weight import compute_sample_weight
from xgboost import XGBClassifier

FEATURES = ["age", "sbp", "dbp", "hr", "rr", "temp", "pain_score"]
TARGET = "ats_category"
HIGH_ACUITY_CLASSES = [1, 2]
ALGORITHMS = ["rf", "xgboost"]


class DatasetError(Exception):
    """Raised when an uploaded/given CSV doesn't match the expected shape."""


class LabelShiftedXGBClassifier:
    """Wraps XGBClassifier so it exposes the same label contract as
    sklearn's RandomForestClassifier: `classes_` and predict()/predict_proba()
    all speak in the real ATS labels (1-5). XGBoost's multiclass objective
    internally requires 0-indexed contiguous class labels, but nothing else
    in this codebase should have to know that — api.py's /predict endpoint
    in particular reads `model.classes_` directly and expects real ATS
    numbers back, and it's unchanged by adding this second algorithm."""

    def __init__(self, **kwargs):
        self._model = XGBClassifier(**kwargs)
        self.classes_ = []
        self._label_map = {}
        self._inverse_map = {}

    def fit(self, X, y, sample_weight=None):
        self.classes_ = sorted(y.unique())
        self._label_map = {c: i for i, c in enumerate(self.classes_)}
        self._inverse_map = {i: c for c, i in self._label_map.items()}
        self._model.fit(X, y.map(self._label_map), sample_weight=sample_weight)
        return self

    def predict(self, X):
        return [self._inverse_map[p] for p in self._model.predict(X)]

    def predict_proba(self, X):
        # Column order matches self.classes_ because _label_map preserves
        # sorted order (class 1 -> column 0, 2 -> column 1, etc.).
        return self._model.predict_proba(X)


def load_dataset(source) -> pd.DataFrame:
    """`source` is anything pandas.read_csv accepts: a path, or a file-like
    object (used by api.py to read an uploaded file without saving it to
    disk first)."""
    try:
        df = pd.read_csv(source)
    except Exception as e:
        raise DatasetError(f"Could not read as CSV: {e}")

    missing = [col for col in FEATURES + [TARGET] if col not in df.columns]
    if missing:
        raise DatasetError(
            f"Dataset is missing required column(s): {missing}. "
            f"Expected at least: {FEATURES + [TARGET]}"
        )

    before = len(df)
    df = df.dropna(subset=FEATURES + [TARGET])
    dropped_missing = before - len(df)

    df[TARGET] = pd.to_numeric(df[TARGET], errors="coerce")
    df = df.dropna(subset=[TARGET])
    df[TARGET] = df[TARGET].astype(int)
    if not df[TARGET].between(1, 5).all():
        raise DatasetError(f"'{TARGET}' must be an integer 1-5 in every row.")

    if len(df) < 25:
        raise DatasetError(
            f"Only {len(df)} usable row(s) after removing missing/invalid values — "
            "too few to train and evaluate a 5-class model."
        )

    df.attrs["dropped_missing"] = dropped_missing
    return df


def build_model(algorithm: str, random_state: int):
    if algorithm == "xgboost":
        return LabelShiftedXGBClassifier(
            n_estimators=300,
            random_state=random_state,
            eval_metric="mlogloss",
        )
    return RandomForestClassifier(
        n_estimators=300,
        class_weight="balanced",  # counteract the usual skew toward lower-acuity cases
        random_state=random_state,
    )


def train(df: pd.DataFrame, out_path: str, random_state: int = 42, verbose: bool = True,
          algorithm: str = "rf") -> dict:
    """Trains, evaluates, saves the model, and returns a JSON-serialisable
    metrics dict. `verbose` controls whether it also prints (used by the CLI;
    api.py sets this False and just uses the returned dict)."""
    if algorithm not in ALGORITHMS:
        raise DatasetError(f"Unknown algorithm '{algorithm}' — expected one of {ALGORITHMS}.")

    X = df[FEATURES]
    y = df[TARGET]

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=random_state, stratify=y
    )

    model = build_model(algorithm, random_state)

    if algorithm == "xgboost":
        # XGBoost has no built-in class_weight="balanced" for multiclass —
        # compute_sample_weight is the equivalent, mirroring what RF does
        # internally, so the two algorithms get a fair comparison.
        sample_weight = compute_sample_weight("balanced", y_train)
        model.fit(X_train, y_train, sample_weight=sample_weight)
    else:
        model.fit(X_train, y_train)

    y_pred = model.predict(X_test)

    report = classification_report(y_test, y_pred, output_dict=True, zero_division=0)
    cm = confusion_matrix(y_test, y_pred, labels=[1, 2, 3, 4, 5]).tolist()

    high_acuity_recall = {}
    for cls in HIGH_ACUITY_CLASSES:
        key = str(cls)
        if key in report:
            high_acuity_recall[cls] = {
                "recall": round(report[key]["recall"], 3),
                "support": int(report[key]["support"]),
            }

    joblib.dump({"model": model, "features": FEATURES, "algorithm": algorithm}, out_path)

    result = {
        "algorithm": algorithm,
        "rows_used": len(df),
        "dropped_missing": int(df.attrs.get("dropped_missing", 0)),
        "class_distribution": df[TARGET].value_counts().sort_index().to_dict(),
        "accuracy": round(report["accuracy"], 3),
        "per_class": {
            cls: {
                "precision": round(report[cls]["precision"], 3),
                "recall": round(report[cls]["recall"], 3),
                "f1": round(report[cls]["f1-score"], 3),
                "support": int(report[cls]["support"]),
            }
            for cls in ["1", "2", "3", "4", "5"] if cls in report
        },
        "confusion_matrix": cm,
        "high_acuity_recall": high_acuity_recall,
        "model_path": out_path,
    }

    if verbose:
        print(f"\n=== Classification report ({algorithm}, test set) ===")
        print(classification_report(y_test, y_pred, digits=3, zero_division=0))
        print("=== Confusion matrix (rows = actual, cols = predicted, labels 1-5) ===")
        print(confusion_matrix(y_test, y_pred, labels=[1, 2, 3, 4, 5]))
        print("=== High-acuity recall (the number that matters most) ===")
        for cls in HIGH_ACUITY_CLASSES:
            if cls in high_acuity_recall:
                r = high_acuity_recall[cls]
                print(f"  ATS {cls}: recall = {r['recall']:.3f}  (n={r['support']})")
            else:
                print(f"  ATS {cls}: not present in test split")
        print(f"\nSaved model to {out_path}")

    return result


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--data", default="data/triage_data.csv", help="Path to training CSV")
    parser.add_argument("--out", default="triage_model.pkl", help="Path to save the trained model")
    parser.add_argument("--algorithm", choices=ALGORITHMS, default="rf", help="Which classifier to train")
    args = parser.parse_args()

    try:
        dataset = load_dataset(args.data)
    except DatasetError as e:
        sys.exit(str(e))

    print(f"Loaded {len(dataset)} rows from {args.data}")
    if dataset.attrs.get("dropped_missing"):
        print(f"Dropped {dataset.attrs['dropped_missing']} row(s) with missing required values.")
    print("Class distribution:")
    print(dataset[TARGET].value_counts().sort_index())

    train(dataset, args.out, algorithm=args.algorithm)
