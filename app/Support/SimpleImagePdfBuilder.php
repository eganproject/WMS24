<?php

namespace App\Support;

class SimpleImagePdfBuilder
{
    /**
     * @param  array<int,string>  $jpegPages
     */
    public function buildFromJpegs(array $jpegPages, float $pageWidthPt = 595.28, float $pageHeightPt = 841.89): string
    {
        if ($jpegPages === []) {
            throw new \RuntimeException('Tidak ada halaman PDF yang bisa dibuat.');
        }

        $objects = [];
        $catalogId = 1;
        $pagesId = 2;
        $nextId = 3;
        $pageIds = [];

        foreach (array_values($jpegPages) as $index => $jpegBinary) {
            $size = @getimagesizefromstring($jpegBinary);
            if ($size === false || (int) ($size[0] ?? 0) <= 0 || (int) ($size[1] ?? 0) <= 0) {
                throw new \RuntimeException('Gagal membaca gambar halaman PDF.');
            }

            $imageWidth = (int) $size[0];
            $imageHeight = (int) $size[1];
            $imageName = 'Im'.($index + 1);

            $imageId = $nextId++;
            $contentId = $nextId++;
            $pageId = $nextId++;

            $objects[$imageId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $imageWidth,
                $imageHeight,
                strlen($jpegBinary),
                $jpegBinary
            );

            $content = sprintf(
                "q\n%.2F 0 0 %.2F 0 0 cm\n/%s Do\nQ",
                $pageWidthPt,
                $pageHeightPt,
                $imageName
            );

            $objects[$contentId] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($content),
                $content
            );

            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /%s %d 0 R >> >> /Contents %d 0 R >>",
                $pagesId,
                $pageWidthPt,
                $pageHeightPt,
                $imageName,
                $imageId,
                $contentId
            );

            $pageIds[] = $pageId;
        }

        $objects[$pagesId] = sprintf(
            "<< /Type /Pages /Count %d /Kids [%s] >>",
            count($pageIds),
            implode(' ', array_map(fn (int $id) => sprintf('%d 0 R', $id), $pageIds))
        );

        $objects[$catalogId] = sprintf(
            "<< /Type /Catalog /Pages %d 0 R >>",
            $pagesId
        );

        ksort($objects);
        $maxId = max(array_keys($objects));
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        for ($id = 1; $id <= $maxId; $id++) {
            $content = $objects[$id] ?? '<<>>';
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $content);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", $maxId + 1);
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF",
            $maxId + 1,
            $catalogId,
            $xrefOffset
        );

        return $pdf;
    }
}
