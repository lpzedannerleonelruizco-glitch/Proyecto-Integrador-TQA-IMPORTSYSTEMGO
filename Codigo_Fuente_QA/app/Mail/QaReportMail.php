<?php

namespace App\Mail;

use App\Models\QaExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class QaReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly QaExecution $execution) {}

    public function envelope(): Envelope
    {
        $suite = $this->execution->suite?->name ?: $this->execution->type;

        return new Envelope(
            subject: 'IMPORTSYSTEMGO QA CENTER – Reporte de automatización – '.$suite.' – '.now()->format('Y-m-d'),
        );
    }

    public function content(): Content
    {
        $pass = $this->execution->results()->where('status', 'PASS')->count();
        $fail = $this->execution->results()->where('status', 'FAIL')->count();
        $skipped = $this->execution->results()->where('status', 'SKIPPED')->count();
        $blocked = $this->execution->results()->where('status', 'BLOCKED')->count();
        $notRun = $this->execution->results()->where('status', 'NOT RUN')->count();

        return new Content(
            view: 'emails.qa-report',
            with: [
                'execution' => $this->execution,
                'pass' => $pass,
                'fail' => $fail,
                'skipped' => $skipped,
                'blocked' => $blocked,
                'notRun' => $notRun,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];
        $reports = $this->execution->reports()->get()->keyBy('format');
        foreach (['PDF', 'HTML'] as $format) {
            $report = $reports->get($format);
            if (! $report) {
                throw new RuntimeException('qa_report_attachment_missing');
            }

            $path = storage_path('app/private/'.$report->relative_path);
            if (! is_file($path) || ! hash_equals($report->sha256, (string) hash_file('sha256', $path))) {
                throw new RuntimeException('qa_report_attachment_integrity_failed');
            }

            $attachments[] = Attachment::fromPath($path)->withMime($format === 'PDF' ? 'application/pdf' : 'text/html');
        }

        return $attachments;
    }
}
