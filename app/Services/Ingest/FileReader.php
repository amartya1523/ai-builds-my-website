<?php

namespace App\Services\Ingest;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/** Reads uploaded files into plain text. Photos are kept for the gallery, not read. */
class FileReader
{
    /** @return array{status:string, note:?string, text:?string} */
    public function read(string $path, string $mime, string $originalName): array
    {
        $full = Storage::disk('public')->path($path);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        try {
            if (str_starts_with($mime, 'image/')) {
                return ['status' => 'ok', 'note' => 'Photo saved for the gallery.', 'text' => null];
            }

            if ($mime === 'application/pdf' || $ext === 'pdf') {
                $text = (new PdfParser())->parseFile($full)->getText();
                $text = trim(preg_replace("/[ \t]+/", ' ', $text));
                if ($text === '') {
                    return ['status' => 'failed', 'note' => 'This PDF has no readable text (it may be a scan). Please type the key details as a note.', 'text' => null];
                }

                return ['status' => 'ok', 'note' => null, 'text' => $text];
            }

            if (in_array($ext, ['csv', 'tsv'], true)) {
                return ['status' => 'ok', 'note' => null, 'text' => $this->table($full, $ext === 'tsv' ? "\t" : ',')];
            }

            if (in_array($ext, ['txt', 'md'], true) || str_starts_with($mime, 'text/plain')) {
                return ['status' => 'ok', 'note' => null, 'text' => trim(file_get_contents($full))];
            }

            if (in_array($ext, ['html', 'htm'], true)) {
                $x = (new HtmlExtractor())->extract(file_get_contents($full));

                return ['status' => 'ok', 'note' => null, 'text' => trim(($x['hidden_text'] ? $x['hidden_text']."\n" : '').$x['text'])];
            }
        } catch (Throwable $e) {
            return ['status' => 'failed', 'note' => 'Could not read this file: '.$e->getMessage(), 'text' => null];
        }

        return ['status' => 'skipped', 'note' => 'File type not supported yet (use PDF, CSV, TXT or images).', 'text' => null];
    }

    private function table(string $path, string $sep): string
    {
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($h, 0, $sep, '"', '\\')) !== false) {
                $rows[] = implode(' | ', array_map('trim', $row));
            }
            fclose($h);
        }

        return implode("\n", $rows);
    }
}
