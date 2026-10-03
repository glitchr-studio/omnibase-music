<?php

namespace Base\Music\Service;

/** What Importer did to a release: what it filled, how many tracks it touched, and which catalogues did not answer. */
final class ImportReport
{
    /**
     * @param list<string> $filled     the fields that were empty and are not any more
     * @param list<string> $incomplete the catalogues that could not be reached: ask again later
     */
    public function __construct(
        public readonly bool $found,
        public readonly array $filled = [],
        public readonly int $tracksCreated = 0,
        public readonly int $tracksUpdated = 0,
        public readonly array $incomplete = [],
    ) {
    }

    /** Nothing found because a catalogue was down - not because the release does not exist. */
    public function isUnavailable(): bool
    {
        return !$this->found && [] !== $this->incomplete;
    }

    public function changedSomething(): bool
    {
        return [] !== $this->filled || $this->tracksCreated > 0 || $this->tracksUpdated > 0;
    }
}
