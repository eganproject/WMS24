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

        $qrPanelX = $sheetX + 54;
        $qrPanelY = $sheetY + 56;
        $qrPanelWidth = $sheetWidth - 108;
        $qrPanelHeight = 880;
        imagefilledrectangle($image, $qrPanelX, $qrPanelY, $qrPanelX + $qrPanelWidth, $qrPanelY + $qrPanelHeight, $panel);
        imagerectangle($image, $qrPanelX, $qrPanelY, $qrPanelX + $qrPanelWidth, $qrPanelY + $qrPanelHeight, $line);

        $qrBinary = $this->itemQrCodeService->rawPngForItem($item, 560);
        $qrImage = imagecreatefromstring($qrBinary);
        if ($qrImage !== false) {
            $sourceWidth = imagesx($qrImage);
            $sourceHeight = imagesy($qrImage);
            $targetMaxWidth = $qrPanelWidth - 100;
            $targetMaxHeight = $qrPanelHeight - 100;
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

        $skuBlockTop = $qrPanelY + $qrPanelHeight + 72;
        $skuFontSize = $this->fitFontSize($sku, $boldFont, 68, $sheetWidth - 120, 30);
        $this->drawCenteredText(
            $image,
            $sku,
            (int) floor(self::PAGE_WIDTH / 2),
            $skuBlockTop,
            $skuFontSize,
            $accent,
            true,
            $boldFont
        );

        $nameText = Str::limit($name !== '' ? $name : '-', 52);
        $nameFontSize = $this->fitFontSize($nameText, $regularFont, 22, $sheetWidth - 180, 14);
        $this->drawCenteredText(
            $image,
            $nameText,
            (int) floor(self::PAGE_WIDTH / 2),
            $skuBlockTop + 54,
            $nameFontSize,
            $muted,
            false,
            $regularFont
        );

        $dividerY = max(
            $skuBlockTop + 126,
            $sheetY + $sheetHeight - 430
        );
        imageline($image, $sheetX + 48, $dividerY, $sheetX + $sheetWidth - 48, $dividerY, $line);

        $this->drawCenteredText(
            $image,
            $transaction->transacted_at?->format('m.y') ?? '-',
            (int) floor(self::PAGE_WIDTH / 2),
            $dividerY + 62,
            44,
            $text,
            true,
            $boldFont
        );

        $barcodePanelWidth = 700;
        $barcodePanelHeight = 152;
        $barcodePanelX = (int) floor((self::PAGE_WIDTH - $barcodePanelWidth) / 2);
        $barcodePanelY = $dividerY + 94;
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

        $barcodeBinary = $this->barcodeService->pngForValue((string) $transaction->code, 620, 104);
        $barcodeImage = imagecreatefromstring($barcodeBinary);
        if ($barcodeImage !== false) {
            $sourceWidth = imagesx($barcodeImage);
            $sourceHeight = imagesy($barcodeImage);
            $targetWidth = 620;
            $targetHeight = 104;
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
}
