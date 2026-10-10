<?php

namespace Base\Music\Service;

use Base\Music\Entity\Track;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * The static waveform of a track's excerpt: ffmpeg decodes the file to raw
 * mono PCM at 8 kHz, and the samples are reduced to 200 loudness values
 * between 0 and 1 (reduce()), kept on the track. player.js draws them as
 * bars with no decoding in the browser. Without ffmpeg nothing is computed
 * - it is logged, and the player shows a plain progress line instead.
 */
class Peaks
{
    /** How many bars a waveform has. */
    public const COUNT = 200;

    /** What ffmpeg is asked for: samples a second, mono, signed 16 bits little-endian. */
    public const RATE = 8000;

    private ?bool $available = null;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly string $ffmpeg = 'ffmpeg',
    ) {
    }

    /** Whether ffmpeg answers (`ffmpeg -version`); asked once. */
    public function isAvailable(): bool
    {
        if (null !== $this->available) {
            return $this->available;
        }
        try {
            $process = new Process([$this->ffmpeg, '-version']);
            $process->setTimeout(10);
            $this->available = 0 === $process->run();
        } catch (ProcessException) {
            $this->available = false;
        }
        if (!$this->available) {
            $this->logger?->warning('music: ffmpeg was not found, the waveforms are not computed.');
        }

        return $this->available;
    }

    /**
     * Computes and sets the peaks of a track's excerpt (nothing is flushed).
     * Of the uploaded excerpt, or else of the platform's preview. False when
     * there is neither, no ffmpeg, or a file it cannot read.
     */
    public function compute(Track $track, int $count = self::COUNT): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        if (!$track->hasSample()) {
            // No excerpt of its own: the platform's preview (an https address ffmpeg reads itself).
            $preview = $track->getPreviewUrl();
            if (null === $preview || !str_starts_with($preview, 'https://')) {
                return false;
            }
            $peaks = $this->ofFile($preview, $count);
            if (null === $peaks) {
                return false;
            }
            $track->setPeaks($peaks);

            return true;
        }
        try {
            $path = $track->getSampleFile()?->getPathname();
        } catch (\Throwable $e) {
            $path = null;
            $this->logger?->warning('music: the excerpt of track {id} could not be located: {error}', ['id' => $track->getId(), 'error' => $e->getMessage()]);
        }
        if (null === $path || !is_readable($path)) {
            return false;
        }
        $peaks = $this->ofFile($path, $count);
        if (null === $peaks) {
            return false;
        }
        $track->setPeaks($peaks);

        return true;
    }

    /** @return list<float>|null the peaks of an audio file, null when ffmpeg could not decode it */
    public function ofFile(string $path, int $count = self::COUNT): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $process = new Process([$this->ffmpeg, '-v', 'error', '-nostdin', '-i', $path, '-ac', '1', '-ar', (string) self::RATE, '-f', 's16le', '-']);
            $process->setTimeout(300);
            $process->run();
        } catch (ProcessException $e) {
            $this->logger?->warning('music: ffmpeg failed on {path}: {error}', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
        if (!$process->isSuccessful() || '' === $process->getOutput()) {
            $this->logger?->warning('music: ffmpeg could not decode {path}: {error}', ['path' => $path, 'error' => trim($process->getErrorOutput())]);

            return null;
        }

        return self::reduce($process->getOutput(), $count);
    }

    /**
     * Raw PCM (mono, signed 16 bits little-endian) to $count values in
     * 0..1: the samples are cut in $count equal windows, each window gives
     * its RMS, and the loudest window is 1. Fewer samples than $count give
     * one value a sample; silence gives zeros; nothing gives nothing.
     *
     * @return list<float>
     */
    public static function reduce(string $pcm, int $count = self::COUNT): array
    {
        $samples = intdiv(\strlen($pcm), 2);
        if ($samples < 1 || $count < 1) {
            return [];
        }
        $count = min($count, $samples);
        $peaks = [];
        for ($i = 0; $i < $count; ++$i) {
            $from = intdiv($i * $samples, $count);
            $to = max($from + 1, intdiv(($i + 1) * $samples, $count));
            // A window at a time: unpacking a whole track at once would cost hundreds of megabytes.
            $sum = 0.0;
            foreach (unpack('v*', substr($pcm, $from * 2, ($to - $from) * 2)) ?: [] as $value) {
                $value = $value >= 0x8000 ? $value - 0x10000 : $value;
                $sum += $value * $value;
            }
            $peaks[] = sqrt($sum / ($to - $from));
        }
        $max = max($peaks);
        if ($max <= 0.0) {
            return array_fill(0, $count, 0.0);
        }

        return array_map(static fn (float $peak): float => round($peak / $max, 3), $peaks);
    }
}
