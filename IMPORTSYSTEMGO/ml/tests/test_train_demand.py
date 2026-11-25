import json
import math
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path
from types import SimpleNamespace

from ml.train_demand import train


class DemandTrainingTest(unittest.TestCase):
    def test_temporal_training_produces_versionable_predictions(self):
        start = date(2025, 1, 1)
        series = []
        observations = []
        for branch_id in range(1, 4):
            for product_id in range(1, 5):
                series.append(
                    {
                        "branch_id": branch_id,
                        "branch_code": f"B{branch_id:02d}",
                        "product_id": product_id,
                        "sku": f"SKU-{product_id:03d}",
                        "category_id": (product_id % 2) + 1,
                        "brand_id": (product_id % 3) + 1,
                    }
                )
                for offset in range(240):
                    current = start + timedelta(days=offset)
                    weekly = 1.4 if current.weekday() >= 5 else 1.0
                    trend = 1 + offset / 1200
                    quantity = max(0, round((0.4 + product_id * 0.17) * (0.8 + branch_id * 0.1) * weekly * trend + math.sin(offset / 11)))
                    observations.append(
                        {"branch_id": branch_id, "product_id": product_id, "date": current.isoformat(), "quantity": quantity}
                    )
        payload = {"metadata": {"source": "synthetic_test"}, "series": series, "observations": observations}
        with tempfile.TemporaryDirectory() as directory:
            artifact = str(Path(directory) / "model.joblib")
            result = train(
                payload,
                SimpleNamespace(
                    minimum_history_days=180,
                    minimum_training_rows=500,
                    horizon_days=30,
                    artifact=artifact,
                ),
            )
            self.assertEqual("HistGradientBoostingRegressor", result["algorithm"])
            self.assertEqual("synthetic_test", result["data_source"])
            self.assertEqual(12, len(result["predictions"]))
            self.assertGreater(result["metrics"]["validation_rows"], 0)
            self.assertGreaterEqual(result["metrics"]["wape"], 0)
            self.assertEqual(64, len(result["artifact_sha256"]))
            self.assertTrue(Path(artifact).is_file())
            json.dumps(result)


if __name__ == "__main__":
    unittest.main()
