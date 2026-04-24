<?php

namespace App\Support;

class SimpleBarcodeService
{
    private const CODE128_PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const CODE128_START_B = 104;
    private const CODE128_STOP = 106;

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
        $unitWidth = max(1, $barcodeWidth / max(1, $totalUnits));
        $cursorX = (float) (($canvasWidth - ($totalUnits * $unitWidth)) / 2);

        foreach ($segments as $segment) {
            $segmentWidth = $segment['units'] * $unitWidth;
            if ($segment['bar']) {
                imagefilledrectangle(
                    $image,
                    (int) round($cursorX),
                    $quietZoneY,
                    max((int) round($cursorX), (int) round($cursorX + $segmentWidth) - 1),
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
        $characters = str_split($value);
        $codes = [self::CODE128_START_B];

        foreach ($characters as $character) {
            $ascii = ord($character);
            $codes[] = $ascii - 32;
        }

        $checksum = self::CODE128_START_B;
        foreach (array_slice($codes, 1) as $index => $code) {
            $checksum += $code * ($index + 1);
        }
        $codes[] = $checksum % 103;
        $codes[] = self::CODE128_STOP;

        $segments = [];

        foreach ($codes as $code) {
            $pattern = self::CODE128_PATTERNS[$code] ?? null;
            if ($pattern === null) {
                throw new \RuntimeException('Pattern barcode tidak didukung: '.$code);
            }

            for ($i = 0; $i < strlen($pattern); $i++) {
                $segments[] = [
                    'bar' => $i % 2 === 0,
                    'units' => (int) $pattern[$i],
                ];
            }
        }

        return $segments;
    }

    private function normalize(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new \RuntimeException('Barcode tidak boleh kosong.');
        }

        if (preg_match('/[^\x20-\x7E]/', $normalized)) {
            throw new \RuntimeException('Barcode mengandung karakter yang tidak didukung.');
        }

        return $normalized;
    }
}
