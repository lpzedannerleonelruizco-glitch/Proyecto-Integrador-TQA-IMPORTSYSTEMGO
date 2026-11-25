import argparse
import csv
import json
import os
import re
import shutil
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
ROBOT_SUITE = ROOT / "qa_engine" / "robot" / "tests" / "qa_smoke.robot"
LOCUST_FILE = ROOT / "qa_engine" / "performance" / "locustfile.py"
SENSITIVE_ENV = {"APP_KEY", "DB_PASSWORD", "QA_E2E_PASSWORD", "QA_API_KEY", "QA_AI_API_KEY"}


def safe_json(value):
    return json.dumps(value, ensure_ascii=False, indent=2)


def utc_now():
    return datetime.now(timezone.utc).isoformat()


def sanitized_environment(overrides=None):
    environment = {key: value for key, value in os.environ.items() if key not in SENSITIVE_ENV}
    environment.update(overrides or {})
    return environment


def result_for(case, status, *, obtained=None, observations=None, failure_step=None,
               error_message=None, duration_ms=0, steps=None, screenshot=None):
    return {
        "code": case["code"],
        "name": case["name"],
        "suite": case.get("suite") or case.get("category", ""),
        "executor": case.get("executor", ""),
        "automation_status": case.get("automation", {}).get("status", "NOT RUN"),
        "objective": case.get("objective"),
        "preconditions": case.get("preconditions"),
        "test_data": case.get("test_data"),
        "validations": case.get("validations"),
        "expected": case.get("expected_result"),
        "status": status,
        "duration_ms": int(duration_ms),
        "obtained": obtained,
        "observations": observations,
        "failure_step": failure_step,
        "error_message": error_message,
        "steps": steps or [],
        "screenshot": screenshot,
    }


def is_local_target(base_url):
    parsed = urlparse(base_url)
    return parsed.scheme == "http" and parsed.hostname in {"127.0.0.1", "localhost"}


def phpunit_command(test_filter):
    php = shutil.which("php")
    if not php:
        raise RuntimeError("php_unavailable")

    command = [php]
    if os.name == "nt":
        extension_dir = Path(php).resolve().parent / "ext"
        required_extensions = ("php_pdo_sqlite.dll", "php_sqlite3.dll")
        if not all((extension_dir / extension).is_file() for extension in required_extensions):
            raise RuntimeError("php_sqlite_extensions_unavailable")
        command.extend(["-d", f"extension_dir={extension_dir}"])
        for extension in required_extensions:
            command.extend(["-d", f"extension={extension}"])

    command.extend([
        str(ROOT / "vendor" / "bin" / "phpunit"),
        "--configuration",
        str(ROOT / "phpunit.xml"),
        "--filter",
        test_filter,
    ])
    return command


def summarize_phpunit_output(output, returncode):
    clean_output = re.sub(r"\x1b\[[0-?]*[ -/]*[@-~]", "", output)
    lines = [line.strip() for line in clean_output.splitlines() if line.strip()]
    summary = next(
        (line for line in reversed(lines)
         if re.match(r"^(OK|FAILURES|ERRORS|WARNINGS|There was)", line)),
        lines[-1] if lines else "Sin salida PHPUnit",
    )
    passed = returncode == 0 and re.match(r"^OK \(\d+ tests?,", summary) is not None
    return passed, summary


def run_phpunit(case, test_filter):
    started = time.monotonic()
    environment = sanitized_environment({
        "APP_ENV": "testing",
        "APP_DEBUG": "false",
        "DB_CONNECTION": "sqlite",
        "DB_DATABASE": ":memory:",
        "DB_URL": "null",
        "QUEUE_CONNECTION": "sync",
        "MAIL_MAILER": "array",
        "SESSION_DRIVER": "array",
        "CACHE_STORE": "array",
    })
    completed = subprocess.run(
        phpunit_command(test_filter),
        cwd=ROOT,
        env=environment,
        capture_output=True,
        text=True,
        timeout=300,
        check=False,
    )
    duration = round((time.monotonic() - started) * 1000)
    output = completed.stdout + "\n" + completed.stderr
    passed, summary = summarize_phpunit_output(output, completed.returncode)
    return result_for(
        case,
        "PASS" if passed else "FAIL",
        obtained=f"phpunit.xml con SQLite :memory:; {summary}",
        observations="Prueba enfocada ejecutada en aislamiento; no usa database/qa.sqlite.",
        failure_step=None if passed else "Aserciones de la prueba PHPUnit seleccionada",
        error_message=None if passed else "phpunit_test_failed",
        duration_ms=duration,
        steps=[{
            "name": f"PHPUnit: {test_filter}",
            "description": "Suite configurada explícitamente con phpunit.xml y SQLite en memoria.",
            "status": "PASS" if passed else "FAIL",
            "obtained": summary,
            "error_message": None if passed else "phpunit_test_failed",
        }],
    )


def edge_driver():
    from selenium import webdriver

    options = webdriver.EdgeOptions()
    options.add_argument("--headless=new")
    options.add_argument("--disable-gpu")
    options.add_argument("--window-size=1440,1000")
    options.add_argument("--disable-dev-shm-usage")
    return webdriver.Edge(options=options)


def run_selenium(case, base_url, execution_dir, controlled_fail):
    password = os.environ.get("QA_E2E_PASSWORD")
    scenario = case.get("automation", {}).get("scenario")
    if not password and scenario != "controlled_validation":
        return result_for(
            case,
            "BLOCKED",
            observations="Falta QA_E2E_PASSWORD en la configuración local segura; no se intentó autenticar.",
            error_message="qa_e2e_credentials_missing",
        )

    from selenium.webdriver.common.by import By
    from selenium.webdriver.support import expected_conditions as expected_conditions
    from selenium.webdriver.support.ui import WebDriverWait

    screenshots_dir = execution_dir / "screenshots"
    screenshots_dir.mkdir(parents=True, exist_ok=True)
    screenshot_name = f"{case['code']}.png"
    screenshot_path = screenshots_dir / screenshot_name
    started = time.monotonic()
    driver = None
    steps = []
    try:
        driver = edge_driver()
        driver.set_page_load_timeout(30)
        wait = WebDriverWait(driver, 15)
        driver.get(base_url.rstrip("/") + "/login")
        email = driver.find_element(By.ID, "email")
        if email.get_attribute("type") != "email" or email.get_attribute("name") != "email":
            raise AssertionError("login_email_locator_or_type_mismatch")
        email.send_keys(os.environ.get("QA_E2E_EMAIL", "qa.admin@example.test"))
        actual_email = email.get_attribute("value")
        if actual_email != "qa.admin@example.test":
            raise AssertionError("qa_email_literal_mismatch")
        steps.append({
            "name": "Abrir el formulario real de acceso",
            "description": "El formulario presenta el campo HTML email con el nombre esperado.",
            "status": "PASS",
            "obtained": "input#email, type=email y name=email disponibles.",
        })

        if scenario == "controlled_validation":
            email.clear()
            email.send_keys("not-an-email")
            form_state = driver.execute_script(
                "const input = arguments[0]; input.form.requestSubmit(); "
                "return {typeMismatch: input.validity.typeMismatch, "
                "formValid: input.form.checkValidity(), validationMessage: input.validationMessage};",
                email,
            )
            obtained = (
                f"validity.typeMismatch={str(form_state['typeMismatch']).lower()}; "
                f"form.checkValidity()={str(form_state['formValid']).lower()}; "
                f"mensaje de validación presente={str(bool(form_state['validationMessage'])).lower()}"
            )
            driver.save_screenshot(str(screenshot_path))
            if not form_state["typeMismatch"] or form_state["formValid"]:
                return result_for(
                    case,
                    "FAIL",
                    obtained=obtained,
                    observations="La regla HTML5 no rechazó el correo inválido; el comportamiento debe investigarse.",
                    failure_step="Validación HTML5 del campo Correo electrónico",
                    error_message="La validación real del navegador permitió un valor inválido.",
                    duration_ms=(time.monotonic() - started) * 1000,
                    steps=steps + [{
                        "name": "Validar el formato de correo",
                        "description": "Se envía un valor con formato inválido al campo type=email.",
                        "status": "FAIL",
                        "obtained": obtained,
                        "error_message": "La validación del navegador no rechazó el valor.",
                    }],
                    screenshot=screenshot_name,
                )

            if not controlled_fail:
                return result_for(
                    case,
                    "PASS",
                    obtained=obtained,
                    observations="La validación HTML5 real rechazó el correo inválido y bloqueó el envío.",
                    duration_ms=(time.monotonic() - started) * 1000,
                    steps=steps + [{
                        "name": "Validación HTML5 del campo Correo electrónico",
                        "description": "Se verifica que el navegador impide enviar un correo inválido.",
                        "status": "PASS",
                        "obtained": obtained,
                    }],
                    screenshot=screenshot_name,
                )

            error_message = "FAIL CONTROLADO: la expectativa de demostración invertida no coincidió con la validación HTML5 real."
            return result_for(
                case,
                "FAIL",
                obtained=obtained,
                observations="FAIL CONTROLADO — expectativa de demostración invertida; no representa un defecto empresarial.",
                failure_step="Aserción deliberadamente invertida después de verificar la validación HTML5 real",
                error_message=error_message,
                duration_ms=(time.monotonic() - started) * 1000,
                steps=steps + [{
                    "name": "Validación HTML5 del campo Correo electrónico",
                    "description": "La prueba confirma typeMismatch y bloquea el envío del formulario.",
                    "status": "FAIL",
                    "obtained": obtained,
                    "error_message": error_message,
                }],
                screenshot=screenshot_name,
            )

        password_input = driver.find_element(By.ID, "password")
        if password_input.get_attribute("type") != "password":
            raise AssertionError("login_password_locator_or_type_mismatch")
        password_input.send_keys(password)
        button = driver.find_element(
            By.CSS_SELECTOR,
            'form[action$="/login"] button[type="submit"], form[action$="/login"] button:not([type])',
        )
        button.click()
        wait.until(lambda browser: urlparse(browser.current_url).path != "/login")
        steps.append({
            "name": "Enviar correo literal y contraseña al formulario",
            "description": "Selenium envía el valor de correo literal, sin formato Markdown.",
            "status": "PASS",
            "obtained": "El navegador salió de /login después del POST autenticado.",
        })

        driver.get(base_url.rstrip("/") + "/qa")
        dashboard = wait.until(
            expected_conditions.visibility_of_element_located(
                (By.XPATH, "//*[normalize-space()='IMPORTSYSTEMGO QA CENTER']")
            )
        )
        if not dashboard.is_displayed():
            raise AssertionError("qa_dashboard_not_visible")
        steps.append({
            "name": "Abrir QA Center con la sesión autenticada",
            "description": "El middleware de sesión conserva la autenticación y el permiso de QA.",
            "status": "PASS",
            "obtained": "IMPORTSYSTEMGO QA CENTER visible en /qa.",
        })
        driver.save_screenshot(str(screenshot_path))
        return result_for(
            case,
            "PASS",
            obtained="Inicio de sesión y acceso autenticado al dashboard QA verificados en Edge WebDriver.",
            observations="CSRF, cookies y regeneración de sesión se procesaron mediante el formulario web real.",
            duration_ms=(time.monotonic() - started) * 1000,
            steps=steps,
            screenshot=screenshot_name,
        )
    except Exception as error:
        if driver:
            try:
                driver.save_screenshot(str(screenshot_path))
            except Exception:
                pass
        error_code = type(error).__name__
        return result_for(
            case,
            "FAIL",
            obtained=f"El flujo de navegador no alcanzó el resultado esperado ({error_code}).",
            observations="Consulte los pasos y la captura adjunta; no se incluyen credenciales en el reporte.",
            failure_step="Formulario, sesión autenticada o dashboard QA",
            error_message=f"selenium_{error_code.lower()}",
            duration_ms=(time.monotonic() - started) * 1000,
            steps=steps + [{
                "name": "Completar el flujo Selenium",
                "description": "Verificar autenticación con CSRF y acceso autorizado.",
                "status": "FAIL",
                "obtained": error_code,
                "error_message": f"selenium_{error_code.lower()}",
            }],
            screenshot=screenshot_name if screenshot_path.is_file() else None,
        )
    finally:
        if driver:
            driver.quit()


def run_robot(case, base_url, execution_dir):
    output_dir = execution_dir / "robot"
    screenshots_dir = execution_dir / "screenshots"
    output_dir.mkdir(parents=True, exist_ok=True)
    screenshots_dir.mkdir(parents=True, exist_ok=True)
    test_name = case.get("automation", {}).get("test")
    command = [
        sys.executable,
        "-m",
        "robot",
        "--outputdir",
        str(output_dir),
        "--variable",
        f"BASE_URL:{base_url}",
        "--variable",
        f"SCREENSHOT_DIR:{screenshots_dir}",
    ]
    if test_name:
        command.extend(["--test", test_name])
    command.append(str(ROBOT_SUITE))
    started = time.monotonic()
    completed = subprocess.run(
        command,
        cwd=ROOT,
        env=sanitized_environment(),
        capture_output=True,
        text=True,
        timeout=120,
        check=False,
    )
    duration = round((time.monotonic() - started) * 1000)
    output_xml = output_dir / "output.xml"
    passed = completed.returncode == 0
    if output_xml.is_file():
        root = ET.parse(output_xml).getroot()
        statuses = [node.get("status", "") for node in root.iter("status") if node.get("status")]
        passed = passed and bool(statuses) and all(status == "PASS" for status in statuses)
    screenshot_source = screenshots_dir / "protected_redirect.png"
    screenshot_name = None
    if screenshot_source.is_file():
        screenshot_name = "TC-NF-005.png"
        shutil.copy2(screenshot_source, screenshots_dir / screenshot_name)
    summary = "Robot Framework verificó que /dashboard redirige al formulario de acceso."
    if not passed:
        summary = "Robot Framework no completó la validación de ruta protegida."
    return result_for(
        case,
        "PASS" if passed else "FAIL",
        obtained=summary,
        observations="Ejecución Robot Framework/SeleniumLibrary; el usuario anónimo no envía credenciales.",
        failure_step=None if passed else "Redirección de ruta protegida al login",
        error_message=None if passed else "robot_test_failed",
        duration_ms=duration,
        steps=[{
            "name": "TC-NF-005 Ruta protegida requiere autenticación",
            "description": "Visitar /dashboard sin sesión y verificar redirección a /login.",
            "status": "PASS" if passed else "FAIL",
            "obtained": summary,
            "error_message": None if passed else "robot_test_failed",
        }],
        screenshot=screenshot_name,
    )


def read_locust_stats(stats_path):
    if not stats_path.is_file():
        return None
    with stats_path.open(encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.DictReader(handle))
    aggregate = next(
        (row for row in rows if row.get("Type", "").lower() == "aggregated"
         or row.get("Name", "").lower() == "aggregated"),
        None,
    )
    if aggregate is None:
        aggregate = next((row for row in reversed(rows) if row.get("Name", "") == ""), None)
    if aggregate is None:
        return None

    def integer_value(*keys):
        for key in keys:
            try:
                return int(float(aggregate.get(key, "0") or "0"))
            except (TypeError, ValueError):
                continue
        return 0

    percentile_key = next((key for key in aggregate if "95" in key.lower()), None)
    try:
        p95 = int(float(aggregate.get(percentile_key, "0") or "0")) if percentile_key else 0
    except (TypeError, ValueError):
        p95 = 0
    return {
        "requests": integer_value("Request Count"),
        "failures": integer_value("Failure Count"),
        "p95_ms": p95,
        "row": aggregate,
    }


def run_locust(case, base_url, execution_dir):
    profile = case.get("automation", {})
    users = int(profile.get("users", 1))
    requests_per_user = int(profile.get("requests_per_user", 1))
    total_requests = users * requests_per_user
    performance_dir = execution_dir / "performance"
    performance_dir.mkdir(parents=True, exist_ok=True)
    prefix = performance_dir / case["code"]
    command = [
        sys.executable,
        "-m",
        "locust",
        "-f",
        str(LOCUST_FILE),
        "--headless",
        "--users",
        str(users),
        "--spawn-rate",
        str(users),
        "--run-time",
        "90s",
        "--stop-timeout",
        "5",
        "--csv",
        str(prefix),
        "--only-summary",
        "--loglevel",
        "WARNING",
        "--host",
        base_url,
    ]
    started = time.monotonic()
    completed = subprocess.run(
        command,
        cwd=ROOT,
        env=sanitized_environment({"QA_LOCUST_MAX_REQUESTS": str(total_requests)}),
        capture_output=True,
        text=True,
        timeout=150,
        check=False,
    )
    duration = round((time.monotonic() - started) * 1000)
    stats = read_locust_stats(Path(f"{prefix}_stats.csv"))
    if stats is None:
        return result_for(
            case,
            "FAIL",
            obtained="Locust no generó el CSV agregado de estadísticas.",
            failure_step="Generación y lectura del CSV original de Locust",
            error_message="locust_stats_missing",
            duration_ms=duration,
        )

    expected_requests = total_requests
    correct_count = stats["requests"] == expected_requests
    no_failures = stats["failures"] == 0
    latency_ok = case["code"] != "TC-NF-001" or stats["p95_ms"] <= 3000
    passed = completed.returncode == 0 and correct_count and no_failures and latency_ok
    obtained = (
        f"CSV Locust: {stats['requests']} solicitudes, {stats['failures']} fallidas, "
        f"p95={stats['p95_ms']} ms; esperado={expected_requests} solicitudes."
    )
    observations = "Carga breve y de solo lectura contra el QA local; no es certificación de capacidad."
    return result_for(
        case,
        "PASS" if passed else "FAIL",
        obtained=obtained,
        observations=observations,
        failure_step=None if passed else "Conteo, errores o umbral p95 de Locust",
        error_message=None if passed else "locust_threshold_or_request_count_failed",
        duration_ms=duration,
        steps=[{
            "name": f"Perfil Locust de {users} usuario(s)",
            "description": f"Se esperan {expected_requests} solicitudes según el CSV agregado.",
            "status": "PASS" if passed else "FAIL",
            "obtained": obtained,
            "error_message": None if passed else "locust_threshold_or_request_count_failed",
        }],
    )


def run_case(case, base_url, execution_dir, controlled_fail):
    automation = case.get("automation", {})
    if automation.get("status") != "IMPLEMENTED":
        status = "BLOCKED" if automation.get("status") == "BLOCKED" else "NOT RUN"
        reason = automation.get("reason", "No hay un ejecutor automatizado implementado para este caso.")
        return result_for(
            case,
            status,
            observations=reason,
            error_message="automation_blocked" if status == "BLOCKED" else "automation_not_implemented",
        )

    engine = automation.get("engine")
    if engine == "phpunit":
        return run_phpunit(case, automation["test"])
    if engine == "selenium":
        return run_selenium(case, base_url, execution_dir, controlled_fail)
    if engine == "robot":
        return run_robot(case, base_url, execution_dir)
    if engine == "locust":
        return run_locust(case, base_url, execution_dir)

    return result_for(
        case,
        "BLOCKED",
        observations=f"Motor de automatización desconocido: '{engine}'.",
        error_message="automation_engine_unknown",
    )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--execution-id", required=True)
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--execution-dir", required=True)
    parser.add_argument("--base-url", required=True)
    args = parser.parse_args()

    if not is_local_target(args.base_url):
        raise RuntimeError("qa_runner_requires_local_http_target")

    execution_dir = Path(args.execution_dir).resolve()
    screenshots_dir = execution_dir / "screenshots"
    logs_dir = execution_dir / "logs"
    for directory in (screenshots_dir, logs_dir, execution_dir / "reports"):
        directory.mkdir(parents=True, exist_ok=True)

    manifest_path = Path(args.manifest).resolve()
    cases = json.loads(manifest_path.read_text(encoding="utf-8"))
    started_monotonic = time.monotonic()
    started = utc_now()
    controlled_fail = os.environ.get("QA_CONTROLLED_FAIL") == "1"
    results = [
        run_case(case, args.base_url, execution_dir, controlled_fail)
        for case in cases
    ]
    finished = utc_now()
    duration_ms = round((time.monotonic() - started_monotonic) * 1000)
    metadata = {
        "execution_id": args.execution_id,
        "environment": "SQLite QA local",
        "base_url": args.base_url,
        "started_at": started,
        "finished_at": finished,
        "duration_ms": duration_ms,
        "engine": "IMPORTSYSTEMGO QA Engine",
        "controlled_fail": controlled_fail,
        "runner_version": 2,
    }
    (execution_dir / "metadata.json").write_text(safe_json(metadata), encoding="utf-8")
    (execution_dir / "results.json").write_text(safe_json({"results": results}), encoding="utf-8")
    (logs_dir / "runner.log").write_text(
        safe_json({
            "execution_id": args.execution_id,
            "results": [
                {"code": result["code"], "status": result["status"], "engine": result["executor"]}
                for result in results
            ],
        }),
        encoding="utf-8",
    )
    print(json.dumps({"execution_id": args.execution_id, "result_count": len(results)}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
