<?php

namespace Base\Music\Form;

use Omnisong\Platform;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Url;

/**
 * A release's links in the back office: one address per platform, in the
 * order the site shows them (Platform::rank()), bound to the json column
 * Release::$links (platform value => URL). The label's own page is not here
 * - it is the Label's pattern, or the release's labelUrl - nor its shop.
 */
final class LinksType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::platforms($options['exclude']) as $platform) {
            $builder->add($platform->value, UrlType::class, [
                // A brand's name, never translated (base-bundle would send it to the "fields" domain).
                'label' => $platform->label(),
                'translation_domain' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Url(requireTld: true)],
                'attr' => ['placeholder' => 'https://…', 'inputmode' => 'url'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'required' => false,
            'empty_data' => [],
            'exclude' => [Platform::LABEL, Platform::SHOP],
            'translation_domain' => 'music',
        ]);
        $resolver->setAllowedTypes('exclude', 'array');
    }

    /**
     * The platforms a link can be typed for, in their rank.
     *
     * @param list<Platform> $exclude
     *
     * @return list<Platform>
     */
    public static function platforms(array $exclude = [Platform::LABEL, Platform::SHOP]): array
    {
        $platforms = array_values(array_filter(Platform::cases(), static fn (Platform $p) => !\in_array($p, $exclude, true)));
        usort($platforms, static fn (Platform $a, Platform $b) => [$a->rank(), $a->value] <=> [$b->rank(), $b->value]);

        return $platforms;
    }
}
