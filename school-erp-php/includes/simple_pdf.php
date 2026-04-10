<?php

class SimplePdfDocument {
    private $pages = [];
    private $currentPage = -1;
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;

    public function addPage() {
        $this->pages[] = [];
        $this->currentPage = count($this->pages) - 1;
    }

    public function text($xMm, $yMm, $text, $size = 12, $bold = false) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $font = $bold ? 'F2' : 'F1';
        $x = $this->mmToPt($xMm);
        $y = $this->pageHeight - $this->mmToPt($yMm);
        $safeText = $this->escapeText($text);

        $this->pages[$this->currentPage][] = sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET",
            $font,
            $size,
            $x,
            $y,
            $safeText
        );
    }

    public function line($x1Mm, $y1Mm, $x2Mm, $y2Mm) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $x1 = $this->mmToPt($x1Mm);
        $y1 = $this->pageHeight - $this->mmToPt($y1Mm);
        $x2 = $this->mmToPt($x2Mm);
        $y2 = $this->pageHeight - $this->mmToPt($y2Mm);

        $this->pages[$this->currentPage][] = sprintf(
            "%.2F %.2F m %.2F %.2F l S",
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    public function writeBlock($xMm, $yMm, array $lines, $size = 11, $lineHeightMm = 5, $bold = false) {
        $currentY = $yMm;
        foreach ($lines as $line) {
            $this->text($xMm, $currentY, $line, $size, $bold);
            $currentY += $lineHeightMm;
        }
    }

    public function outputString() {
        if (empty($this->pages)) {
            $this->addPage();
        }

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pageRefs = [];
        $nextObject = 5;

        foreach ($this->pages as $pageOps) {
            $content = implode("\n", $pageOps) . "\n";
            $contentObject = $nextObject++;
            $pageObject = $nextObject++;

            $objects[$contentObject] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $objects[$pageObject] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentObject
            );

            $pageRefs[] = $pageObject . " 0 R";
        }

        $objects[2] = "<< /Type /Pages /Count " . count($pageRefs) . " /Kids [" . implode(' ', $pageRefs) . "] >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer << /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefStart . "\n%%EOF";

        return $pdf;
    }

    private function mmToPt($mm) {
        return ((float)$mm) * 72 / 25.4;
    }

    private function escapeText($text) {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', (string)$text);
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return str_replace("\r", '', $text);
    }
}
