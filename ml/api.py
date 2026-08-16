"""
Minimal Flask API wrapping the trained triage model.

POST /predict
    body: {"age": .., "sbp": .., "dbp": .., "hr": .., "rr": .., "temp": .., "pain_score": ..}
    -> {"ats_category": 1-5, "confidence": 0.0-1.0}

This endpoint only ever returns a suggestion + confidence score. It never
decides anything on its own — the calling app (and ultimately the triage
nurse) is responsible for treating this as decision support, not an
auto-assigned category.

Laravel already validates input ranges before this is ever called, but the
API re-validates independently since it's a general HTTP endpoint on
localhost during development and shouldn't trust its caller blindly.

POST /train
    multipart form, field "dataset" = a CSV shaped like train_model.py
    expects, header X-Train-Secret = TRAIN_SHARED_SECRET
    -> retrains, overwrites triage_model.pkl, hot-reloads it into this
       running process, returns the same metrics train_model.py prints

Requires the shared-secret header specifically so this can't be hit by
anyone who happens to find this API's port, even though Laravel's own
/train page is separately password-gated — the model file is exactly the
kind of thing that shouldn't be silently replaceable by whoever finds this
URL first.
"""

import os
import sys

import joblib
from flask import Flask, jsonify, request

import train_model

# Must match laravel-app/.env's TRAIN_SHARED_SECRET — there's no shared env
# loading between the two processes, so both sides hardcode the same value
# and it's changed in both places together if it's ever rotated.
TRAIN_SHARED_SECRET = os.environ.get("TRAIN_SHARED_SECRET", "03fba325c50981107f840218ba31c8a45582db301a93dad1")

# Resolved relative to this file, not the process's working directory —
# the API is sometimes launched from outside ml/ (e.g. by a process
# manager), and a bare relative path would silently fail to find the model.
#
# When frozen by PyInstaller, __file__ resolves inside the temp extraction
# dir (sys._MEIPASS) instead — fine for reading a bundled default, but /train
# overwrites this file at runtime, and anything written into that temp dir
# is gone the next time the exe launches. Use the actual exe's own directory
# instead so a retrained model actually persists across runs.
if getattr(sys, "frozen", False):
    MODEL_PATH = os.path.join(os.path.dirname(sys.executable), "triage_model.pkl")
else:
    MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "triage_model.pkl")

# Same physiological range contract the Laravel form validates against —
# keep these two in sync if either side changes.
RANGES = {
    "age": (0, 120),
    "sbp": (40, 300),
    "dbp": (20, 200),
    "hr": (20, 250),
    "rr": (4, 60),
    "temp": (30, 43),
    "pain_score": (0, 10),
}

app = Flask(__name__)

try:
    artifact = joblib.load(MODEL_PATH)
    model = artifact["model"]
    FEATURES = artifact["features"]
except FileNotFoundError:
    model = None
    FEATURES = list(RANGES.keys())


@app.get("/health")
def health():
    return jsonify({"status": "ok", "model_loaded": model is not None})


@app.post("/predict")
def predict():
    if model is None:
        return jsonify({"error": "Model not trained yet — run train_model.py first."}), 503

    if not request.is_json:
        return jsonify({"error": "Request body must be JSON."}), 400

    payload = request.get_json()

    missing = [f for f in FEATURES if f not in payload]
    if missing:
        return jsonify({"error": f"Missing required field(s): {missing}"}), 422

    values = {}
    for field in FEATURES:
        raw = payload[field]
        try:
            value = float(raw)
        except (TypeError, ValueError):
            return jsonify({"error": f"'{field}' must be a number, got {raw!r}."}), 422

        low, high = RANGES[field]
        if not (low <= value <= high):
            return jsonify({"error": f"'{field}' must be between {low} and {high}, got {value}."}), 422

        values[field] = value

    row = [[values[f] for f in FEATURES]]
    probabilities = model.predict_proba(row)[0]
    predicted_class = int(model.classes_[probabilities.argmax()])
    confidence = float(probabilities.max())

    return jsonify({
        "ats_category": predicted_class,
        "confidence": round(confidence, 4),
    })


@app.post("/train")
def train_endpoint():
    global model, FEATURES

    if request.headers.get("X-Train-Secret") != TRAIN_SHARED_SECRET:
        return jsonify({"error": "Invalid or missing training secret."}), 401

    if "dataset" not in request.files:
        return jsonify({"error": "No file uploaded under the 'dataset' field."}), 422

    file = request.files["dataset"]
    algorithm = request.form.get("algorithm", "rf")

    try:
        df = train_model.load_dataset(file.stream)
        metrics = train_model.train(df, MODEL_PATH, verbose=False, algorithm=algorithm)
    except train_model.DatasetError as e:
        return jsonify({"error": str(e)}), 422
    except Exception as e:
        return jsonify({"error": f"Training failed: {e}"}), 500

    # Hot-reload the freshly trained model into this running process so the
    # very next /predict call uses it — no server restart needed.
    artifact = joblib.load(MODEL_PATH)
    model = artifact["model"]
    FEATURES = artifact["features"]

    return jsonify(metrics)


if __name__ == "__main__":
    # debug=True's reloader spawns a child process to watch for file changes
    # — harmless in normal dev use, but a PyInstaller-frozen exe re-launching
    # itself as its own "child" causes an infinite relaunch loop. Off by
    # default (safe for the packaged app); set TRIAGE_API_DEBUG=1 for the
    # dev workflow's auto-reload.
    app.run(host="127.0.0.1", port=5055, debug=os.environ.get("TRIAGE_API_DEBUG") == "1")
