<?php

namespace Base\Music\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MusicExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): MusicConfiguration
    {
        return new MusicConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $configuration = new MusicConfiguration();
        $config = (new Processor())->processConfiguration($configuration, $configs);

        // Flat parameters: music.player.waves, music.label_first, music.country...
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());
    }
}
