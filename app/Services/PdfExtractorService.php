<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Exception;

class PdfExtractorService
{
    public function extract(string $filePath): string{
        $fullPath = storage_path('app/private/' . $filePath);

        if(!file_exists($fullPath)){
            throw new Exception("Archivo no encontrado: {$fullPath}");
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        return match($extension){
            'pdf' => $this->extractFromPdf($fullPath),
            'txt' => $this->extractFromTxt($fullPath),
            default => throw new Exception("Formato del archivo no soportado: {$extension}"),
        };
    }

    private function extractFromPdf(string $fullPath): string{
        $parser = new Parser();
        $pdf = $parser->parseFile($fullPath);
        $text = $pdf->getText();

        if(empty(trim($text))){
            throw new Exception("No se pudo encontrar texto del PDF, puede tratarse de un PDF escaneado o una imagen");
        }

        return $this->cleanText($text);
    }

    private function extractFromTxt(string $fullPath): string{
        
        $text = file_get_contents($fullPath);

        if ($text === false) {
            throw new Exception("No se pudo leer el archivo de texto.");
        }

        return $this->cleanText($text);
    }

    private function cleanText(string $text):string {

    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }

    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);

    return trim($text);
    }
}


