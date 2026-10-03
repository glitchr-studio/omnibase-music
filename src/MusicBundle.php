<?php

namespace Base\Music;

use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A musician's site on top of omnibase's Thread: a Release and a Video ARE
 * threads (JOINED subclasses); a label, a track, a work, an instrument and a
 * performer are plain rows. The label goes first, everywhere.
 */
class MusicBundle extends AbstractBaseBundle
{
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /** Modern layout: the class lives in src/, the bundle root is the package root. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // App\-wins, as omnibase does for its own entities: an application may
        // declare App\Entity\Music\Release extending ours and take over.
        $this->setMapping($this->getPath().'/src/Entity', 'Base\Music\Entity', 'App\Entity\Music');
        $this->setMapping($this->getPath().'/src/Repository', 'Base\Music\Repository', 'App\Repository\Music');
    }
}
