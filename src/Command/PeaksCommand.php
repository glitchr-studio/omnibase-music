<?php

namespace Base\Music\Command;

use Base\Music\Repository\TrackRepository;
use Base\Music\Service\Peaks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Computes the waveforms of the tracks' excerpts with ffmpeg: those that
 * have none yet, all of them again with --all, one with --track. Without
 * ffmpeg it says so and leaves: the player draws a plain line instead.
 */
#[AsCommand(name: 'music:peaks', description: 'Computes the static waveforms (peaks) of the tracks\' excerpts with ffmpeg.')]
class PeaksCommand extends Command
{
    public function __construct(
        private readonly TrackRepository $tracks,
        private readonly Peaks $peaks,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Every excerpt again, not only those without a waveform')
            ->addOption('track', null, InputOption::VALUE_REQUIRED, 'One track, by its id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->peaks->isAvailable()) {
            $io->warning('ffmpeg was not found: no waveform computed. Install it (apt install ffmpeg, apk add ffmpeg) and run again.');

            return Command::SUCCESS;
        }

        if (null !== $id = $input->getOption('track')) {
            $track = $this->tracks->find((int) $id);
            if (null === $track) {
                $io->error(sprintf('No track %s.', $id));

                return Command::FAILURE;
            }
            $tracks = [$track];
        } else {
            $tracks = $this->tracks->findWithSample(!$input->getOption('all'));
        }

        $done = $skipped = 0;
        foreach ($tracks as $track) {
            if ($this->peaks->compute($track)) {
                ++$done;
                $io->writeln(sprintf(' <info>✓</info> #%d %s', $track->getId(), $track->getDisplayTitle()), OutputInterface::VERBOSITY_VERBOSE);
            } else {
                ++$skipped;
                $io->writeln(sprintf(' <comment>-</comment> #%d %s', $track->getId(), $track->getDisplayTitle()), OutputInterface::VERBOSITY_VERBOSE);
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d waveform(s) computed, %d track(s) skipped (no excerpt, or a file ffmpeg could not read).', $done, $skipped));

        return Command::SUCCESS;
    }
}
