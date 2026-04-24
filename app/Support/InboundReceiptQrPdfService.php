<?php

namespace App\Support;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InboundReceiptQrPdfService
{
    private const PAGE_WIDTH = 1181;
    private const PAGE_HEIGHT = 1772;
    private const PAGE_WIDTH_PT = 283.46;
    private const PAGE_HEIGHT_PT = 425.20;
    private const OUTER_MARGIN = 40;
    private const SKU_LINE_GAP = 16;
    private const NAME_LINE_GAP = 10;

    public function __construct(
        private readonly ItemQrCodeService $itemQrCodeService,
        private readonly SimpleBarcodeService $barcodeService,
        private readonly SimpleImagePdfBuilder $pdfBuilder,
    ) {
    }

    public function downloadFilename(InboundTransaction $transaction): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim((string) $transaction->code)) ?? '';
        $slug = trim(Str::lower($slug), '-');
        $slug = $slug !== '' ? $slug : 'penerimaan-'.$transaction->id;

        return 'qr-penerimaan-'.$slug.'.pdf';
    }

    public function pdfForTransaction(InboundTransaction $transaction): string
    {
        $items = $this->validItems($transaction);
        if ($items->isEmpty()) {
            throw new \RuntimeException('Penerimaan barang tidak memiliki SKU yang bisa dibuat QR.');
        }

        $pages = [];
        foreach ($items->values() as $itemRow) {
            $pages[] = $this->renderPage($transaction, $itemRow);
        }

        return $this->pdfBuilder->buildFromJpegs($pages, self::PAGE_WIDTH_PT, self::PAGE_HEIGHT_PT);
    }

    /**
     * @return Collection<int,InboundItem>
     */
    private function validItems(InboundTransaction $transaction): Collection
    {
        return $transaction->items
            ->filter(fn (InboundItem $row) => $row->item !== null && trim((string) $row->item->sku) !== '')
            ->values();
    }

    /**
     */
    private function renderPage(InboundTransaction $transaction, InboundItem $row): string
    {
        $image = imagecreatetruecolor(self::PAGE_WIDTH, self::PAGE_HEIGHT);
        if ($image === false) {
            throw new \RuntimeException('Gagal menyiapkan halaman QR penerimaan.');
        }

        imageantialias($image, true);
        imagealphablending($image, true);
        imagesavealpha($image, false);

        $bg = imagecolorallocate($image, 255, 255, 255);
        $card = imagecolorallocate($image, 255, 255, 255);
        $panel = imagecolorallocate($image, 248, 250, 252);
        $line = imagecolorallocate($image, 226, 232, 240);
        $text = imagecolorallocate($image, 15, 23, 42);
        $muted = imagecolorallocate($image, 71, 85, 105);
        $soft = imagecolorallocate($image, 148, 163, 184);
        $accent = imagecolorallocate($image, 30, 41, 59);

        imagefilledrectangle($image, 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, $bg);
        $boldFont = $this->resolveFontPath(true);
        $regularFont = $this->resolveFontPath(false);

        $item = $row->item;
        if ($item === null) {
            imagedestroy($image);
            throw new \RuntimeException('Item penerimaan tidak ditemukan untuk QR.');
        }

        $sku = trim((string) $item->sku);
        $name = trim((string) $item->name);

        $sheetX = self::OUTER_MARGIN;
        $sheetY = self::OUTER_MARGIN;
        $sheetWidth = self::PAGE_WIDTH - (self::OUTER_MARGIN * 2);
        $sheetHeight = self::PAGE_HEIGHT - (self::OUTER_MARGIN * 2);

        imagefilledrectangle($image, $sheetX, $sheetY, $sheetX + $sheetWidth, $sheetY + $sheetHeight, $card);
        imagerectangle($image, $sheetX, $sheetY, $sheetX + $sheetWidth, $sheetY + $sheetHeight, $line);

        $this->drawCenteredText(
            $image,
            'QR SKU PENERIMAAN',
            (int) floor(self::PAGE_WIDTH / 2),
            $sheetY + 38,
            24,
            $soft,
            true,
            $boldFont
        );

        $qrPanelX = $sheetX + 54;
        $qrPanelY = $sheetY + 72;
        $qrPanelWidth = $sheetWidth - 108;
        $qrPanelHeight = 680;
        imagefilledrectangle($image, $qrPanelX, $qrPanelY, $qrPanelX + $qrPanelWidth, $qrPanelY + $qrPanelHeight, $panel);
        imagerectangle($image, $qrPanelX, $qrPanelY, $qrPanelX + $qrPanelWidth, $qrPanelY + $qrPanelHeight, $line);

        $qrBinary = $this->itemQrCodeService->rawPngForItem($item, 520);
        $qrImage = imagecreatefromstring($qrBinary);
        if ($qrImage !== false) {
            $sourceWidth = imagesx($qrImage);
            $sourceHeight = imagesy($qrImage);
            $targetMaxWidth = $qrPanelWidth - 180;
            $targetMaxHeight = $qrPanelHeight - 170;
            $scale = min(
                $targetMaxWidth / max(1, $sourceWidth),
                $targetMaxHeight / max(1, $sourceHeight)
            );
            $targetWidth = (int) max(1, floor($sourceWidth * $scale));
            $targetHeight = (int) max(1, floor($sourceHeight * $scale));
            $targetX = $qrPanelX + (int) floor(($qrPanelWidth - $targetWidth) / 2);
            $targetY = $qrPanelY + (int) floor(($qrPanelHeight - $targetHeight) / 2);

            imagecopyresampled(
                $image,
                $qrImage,
                $targetX,
                $targetY,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );
            imagedestroy($qrImage);
        }

        $this->drawCenteredText(
            $image,
            'SCAN UNTUK SKU',
            (int) floor(self::PAGE_WIDTH / 2),
            $qrPanelY + $qrPanelHeight - 28,
            20,
            $muted,
            true,
            $boldFont
        );

        $skuPanelTop = $qrPanelY + $qrPanelHeight + 26;
        $skuPanelHeight = 430;
        imagefilledrectangle(
            $image,
            $sheetX + 48,
            $skuPanelTop,
            $sheetX + $sheetWidth - 48,
            $skuPanelTop + $skuPanelHeight,
            $panel
        );
        imagerectangle(
            $image,
            $sheetX + 48,
            $skuPanelTop,
            $sheetX + $sheetWidth - 48,
            $skuPanelTop + $skuPanelHeight,
            $line
        );

        $this->drawCenteredText(
            $image,
            'SKU',
            (int) floor(self::PAGE_WIDTH / 2),
            $skuPanelTop + 54,
            28,
            $soft,
            true,
            $boldFont
        );

        $skuLines = $this->splitTextForLines($sku, 2);
        $skuFontSize = $this->fitWrappedFontSize(
            $skuLines,
            $boldFont,
            158,
            $sheetWidth - 110,
            60
        );
        $skuLineHeight = $skuFontSize + self::SKU_LINE_GAP;
        $skuBlockTop = $skuPanelTop + 150;
        $this->drawCenteredLines(
            $image,
            $skuLines,
            (int) floor(self::PAGE_WIDTH / 2),
            $skuBlockTop,
            $skuFontSize,
            $accent,
            true,
            $boldFont,
            $skuLineHeight
        );

        $nameText = $name !== '' ? $name : '-';
        $nameLines = $this->wrapTextByWidth(
            Str::limit($nameText, 82),
            $regularFont,
            34,
            $sheetWidth - 180,
            2
        );
        $nameFontSize = $this->fitWrappedFontSize(
            $nameLines,
            $regularFont,
            32,
            $sheetWidth - 180,
            18
        );
        $nameLineHeight = $nameFontSize + self::NAME_LINE_GAP;
        $nameTop = $skuPanelTop + $skuPanelHeight - 92;
        $this->drawCenteredLines(
            $image,
            $nameLines,
            (int) floor(self::PAGE_WIDTH / 2),
            $nameTop,
            $nameFontSize,
            $muted,
            false,
            $regularFont,
            $nameLineHeight
        );

        $dividerY = max(
            $skuPanelTop + $skuPanelHeight + 18,
            $sheetY + $sheetHeight - 330
        );
        imageline($image, $sheetX + 48, $dividerY, $sheetX + $sheetWidth - 48, $dividerY, $line);

        $this->drawCenteredText(
            $image,
            'KODE INBOUND',
            (int) floor(self::PAGE_WIDTH / 2),
            $dividerY + 40,
            20,
            $soft,
            true,
            $boldFont
        );

        $this->drawCenteredText(
            $image,
            $transaction->transacted_at?->format('m.y') ?? '-',
            (int) floor(self::PAGE_WIDTH / 2),
            $dividerY + 86,
            34,
            $text,
            true,
            $boldFont
        );

        $barcodePanelWidth = 780;
        $barcodePanelHeight = 154;
        $barcodePanelX = (int) floor((self::PAGE_WIDTH - $barcodePanelWidth) / 2);
        $barcodePanelY = $dividerY + 114;
        imagefilledrectangle(
            $image,
            $barcodePanelX,
            $barcodePanelY,
            $barcodePanelX + $barcodePanelWidth,
            $barcodePanelY + $barcodePanelHeight,
            $panel
        );
        imagerectangle(
            $image,
            $barcodePanelX,
            $barcodePanelY,
            $barcodePanelX + $barcodePanelWidth,
            $barcodePanelY + $barcodePanelHeight,
            $line
        );

        $barcodeBinary = $this->barcodeService->pngForValue((string) $transaction->code, 700, 90);
        $barcodeImage = imagecreatefromstring($barcodeBinary);
        if ($barcodeImage !== false) {
            $sourceWidth = imagesx($barcodeImage);
            $sourceHeight = imagesy($barcodeImage);
            $targetWidth = 700;
            $targetHeight = 90;
            $targetX = $barcodePanelX + (int) floor(($barcodePanelWidth - $targetWidth) / 2);
            $targetY = $barcodePanelY + (int) floor(($barcodePanelHeight - $targetHeight) / 2);

            imagecopyresampled(
                $image,
                $barcodeImage,
                $targetX,
                $targetY,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );
            imagedestroy($barcodeImage);
        }

        $inboundCodeLines = $this->wrapTextByWidth(
            (string) $transaction->code,
            $boldFont,
            28,
            $sheetWidth - 150,
            2
        );
        $this->drawCenteredLines(
            $image,
            $inboundCodeLines,
            (int) floor(self::PAGE_WIDTH / 2),
            $barcodePanelY + $barcodePanelHeight + 48,
            28,
            $accent,
            true,
            $boldFont,
            34
        );

        ob_start();
        imagejpeg($image, null, 96);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
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

    private function drawText($image, string $text, int $x, int $y, int $size, int $color, bool $bold, ?string $font): void
    {
        if ($font !== null && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }

        $fontIndex = $bold ? 5 : 4;
        imagestring($image, $fontIndex, $x, max(0, $y - 18), $text, $color);
    }

    private function drawCenteredText($image, string $text, int $centerX, int $y, int $size, int $color, bool $bold, ?string $font): void
    {
        if ($font !== null && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box !== false) {
                $width = abs($box[2] - $box[0]);
                $x = (int) round($centerX - ($width / 2));
                $this->drawText($image, $text, $x, $y, $size, $color, $bold, $font);
                return;
            }
        }

        $fontIndex = $bold ? 5 : 4;
        $width = strlen($text) * imagefontwidth($fontIndex);
        imagestring($image, $fontIndex, max(0, (int) round($centerX - ($width / 2))), max(0, $y - 18), $text, $color);
    }

    private function drawCenteredLines($image, array $lines, int $centerX, int $topY, int $size, int $color, bool $bold, ?string $font, int $lineHeight): void
    {
        $currentY = $topY;
        foreach ($lines as $line) {
            $this->drawCenteredText($image, $line, $centerX, $currentY, $size, $color, $bold, $font);
            $currentY += $lineHeight;
        }
    }

    private function fitFontSize(string $text, ?string $font, int $startSize, int $maxWidth, int $minSize): int
    {
        if ($font === null || !function_exists('imagettfbbox')) {
            return $startSize;
        }

        for ($size = $startSize; $size >= $minSize; $size--) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box === false) {
                return $startSize;
            }

            $width = abs($box[2] - $box[0]);
            if ($width <= $maxWidth) {
                return $size;
            }
        }

        return $minSize;
    }

    private function fitWrappedFontSize(array $lines, ?string $font, int $startSize, int $maxWidth, int $minSize): int
    {
        if ($font === null || !function_exists('imagettfbbox')) {
            return $startSize;
        }

        for ($size = $startSize; $size >= $minSize; $size--) {
            $fits = true;
            foreach ($lines as $line) {
                $box = imagettfbbox($size, 0, $font, $line);
                if ($box === false) {
                    return $startSize;
                }

                $width = abs($box[2] - $box[0]);
                if ($width > $maxWidth) {
                    $fits = false;
                    break;
                }
            }

            if ($fits) {
                return $size;
            }
        }

        return $minSize;
    }

    private function splitTextForLines(string $text, int $maxLines): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['-'];
        }

        if (strlen($text) <= 16 || $maxLines <= 1) {
            return [$text];
        }

        $length = strlen($text);
        $chunkSize = (int) ceil($length / $maxLines);
        $parts = str_split($text, max(1, $chunkSize));

        return array_slice($parts, 0, $maxLines);
    }

    private function wrapTextByWidth(string $text, ?string $font, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['-'];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        if ($font === null || !function_exists('imagettfbbox') || count($words) <= 1) {
            return [Str::limit($text, 48)];
        }

        $lines = [];
        $current = '';
        $index = 0;
        $wordCount = count($words);

        while ($index < $wordCount) {
            $word = $words[$index];
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $width = $box !== false ? abs($box[2] - $box[0]) : 0;

            if ($current !== '' && $width > $maxWidth) {
                $lines[] = $current;
                $current = $word;
                if (count($lines) === $maxLines - 1) {
                    $index++;
                    break;
                }
            } else {
                $current = $candidate;
                $index++;
            }
        }

        if ($current !== '') {
            $remainingText = trim($current.' '.implode(' ', array_slice($words, $index)));
            if (count($lines) === $maxLines - 1) {
                $current = Str::limit($remainingText, 42);
            }
            $lines[] = $current;
        }

        return array_slice($lines, 0, $maxLines);
    }
}
