import tempfile
import unittest
from pathlib import Path

from PIL import Image

from qa_engine import generate_reports, runner


class RunnerClassificationTests(unittest.TestCase):
    def test_blocked_and_unimplemented_cases_keep_distinct_statuses(self):
        with tempfile.TemporaryDirectory() as temporary_directory:
            execution_dir = Path(temporary_directory)
            for automation_status, expected_status in (("BLOCKED", "BLOCKED"), ("NOT RUN", "NOT RUN")):
                case = {
                    "code": "TC-TEST-001",
                    "name": "Clasificación de catálogo",
                    "automation": {
                        "status": automation_status,
                        "reason": "Requiere precondiciones adicionales.",
                    },
                }
                with self.subTest(status=automation_status):
                    result = runner.run_case(case, "http://127.0.0.1:8001", execution_dir, False)
                    self.assertEqual(expected_status, result["status"])

    def test_phpunit_success_summary_is_detected_and_empty_suite_is_not_a_pass(self):
        passed, summary = runner.summarize_phpunit_output(
            "\x1b[30;42mOK (1 test, 17 assertions)\x1b[0m",
            0,
        )
        self.assertTrue(passed)
        self.assertEqual("OK (1 test, 17 assertions)", summary)

        passed, summary = runner.summarize_phpunit_output("No tests executed!", 0)
        self.assertFalse(passed)


class ReportGenerationTests(unittest.TestCase):
    def test_reports_include_case_details_and_captured_evidence(self):
        with tempfile.TemporaryDirectory() as temporary_directory:
            execution_dir = Path(temporary_directory)
            screenshots_dir = execution_dir / "screenshots"
            screenshots_dir.mkdir()
            Image.new("RGB", (32, 24), color=(20, 40, 80)).save(screenshots_dir / "TC-TEST-001.png")

            results = generate_reports.prepare_results([{
                "code": "TC-TEST-001",
                "name": "Validación de demostración",
                "suite": "Funcionales",
                "status": "FAIL",
                "duration_ms": 125,
                "objective": "Comprobar una regla real",
                "preconditions": "Formulario accesible",
                "test_data": {"email": "not-an-email"},
                "validations": ["HTML5 type=email"],
                "expected": "El navegador rechaza el valor",
                "obtained": "validity.typeMismatch=true",
                "failure_step": "Aserción de demostración",
                "error_message": "FAIL CONTROLADO: expectativa invertida",
                "observations": "No representa un defecto empresarial.",
                "steps": [{
                    "name": "Validar formato",
                    "status": "FAIL",
                    "obtained": "El navegador impidió el envío.",
                    "error_message": "FAIL CONTROLADO",
                }],
                "screenshot": "TC-TEST-001.png",
            }], screenshots_dir)
            context = {
                "execution_id": "test-execution",
                "started_at": "2026-01-01T10:00:00Z",
                "finished_at": "2026-01-01T10:00:01Z",
                "duration_ms": 1000,
                "environment": "SQLite QA local",
                "base_url": "http://127.0.0.1:8001",
                "suite": "Funcionales",
                "recipient": "qa.admin@example.test",
                "counts": {status: int(status == "FAIL") for status in generate_reports.STATUS_ORDER},
                "success": "0.0%",
                "results": results,
            }
            pdf_path = execution_dir / "report.pdf"
            html_path = execution_dir / "report.html"

            generate_reports.make_pdf(pdf_path, context, screenshots_dir)
            generate_reports.make_html(html_path, context)

            pdf_bytes = pdf_path.read_bytes()
            html_text = html_path.read_text(encoding="utf-8")
            self.assertTrue(pdf_bytes.startswith(b"%PDF-"))
            self.assertGreater(len(pdf_bytes), 1000)
            for expected_text in (
                "FAIL CONTROLADO",
                "validity.typeMismatch=true",
                "Resultado esperado",
                "Datos de prueba",
                "Pasos ejecutados",
            ):
                self.assertIn(expected_text, html_text)
            self.assertIn("../screenshots/TC-TEST-001.png", html_text)


if __name__ == "__main__":
    unittest.main()
