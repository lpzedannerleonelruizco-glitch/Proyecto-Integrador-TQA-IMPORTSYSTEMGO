"""Train a branch/product demand model from a credential-free JSON dataset."""

from __future__ import annotations

import argparse
import hashlib
import json
import math
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.metrics import mean_absolute_error
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


HORIZON = 30
LAGS = (1, 7, 14, 28)


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--artifact", required=True)
    parser.add_argument("--minimum-history-days", type=int, default=180)
    parser.add_argument("--minimum-training-rows", type=int, default=500)
    parser.add_argument("--horizon-days", type=int, default=HORIZON)
    return parser.parse_args()


def load_frame(payload: dict, minimum_history_days: int) -> pd.DataFrame:
    series = pd.DataFrame(payload["series"])
    observations = pd.DataFrame(payload["observations"])
    if series.empty or observations.empty:
        raise ValueError("No existen ventas suficientes para entrenar el modelo.")

    observations["date"] = pd.to_datetime(observations["date"])
    start = observations["date"].min().normalize()
    end = observations["date"].max().normalize()
    if (end - start).days + 1 < minimum_history_days:
        raise ValueError(
            f"Se requieren al menos {minimum_history_days} días de historia; "
            f"solo existen {(end - start).days + 1}."
        )

    calendar = pd.DataFrame({"date": pd.date_range(start, end, freq="D")})
    grid = series.assign(_key=1).merge(calendar.assign(_key=1), on="_key").drop(columns="_key")
    frame = grid.merge(
        observations[["branch_id", "product_id", "date", "quantity"]],
        on=["branch_id", "product_id", "date"],
        how="left",
    )
    frame["quantity"] = frame["quantity"].fillna(0.0).clip(lower=0.0)
    return frame.sort_values(["branch_id", "product_id", "date"]).reset_index(drop=True)


def features(frame: pd.DataFrame, horizon: int) -> pd.DataFrame:
    grouped = frame.groupby(["branch_id", "product_id"], sort=False)["quantity"]
    for lag in LAGS:
        frame[f"lag_{lag}"] = grouped.shift(lag)
    for window in (7, 14, 28):
        frame[f"rolling_mean_{window}"] = grouped.transform(
            lambda values: values.shift(1).rolling(window, min_periods=window).mean()
        )
        frame[f"rolling_std_{window}"] = grouped.transform(
            lambda values: values.shift(1).rolling(window, min_periods=window).std(ddof=0)
        )
    frame["target"] = grouped.transform(
        lambda values: values.shift(-1).rolling(horizon, min_periods=horizon).sum().shift(-(horizon - 1))
    )
    frame["baseline"] = frame["rolling_mean_28"] * horizon
    frame["day_of_week"] = frame["date"].dt.day_of_week
    frame["month"] = frame["date"].dt.month
    frame["day_of_year"] = frame["date"].dt.day_of_year
    return frame


def wape(actual: np.ndarray, predicted: np.ndarray) -> float:
    denominator = float(np.abs(actual).sum())
    return float(np.abs(actual - predicted).sum() / denominator) if denominator else 0.0


def train(payload: dict, args: argparse.Namespace) -> dict:
    horizon = args.horizon_days
    frame = features(load_frame(payload, args.minimum_history_days), horizon)
    feature_columns = [
        "branch_id", "product_id", "category_id", "brand_id", "day_of_week", "month", "day_of_year",
        *[f"lag_{lag}" for lag in LAGS],
        *[f"rolling_mean_{window}" for window in (7, 14, 28)],
        *[f"rolling_std_{window}" for window in (7, 14, 28)],
    ]
    training = frame.dropna(subset=feature_columns + ["target", "baseline"]).copy()
    if len(training) < args.minimum_training_rows:
        raise ValueError(
            f"Se requieren al menos {args.minimum_training_rows} filas útiles; solo existen {len(training)}."
        )

    unique_dates = np.sort(training["date"].unique())
    cutoff = unique_dates[max(1, math.floor(len(unique_dates) * 0.8)) - 1]
    # Purge one complete forecast horizon so no training target reaches into validation dates.
    train_rows = training[training["date"] <= cutoff - pd.Timedelta(days=horizon)]
    test_rows = training[training["date"] > cutoff]
    if train_rows.empty or test_rows.empty:
        raise ValueError("No existe un período posterior independiente para validar el modelo.")

    categorical = ["branch_id", "product_id", "category_id", "brand_id", "day_of_week", "month"]
    numeric = [column for column in feature_columns if column not in categorical]
    pipeline = Pipeline(
        [
            (
                "preprocess",
                ColumnTransformer(
                    [
                        ("categorical", OneHotEncoder(handle_unknown="ignore", sparse_output=False), categorical),
                        ("numeric", "passthrough", numeric),
                    ]
                ),
            ),
            (
                "model",
                HistGradientBoostingRegressor(
                    loss="poisson",
                    max_iter=220,
                    learning_rate=0.06,
                    max_leaf_nodes=24,
                    l2_regularization=0.2,
                    random_state=20261118,
                ),
            ),
        ]
    )
    pipeline.fit(train_rows[feature_columns], train_rows["target"])
    predicted = np.maximum(0.0, pipeline.predict(test_rows[feature_columns]))
    actual = test_rows["target"].to_numpy(dtype=float)
    baseline = test_rows["baseline"].to_numpy(dtype=float)
    residual_std = float(np.std(actual - predicted))
    ml_wape = wape(actual, predicted)
    baseline_wape = wape(actual, baseline)

    # Retrain the selected candidate with every row after temporal evaluation.
    pipeline.fit(training[feature_columns], training["target"])
    latest = frame.dropna(subset=feature_columns).groupby(["branch_id", "product_id"], as_index=False).tail(1)
    future = np.maximum(0.0, pipeline.predict(latest[feature_columns]))
    predictions = []
    for row, value in zip(latest.to_dict("records"), future, strict=True):
        uncertainty = max(residual_std * 1.645, value * 0.10)
        predictions.append(
            {
                "branch_id": int(row["branch_id"]),
                "product_id": int(row["product_id"]),
                "predicted_quantity": round(float(value), 2),
                "lower_bound": round(max(0.0, float(value) - uncertainty), 2),
                "upper_bound": round(float(value) + uncertainty, 2),
                "baseline_quantity": round(max(0.0, float(row["baseline"])), 2),
            }
        )

    artifact = Path(args.artifact)
    artifact.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(
        {
            "pipeline": pipeline,
            "features": feature_columns,
            "trained_until": str(frame["date"].max().date()),
            "horizon_days": horizon,
        },
        artifact,
    )
    digest = hashlib.sha256(artifact.read_bytes()).hexdigest()
    return {
        "algorithm": "HistGradientBoostingRegressor",
        "trained_from": str(frame["date"].min().date()),
        "trained_until": str(frame["date"].max().date()),
        "training_rows": int(len(training)),
        "metrics": {
            "mae": round(float(mean_absolute_error(actual, predicted)), 4),
            "wape": round(ml_wape, 6),
            "bias": round(float((predicted - actual).mean()), 4),
            "baseline_wape": round(baseline_wape, 6),
            "validation_rows": int(len(test_rows)),
            "champion": bool(ml_wape < baseline_wape),
        },
        "feature_schema": feature_columns,
        "data_source": payload.get("metadata", {}).get("source", "unknown"),
        "artifact_sha256": digest,
        "predictions": predictions,
    }


def main() -> int:
    args = arguments()
    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8"))
        result = train(payload, args)
        Path(args.output).write_text(json.dumps({"ok": True, **result}), encoding="utf-8")
        return 0
    except Exception as error:  # A controlled, credential-free error contract for Laravel.
        Path(args.output).write_text(
            json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False), encoding="utf-8"
        )
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
