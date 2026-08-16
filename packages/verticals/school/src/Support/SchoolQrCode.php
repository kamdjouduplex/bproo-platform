<?php

namespace School\Support;

use School\Models\StudentIdCard;

/**
 * QR helper optimized for batch ID cards.
 *
 * - SVG only (no raster/GD)
 * - Persist on the card (qr_svg) so print never regenerates N×
 * - Compact + Low ECC for fast encode / small footprint
 */
final class SchoolQrCode
{
    public static function svg(string $data, int $size = 96): ?string
    {
        if ($data === '' || ! class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            return null;
        }

        $writerOptions = [];
        if (defined(\Endroid\QrCode\Writer\SvgWriter::class.'::WRITER_OPTION_EXCLUDE_XML_DECLARATION')) {
            $writerOptions[\Endroid\QrCode\Writer\SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION] = true;
        }
        if (defined(\Endroid\QrCode\Writer\SvgWriter::class.'::WRITER_OPTION_COMPACT')) {
            $writerOptions[\Endroid\QrCode\Writer\SvgWriter::WRITER_OPTION_COMPACT] = true;
        }

        $result = (new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\SvgWriter(),
            writerOptions: $writerOptions,
            validateResult: false,
            data: $data,
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Low,
            size: $size,
            margin: 1,
            roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::None,
        ))->build();

        $svg = trim($result->getString());
        // Safety if writer ignored exclude flag
        $svg = preg_replace('/^<\?xml[^>]*>\s*/i', '', $svg) ?? $svg;

        return $svg !== '' ? $svg : null;
    }

    public static function ensureCached(StudentIdCard $card, bool $force = false): ?string
    {
        if (! $force && is_string($card->qr_svg) && $card->qr_svg !== '') {
            return $card->qr_svg;
        }

        $token = (string) ($card->qr_token ?? '');
        if ($token === '') {
            return null;
        }

        $svg = self::svg($token, 96);
        if ($svg === null) {
            return null;
        }

        // Avoid model events recursion; update quietly.
        StudentIdCard::query()->whereKey($card->id)->update(['qr_svg' => $svg]);
        $card->qr_svg = $svg;

        return $svg;
    }

    /** @deprecated Prefer cached qr_svg / ensureCached() */
    public static function dataUri(string $data, int $size = 180): ?string
    {
        $svg = self::svg($data, max(64, min($size, 160)));
        if ($svg === null) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
