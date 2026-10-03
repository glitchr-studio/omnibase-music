<?php

namespace Base\Music\Form;

use Base\Field\Type\AudioType;
use Base\Music\Entity\Track;
use Base\Music\Entity\Work;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One track of a release in the back office: its place, its title - or
 * the work and the movement - its length, its ISRC, the site's excerpt
 * (an upload) and a catalogue's preview. The waveform is not typed: it is
 * computed (the "Waveforms" action, or `music:peaks`).
 *
 * The texts name their domain (@music.…): base-bundle gives every field
 * the "fields" domain, which the form's own translation_domain does not reach.
 */
class TrackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('disc', IntegerType::class, ['label' => '@music.admin.track.disc', 'empty_data' => '1', 'attr' => ['min' => 1, 'max' => 99]])
            ->add('position', IntegerType::class, ['label' => '@music.admin.track.position', 'required' => false, 'attr' => ['min' => 1]])
            ->add('title', TextType::class, ['label' => '@music.admin.track.title', 'required' => false, 'help' => '@music.admin.track.title_help'])
            ->add('work', EntityType::class, ['label' => '@music.admin.track.work', 'class' => Work::class, 'required' => false, 'placeholder' => '—'])
            ->add('movement', TextType::class, ['label' => '@music.admin.track.movement', 'required' => false])
            ->add('duration', IntegerType::class, ['label' => '@music.admin.track.duration', 'required' => false, 'help' => '@music.admin.track.duration_help', 'attr' => ['min' => 0]])
            ->add('isrc', TextType::class, ['label' => '@music.admin.track.isrc', 'required' => false, 'attr' => ['maxlength' => 15]])
            ->add('sample', AudioType::class, ['label' => '@music.admin.track.sample', 'required' => false, 'multiple' => false, 'class' => Track::class, 'data_mapping' => 'sample', 'help' => '@music.admin.track.sample_help'])
            ->add('previewUrl', UrlType::class, ['label' => '@music.admin.track.preview_url', 'required' => false, 'default_protocol' => 'https', 'help' => '@music.admin.track.preview_url_help'])
            ->add('performers', TextType::class, ['label' => '@music.admin.track.performers', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Track::class,
            'empty_data' => fn () => new Track(),
            'translation_domain' => 'music',
        ]);
    }
}
