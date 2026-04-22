<?php

namespace App\Support;

class SimpleBarcodeService
{
    private const CODE39_PATTERNS = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    private const NARROW_UNIT = 1;
    private const WIDE_UNIT = 3;

    public function pngForValue(string $value, int $width = 560, int $height = 120): string
    {
        $normalized = $this->normalize($value);
        $segments = $this->segmentsForValue($normalized);

        $canvasWidth = max(240, $width);
        $canvasHeight = max(72, $height);
        $image = imagecreatetruecolor($canvasWidth, $canvasHeight);

        if ($image === false) {
            throw new \RuntimeException('Gagal menyiapkan gambar barcode.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);
        imagefilledrectangle($image, 0, 0, $canvasWidth, $canvasHeight, $white);

        $quietZoneX = (int) max(16, floor($canvasWidth * 0.05));
        $quietZoneY = (int) max(10, floor($canvasHeight * 0.15));
        $barcodeHeight = max(1, $canvasHeight - ($quietZoneY * 2));
        $barcodeWidth = max(1, $canvasWidth - ($quietZoneX * 2));
        $totalUnits = array_sum(array_column($segments, 'units'));
        $unitWidth = max(1, (int) floor($barcodeWidth / max(1, $totalUnits)));
        $actualWidth = $totalUnits * $unitWidth;
        $cursorX = (int) floor(($canvasWidth - $actualWidth) / 2);

        foreach ($segments as $segment) {
            $segmentWidth = $segment['units'] * $unitWidth;
            if ($segment['bar']) {
                imagefilledrectangle(
                    $image,
                    $cursorX,
                    $quietZoneY,
                    $cursorX + $segmentWidth - 1,
                    $quietZoneY + $barcodeHeight - 1,
                    $black
                );
            }

            $cursorX += $segmentWidth;
        }

        ob_start();
        imagepng($image, null, 9);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    /**
     * @return array<int,array{bar:bool,units:int}>
     */
    private function segmentsForValue(string $value): array
    {
        $characters = str_split('*'.$value.'*');
        $segments = [];

        foreach ($characters as $index => $character) {
            $pattern = self::CODE39_PATTERNS[$character] ?? null;
            if ($pattern === null) {
                throw new \RuntimeException('Karakter barcode tidak didukung: '.$character);
            }

            for ($i = 0; $i < strlen($pattern); $i++) {
                $segments[] = [
                    'bar' => $i % 2 === 0,
                    'units' => $pattern[$i] === 'w' ? self::WIDE_UNIT : self::NARROW_UNIT,
                ];
            }

            if ($index < count($characters) - 1) {
                $segments[] = ['bar' => false, 'units' => self::NARROW_UNIT];
            }
        }

        return $segments;
    }

    private function normalize(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            throw new \RuntimeException('Barcode tidak boleh kosong.');
        }

        if (preg_match('/[^0-9A-Z. \-\$\/\+%]/', $normalized)) {
            throw new \RuntimeException('Barcode mengandung karakter yang tidak didukung.');
        }

        return $normalized;
    }
}
