<?php
declare(strict_types=1);

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class QrManager
{
    private string $dir;
    private string $urlBase;
    private int $size;
    private PngWriter $writer;

    public function __construct(array $cfg)
    {
        $this->dir = rtrim((string)($cfg['dir'] ?? __DIR__ . '/../public/uploads/qrcodes'), '/');
        $this->urlBase = rtrim((string)($cfg['url'] ?? '/uploads/qrcodes'), '/');
        $this->size = (int)($cfg['size'] ?? 220);
        if (!is_dir($this->dir)) {
            if (!mkdir($concurrentDirectory = $this->dir, 0775, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException('Unable to create QR output dir');
            }
        }
        $this->writer = new PngWriter();
    }

    public function ensureForMatch(int $matchId, string $codeA, string $codeB): void
    {
        $this->ensure('A', $matchId, $codeA);
        $this->ensure('B', $matchId, $codeB);
    }

    public function urlFor(int $matchId, string $side): ?string
    {
        $side = strtoupper($side);
        $path = $this->pathFor($matchId, $side);
        if (!is_file($path)) {
            return null;
        }
        return $this->urlBase . '/' . basename($path);
    }

    private function ensure(string $side, int $matchId, string $code): void
    {
        $path = $this->pathFor($matchId, $side);
        if (is_file($path)) {
            return;
        }
        $prev = error_reporting();
        error_reporting($prev & ~E_DEPRECATED);
        try {
            $qrCode = new QrCode(
                data: $code,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: $this->size,
                margin: 12,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );
            $result = $this->writer->write($qrCode);
        } finally {
            error_reporting($prev);
        }
        file_put_contents($path, $result->getString());
    }

    private function pathFor(int $matchId, string $side): string
    {
        $file = sprintf('match-%d-%s.png', $matchId, strtoupper($side));
        return $this->dir . '/' . $file;
    }
}
