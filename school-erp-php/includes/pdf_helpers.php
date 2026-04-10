<?php

require_once __DIR__ . '/simple_pdf.php';

function pdf_output_sections($filename, $title, array $sections, $subtitle = '')
{
    $document = new SimplePdfDocument();
    $document->addPage();

    $y = 18;
    $document->text(15, $y, $title, 18, true);
    $y += 7;

    if ($subtitle !== '') {
        foreach (pdf_wrap_lines($subtitle, 95) as $line) {
            $document->text(15, $y, $line, 10);
            $y += 5;
        }
    }

    $document->line(15, $y, 195, $y);
    $y += 8;

    foreach ($sections as $section) {
        $heading = trim((string) ($section['heading'] ?? ''));
        $lines = $section['lines'] ?? [];

        if ($heading !== '') {
            if ($y > 270) {
                $document->addPage();
                $y = 18;
            }
            $document->text(15, $y, $heading, 13, true);
            $y += 6;
        }

        foreach ($lines as $line) {
            $wrapped = pdf_wrap_lines((string) $line, 98);
            foreach ($wrapped as $wrappedLine) {
                if ($y > 280) {
                    $document->addPage();
                    $y = 18;
                }
                $document->text(15, $y, $wrappedLine, 10);
                $y += 5;
            }
        }

        $y += 3;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $document->outputString();
    exit;
}

function pdf_wrap_lines($text, $width = 95)
{
    $wrapped = wordwrap((string) $text, $width, "\n", true);
    return explode("\n", $wrapped);
}

function pdf_table_section($heading, array $headers, array $rows)
{
    $lines = [];
    $lines[] = implode(' | ', $headers);
    $lines[] = str_repeat('-', min(110, max(20, strlen($lines[0]))));

    foreach ($rows as $row) {
        $values = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $values[] = trim((string) $value);
        }
        $lines[] = implode(' | ', $values);
    }

    if (count($rows) === 0) {
        $lines[] = 'No records available.';
    }

    return [
        'heading' => $heading,
        'lines' => $lines,
    ];
}

function pdf_money($value)
{
    return 'Rs ' . number_format((float) $value, 2);
}
