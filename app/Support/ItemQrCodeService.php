<?php

namespace App\Support;

use App\Models\Item;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class ItemQrCodeService
{
    public function pngForItem(Item $item, int $size = 360): string
    {
        $sku = trim((string) $item->sku);
        $qrSize = max(180, min($size, 720));
        $qrBinary = $this->rawPngForSku($sku, $qrSize);

        $qrImage = imagecreatefromstring($qrBinary);
        if ($qrImage === false) {
            throw new \RuntimeException('Gagal membuat gambar QR.');
        }

        $canvasWidth = $qrSize + 140;
        $canvasHeight = $qrSize + 230;
        $image = imagecreatetruecolor($canvasWidth, $canvasHeight);

        if ($image === false) {
            imagedestroy($qrImage);
            throw new \RuntimeException('Gagal menyiapkan canvas QR.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);
        imageantialias($image, true);

        $bg = imagecolorallocate($image, 241, 245, 249);
        $card = imagecolorallocate($image, 255, 255, 255);
        $shadow = imagecolorallocatealpha($image, 15, 23, 42, 110);
        $border = imagecolorallocate($image, 203, 213, 225);
        $panel = imagecolorallocate($image, 248, 250, 252);
        $label = imagecolorallocate($image, 100, 116, 139);
        $text = imagecolorallocate($image, 15, 23, 42);

        imagefilledrectangle($image, 0, 0, $canvasWidth, $canvasHeight, $bg);

        $cardX = 18;
        $cardY = 18;
        $cardWidth = $canvasWidth - 36;
        $cardHeight = $canvasHeight - 36;

        $this->drawRoundedRect($image, $cardX + 6, $cardY + 10, $cardWidth, $cardHeight, 28, $shadow);
        $this->drawRoundedRect($image, $cardX, $cardY, $cardWidth, $cardHeight, 28, $card);
        $this->drawRoundedRectOutline($image, $cardX, $cardY, $cardWidth, $cardHeight, 28, $border);

        $qrPanelX = (int) round(($canvasWidth - $qrSize) / 2);
        $qrPanelY = 46;
        $this->drawRoundedRect($image, $qrPanelX, $qrPanelY, $qrSize, $qrSize, 24, $panel);
        $this->drawRoundedRectOutline($image, $qrPanelX, $qrPanelY, $qrSize, $qrSize, 24, $border);

        imagecopy($image, $qrImage, $qrPanelX, $qrPanelY, 0, 0, imagesx($qrImage), imagesy($qrImage));
        imagedestroy($qrImage);

        imageline($image, 54, $qrPanelY + $qrSize + 28, $canvasWidth - 54, $qrPanelY + $qrSize + 28, $border);

        $regularFont = $this->resolveFontPath(false);
        $boldFont = $this->resolveFontPath(true);

        if ($regularFont !== null && $boldFont !== null && function_exists('imagettftext')) {
            $this->drawCenteredTtfText($image, 'SKU', $regularFont, 13, 0, (int) round($canvasWidth / 2), $qrPanelY + $qrSize + 56, $label, 3);

            $fontSize = $this->fitFontSize($sku, $boldFont, 24, $cardWidth - 72);
            $this->drawCenteredTtfText($image, $sku, $boldFont, $fontSize, 0, (int) round($canvasWidth / 2), $qrPanelY + $qrSize + 98, $text);
        } else {
            imagestring($image, 3, (int) round(($canvasWidth - (strlen('SKU') * imagefontwidth(3))) / 2), $qrPanelY + $qrSize + 42, 'SKU', $label);
            imagestring($image, 5, (int) round(($canvasWidth - (strlen($sku) * imagefontwidth(5))) / 2), $qrPanelY + $qrSize + 70, $sku, $text);
        }

        ob_start();
        imagepng($image, null, 9);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    public function rawPngForItem(Item $item, int $size = 360): string
    {
        return $this->rawPngForSku((string) $item->sku, $size);
    }

    public function rawPngForSku(string $sku, int $size = 360): string
    {
        $value = trim($sku);
        $qrSize = max(180, min($size, 720));

        return (new Writer(
            new GDLibRenderer(
                $qrSize,
                2,
                'png',
                9,
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))
            )
        ))->writeString($value);
    }

    public function downloadFilename(Item $item): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim((string) $item->sku)) ?? '';
        $slug = trim(Str::lower($slug), '-');
        $slug = $slug !== '' ? $slug : 'item-'.$item->id;

        return 'qr-item-'.$slug.'.png';
    }

    private function resolveFontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\segoeuib.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
            ]
            : [
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\segoeui.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
            ];

        foreach ($candidates as $path) {
            if (@is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function fitFontSize(string $text, string $font, int $startSize, int $maxWidth): int
    {
        for ($size = $startSize; $size >= 14; $size--) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box === false) {
                return $startSize;
            }

            $width = abs($box[2] - $box[0]);
            if ($width <= $maxWidth) {
                return $size;
            }
        }

        return 14;
    }

    private function drawCenteredTtfText($image, string $text, string $font, int $size, int $angle, int $centerX, int $baselineY, int $color, int $letterSpacing = 0): void
    {
        if ($letterSpacing <= 0) {
            $box = imagettfbbox($size, $angle, $font, $text);
            if ($box === false) {
                return;
            }

            $width = abs($box[2] - $box[0]);
            $x = (int) round($centerX - ($width / 2));
            imagettftext($image, $size, $angle, $x, $baselineY, $color, $font, $text);
            return;
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalWidth = 0;
        $boxes = [];

        foreach ($chars as $char) {
            $box = imagettfbbox($size, $angle, $font, $char);
            if ($box === false) {
                return;
            }
            $width = abs($box[2] - $box[0]);
            $boxes[] = ['char' => $char, 'width' => $width];
            $totalWidth += $width;
        }

        if (count($boxes) > 1) {
            $totalWidth += $letterSpacing * (count($boxes) - 1);
        }

        $x = (int) round($centerX - ($totalWidth / 2));
        foreach ($boxes as $entry) {
            imagettftext($image, $size, $angle, $x, $baselineY, $color, $font, $entry['char']);
            $x += $entry['width'] + $letterSpacing;
        }
    }

    private function drawRoundedRect($image, int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);
        imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
    }

    private function drawRoundedRectOutline($image, int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imageline($image, $x + $radius, $y, $x + $width - $radius, $y, $color);
        imageline($image, $x + $radius, $y + $height, $x + $width - $radius, $y + $height, $color);
        imageline($image, $x, $y + $radius, $x, $y + $height - $radius, $color);
        imageline($image, $x + $width, $y + $radius, $x + $width, $y + $height - $radius, $color);
        imagearc($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, 0, 90, $color);
    }
}
