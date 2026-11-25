import argparse
import hashlib
import json
from pathlib import Path
from xml.sax.saxutils import escape

from jinja2 import Environment, select_autoescape
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.lib.utils import ImageReader
from reportlab.platypus import Image, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


STATUS_ORDER = ("PASS", "FAIL", "SKIPPED", "BLOCKED", "NOT RUN")


def sha256(path):
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def display_value(value):
    if value is None or value == "":
        return "No especificado"
    if isinstance(value, (dict, list)):
        return json.dumps(value, ensure_ascii=False, indent=2)
    return str(value)


def paragraph_text(value):
    return escape(display_value(value)).replace("\n", "<br/>")


def prepare_results(results, screenshots_dir):
    prepared = []
    for source in results:
        result = dict(source)
        screenshot = result.get("screenshot")
        if screenshot:
            screenshot_name = Path(str(screenshot)).name
            screenshot_path = screenshots_dir / screenshot_name
            result["screenshot"] = screenshot_name if screenshot_path.is_file() else None
        result["objective_text"] = display_value(result.get("objective"))
        result["preconditions_text"] = display_value(result.get("preconditions"))
        result["test_data_text"] = display_value(result.get("test_data"))
        result["validations_text"] = display_value(result.get("validations"))
        result["expected_text"] = display_value(result.get("expected"))
        result["obtained_text"] = display_value(result.get("obtained"))
        result["observations_text"] = display_value(result.get("observations"))
        result["error_text"] = display_value(result.get("error_message"))
        result["failure_step_text"] = display_value(result.get("failure_step"))
        result["duration_ms"] = int(result.get("duration_ms") or 0)
        result["steps"] = result.get("steps") or []
        prepared.append(result)
    return prepared


def make_pdf(pdf_path, context, screenshots_dir):
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(
        name="QaTitle",
        parent=styles["Title"],
        alignment=TA_CENTER,
        textColor=colors.HexColor("#081b38"),
        spaceAfter=12,
    ))
    styles.add(ParagraphStyle(name="QaLabel", parent=styles["BodyText"], fontName="Helvetica-Bold", fontSize=8, leading=10))
    styles.add(ParagraphStyle(name="QaCell", parent=styles["BodyText"], fontSize=8, leading=10, splitLongWords=True))
    styles.add(ParagraphStyle(name="QaSmall", parent=styles["BodyText"], fontSize=7, leading=9, splitLongWords=True))

    def para(value, style="QaCell"):
        return Paragraph(paragraph_text(value), styles[style])

    usable_width = 7 * inch
    story = [
        Paragraph("IMPORTSYSTEMGO QA CENTER", styles["QaTitle"]),
        Paragraph("Reporte de ejecución automatizada", styles["Heading2"]),
        Spacer(1, 8),
    ]

    general_rows = [
        [para("Identificador", "QaLabel"), para(context["execution_id"])],
        [para("Fecha de inicio", "QaLabel"), para(context["started_at"])],
        [para("Fecha de finalización", "QaLabel"), para(context["finished_at"])],
        [para("Duración total", "QaLabel"), para(f'{context["duration_ms"]} ms')],
        [para("Ambiente", "QaLabel"), para(context["environment"])],
        [para("URL QA local", "QaLabel"), para(context["base_url"])],
        [para("Suite", "QaLabel"), para(context["suite"])],
        [para("Destinatario", "QaLabel"), para(context["recipient"])],
    ]
    story.append(Table(general_rows, colWidths=[1.35 * inch, usable_width - 1.35 * inch], style=TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#cbd5e1")),
        ("BACKGROUND", (0, 0), (0, -1), colors.HexColor("#f1f5f9")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ])))
    story.extend([Spacer(1, 12), Paragraph("Resumen", styles["Heading2"])])

    metric_rows = [["Estado", "Cantidad"]]
    metric_rows.extend([[status, str(context["counts"][status])] for status in STATUS_ORDER])
    metric_rows.extend([
        ["Total de casos", str(len(context["results"]))],
        ["Tasa PASS sobre casos medidos", context["success"]],
    ])
    story.append(Table(metric_rows, colWidths=[3.5 * inch, 3.5 * inch], style=TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#081b38")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#cbd5e1")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ])))

    for result in context["results"]:
        story.extend([
            Spacer(1, 15),
            Paragraph(f'{escape(result["code"])} — {escape(result["name"])}', styles["Heading2"]),
        ])
        rows = [
            [para("Suite / categoría", "QaLabel"), para(result.get("suite") or result.get("category") or "No especificado")],
            [para("Estado", "QaLabel"), para(result.get("status"))],
            [para("Duración", "QaLabel"), para(f'{result["duration_ms"]} ms')],
            [para("Objetivo", "QaLabel"), para(result.get("objective_text"))],
            [para("Precondiciones", "QaLabel"), para(result.get("preconditions_text"))],
            [para("Datos de prueba", "QaLabel"), para(result.get("test_data_text"))],
            [para("Validaciones", "QaLabel"), para(result.get("validations_text"))],
            [para("Resultado esperado", "QaLabel"), para(result.get("expected_text"))],
            [para("Resultado obtenido", "QaLabel"), para(result.get("obtained_text"))],
            [para("Paso fallido", "QaLabel"), para(result.get("failure_step_text"))],
            [para("Error", "QaLabel"), para(result.get("error_text"))],
            [para("Observaciones", "QaLabel"), para(result.get("observations_text"))],
        ]
        detail_table = Table(rows, colWidths=[1.35 * inch, usable_width - 1.35 * inch], style=TableStyle([
            ("GRID", (0, 0), (-1, -1), 0.3, colors.HexColor("#d5dde8")),
            ("BACKGROUND", (0, 0), (0, -1), colors.HexColor("#f8fafc")),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
            ("TOPPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ]))
        story.append(detail_table)

        steps = result["steps"]
        if steps:
            story.extend([Spacer(1, 8), Paragraph("Pasos ejecutados", styles["Heading3"])])
            step_rows = [[
                para("#", "QaLabel"),
                para("Paso", "QaLabel"),
                para("Estado", "QaLabel"),
                para("Resultado obtenido", "QaLabel"),
                para("Error", "QaLabel"),
            ]]
            for index, step in enumerate(steps, start=1):
                step_rows.append([
                    para(str(index), "QaSmall"),
                    para(step.get("name") or step.get("description"), "QaSmall"),
                    para(step.get("status"), "QaSmall"),
                    para(step.get("obtained"), "QaSmall"),
                    para(step.get("error_message"), "QaSmall"),
                ])
            story.append(Table(step_rows, colWidths=[0.35 * inch, 1.35 * inch, 0.65 * inch, 2.8 * inch, 1.85 * inch], repeatRows=1, style=TableStyle([
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#e2e8f0")),
                ("GRID", (0, 0), (-1, -1), 0.3, colors.HexColor("#cbd5e1")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ])))

        if result.get("screenshot"):
            image_path = screenshots_dir / result["screenshot"]
            if image_path.is_file():
                image_width, image_height = ImageReader(str(image_path)).getSize()
                scale = min(usable_width / image_width, 6.5 * inch / image_height)
                story.extend([
                    Spacer(1, 8),
                    Paragraph("Evidencia capturada", styles["Heading3"]),
                    Image(str(image_path), width=image_width * scale, height=image_height * scale),
                ])

    def draw_page_number(canvas, document):
        canvas.saveState()
        canvas.setFont("Helvetica", 8)
        canvas.setFillColor(colors.HexColor("#64748b"))
        canvas.drawRightString(letter[0] - 0.75 * inch, 0.4 * inch, f"Página {document.page}")
        canvas.restoreState()

    document = SimpleDocTemplate(
        str(pdf_path),
        pagesize=letter,
        rightMargin=0.75 * inch,
        leftMargin=0.75 * inch,
        topMargin=0.65 * inch,
        bottomMargin=0.65 * inch,
        title=f'QA Report {context["execution_id"]}',
        author="IMPORTSYSTEMGO QA CENTER",
    )
    document.build(story, onFirstPage=draw_page_number, onLaterPages=draw_page_number)


def make_html(html_path, context):
    template = Environment(autoescape=select_autoescape(default=True)).from_string("""<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reporte QA {{ execution_id }}</title><style>
body{font-family:Arial,sans-serif;max-width:1100px;margin:32px auto;padding:0 20px;color:#172033}
h1,h2,h3{color:#081b38}.summary,.general{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.card,section{border:1px solid #dbe3ef;border-radius:8px;padding:14px;margin:14px 0}
table{width:100%;border-collapse:collapse;margin:10px 0 18px;table-layout:fixed}
th,td{border:1px solid #dbe3ef;padding:8px;text-align:left;vertical-align:top;overflow-wrap:anywhere}
th{background:#f1f5f9}.PASS{color:#198754}.FAIL{color:#dc3545}.BLOCKED{color:#a16207}.SKIPPED,.NOT-RUN{color:#6c757d}
pre{white-space:pre-wrap;overflow-wrap:anywhere;margin:0;font:inherit}
img{display:block;max-width:100%;height:auto;margin:12px auto;border:1px solid #dbe3ef}
@media print{body{max-width:none;margin:0}.card,section{break-inside:avoid}a{color:inherit}}
</style></head><body>
<h1>IMPORTSYSTEMGO QA CENTER</h1><h2>Reporte de ejecución automatizada</h2>
<section><h3>Datos generales</h3><div class="general">
<div class="card">Identificador<br><strong>{{ execution_id }}</strong></div>
<div class="card">Inicio<br><strong>{{ started_at }}</strong></div>
<div class="card">Finalización<br><strong>{{ finished_at }}</strong></div>
<div class="card">Duración<br><strong>{{ duration_ms }} ms</strong></div>
<div class="card">Ambiente<br><strong>{{ environment }}</strong></div>
<div class="card">URL QA<br><strong>{{ base_url }}</strong></div>
<div class="card">Suite<br><strong>{{ suite }}</strong></div>
<div class="card">Destinatario<br><strong>{{ recipient }}</strong></div>
</div></section>
<section><h3>Resumen</h3><div class="summary">
{% for status, count in counts.items() %}<div class="card">{{ status }} <strong>{{ count }}</strong></div>{% endfor %}
<div class="card">Total de casos <strong>{{ results|length }}</strong></div>
<div class="card">Tasa PASS sobre casos medidos <strong>{{ success }}</strong></div>
</div></section>
{% for r in results %}<section><h2>{{ r.code }} — {{ r.name }}</h2>
<table><tbody>
<tr><th>Suite / categoría</th><td>{{ r.suite or r.category or "No especificado" }}</td></tr>
<tr><th>Estado</th><td class="{{ r.status|replace(' ', '-') }}">{{ r.status }}</td></tr>
<tr><th>Duración</th><td>{{ r.duration_ms }} ms</td></tr>
<tr><th>Objetivo</th><td><pre>{{ r.objective_text }}</pre></td></tr>
<tr><th>Precondiciones</th><td><pre>{{ r.preconditions_text }}</pre></td></tr>
<tr><th>Datos de prueba</th><td><pre>{{ r.test_data_text }}</pre></td></tr>
<tr><th>Validaciones</th><td><pre>{{ r.validations_text }}</pre></td></tr>
<tr><th>Resultado esperado</th><td><pre>{{ r.expected_text }}</pre></td></tr>
<tr><th>Resultado obtenido</th><td><pre>{{ r.obtained_text }}</pre></td></tr>
<tr><th>Paso fallido</th><td><pre>{{ r.failure_step_text }}</pre></td></tr>
<tr><th>Error</th><td><pre>{{ r.error_text }}</pre></td></tr>
<tr><th>Observaciones</th><td><pre>{{ r.observations_text }}</pre></td></tr>
</tbody></table>
{% if r.steps %}<h3>Pasos ejecutados</h3><table><thead><tr><th>#</th><th>Paso</th><th>Estado</th><th>Resultado obtenido</th><th>Error</th></tr></thead><tbody>
{% for step in r.steps %}<tr><td>{{ loop.index }}</td><td>{{ step.name or step.description or "No especificado" }}</td><td>{{ step.status or "No especificado" }}</td><td>{{ step.obtained or "No especificado" }}</td><td>{{ step.error_message or "No especificado" }}</td></tr>{% endfor %}
</tbody></table>{% endif %}
{% if r.screenshot %}<h3>Evidencia capturada</h3><img src="../screenshots/{{ r.screenshot }}" alt="Captura del caso {{ r.code }}">{% endif %}
</section>{% endfor %}
</body></html>""")
    html_path.write_text(template.render(**context), encoding="utf-8")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--execution-dir", required=True)
    parser.add_argument("--execution-id", required=True)
    parser.add_argument("--recipient", required=True)
    parser.add_argument("--suite", default="Suite seleccionada")
    args = parser.parse_args()

    execution_dir = Path(args.execution_dir)
    screenshots_dir = execution_dir / "screenshots"
    report_dir = execution_dir / "reports"
    report_dir.mkdir(parents=True, exist_ok=True)

    result_payload = json.loads((execution_dir / "results.json").read_text(encoding="utf-8"))
    results = prepare_results(result_payload["results"], screenshots_dir)
    metadata = json.loads((execution_dir / "metadata.json").read_text(encoding="utf-8"))
    counts = {status: sum(result.get("status") == status for result in results) for status in STATUS_ORDER}
    measured = counts["PASS"] + counts["FAIL"]
    success = f'{counts["PASS"] / measured * 100:.1f}%' if measured else "N/A"
    duration_ms = int(metadata.get("duration_ms") or sum(result["duration_ms"] for result in results))
    context = {
        "execution_id": args.execution_id,
        "started_at": metadata.get("started_at", "No especificado"),
        "finished_at": metadata.get("finished_at", "No especificado"),
        "duration_ms": duration_ms,
        "environment": metadata.get("environment", "SQLite QA local"),
        "base_url": metadata.get("base_url", "No especificado"),
        "suite": args.suite,
        "recipient": args.recipient,
        "counts": counts,
        "success": success,
        "results": results,
    }

    pdf_path = report_dir / f"QA_Report_{args.execution_id}.pdf"
    html_path = report_dir / f"QA_Summary_{args.execution_id}.html"
    make_pdf(pdf_path, context, screenshots_dir)
    make_html(html_path, context)
    print(json.dumps({
        "pdf": str(pdf_path),
        "html": str(html_path),
        "pdf_sha256": sha256(pdf_path),
        "html_sha256": sha256(html_path),
    }, ensure_ascii=False))


if __name__ == "__main__":
    raise SystemExit(main())
