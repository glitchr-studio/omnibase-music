<?php

namespace Base\Music\DependencyInjection;

use Base\Bundle\AbstractBaseConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class MusicConfiguration extends AbstractBaseConfiguration
{
    private bool $childrenDeclared = false;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getTreeBuilder();
        if ($this->childrenDeclared) {
            return $treeBuilder;
        }
        $this->childrenDeclared = true;

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('player')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('waves')->defaultTrue()->info('The static waveform of a track (its peaks) drawn behind the progress.')->end()
                        ->booleanNode('live_waves')->defaultTrue()->info('The bar\'s wave moves with the sound (Web Audio); off, or for a visitor who asks for less motion, the static one stays.')->end()
                        ->arrayNode('order')
                            ->info('What a track plays from, first found first: the site\'s own file, a catalogue\'s 30 s preview, a platform\'s embed.')
                            ->enumPrototype()->values(['file', 'preview', 'embed'])->end()
                            ->defaultValue(['file', 'preview', 'embed'])
                        ->end()
                        ->arrayNode('full')
                            ->info('The platforms "Listen in full" offers for a release, in this order, when it has a link there (Omnisong\\Platform values).')
                            ->scalarPrototype()->end()
                            ->defaultValue(['spotify', 'apple_music', 'deezer'])
                        ->end()
                        ->enumNode('theme')->values(['light', 'dark'])->defaultValue('light')->info('The theme the platforms\' embeds are asked for.')->end()
                    ->end()
                ->end()
                ->scalarNode('artist')->defaultNull()
                    ->info('The musician\'s name, as the JSON-LD gives it (byArtist); null: the site\'s title (base.settings.title).')->end()
                ->booleanNode('label_first')->defaultTrue()
                    ->info('The label - its logo, "released by", "buy it at the label" - before the streaming platforms.')->end()
                ->scalarNode('country')->defaultValue('DE')
                    ->info('ISO 3166-1 alpha-2: the store the catalogues are asked about, and the one the links open.')->end()
                ->booleanNode('jsonld')->defaultTrue()->info('A schema.org MusicAlbum on a release\'s page.')->end()
                ->arrayNode('repertoire')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->info('The /repertoire page.')->end()
                        ->enumNode('group_by')->values(['formation', 'composer', 'period'])->defaultValue('formation')->info('How the works are grouped when the visitor has not chosen.')->end()
                    ->end()
                ->end()
                ->arrayNode('instrument')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->info('The /instrument page (a 404 as long as no instrument is visible).')->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
