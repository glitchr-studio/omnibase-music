<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Field\BooleanField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\SlugField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Music\Entity\Playlist;
use Base\Music\Enum\PlaylistKind;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * The playlists and profiles on the platforms: the address is enough, the
 * platform is read off it. The musician's profile ("Artist") is what
 * "Listen in full" opens on /music; the others follow it.
 */
class PlaylistCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Playlist::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-list-ul';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('kind')->add('visible');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', '@music.admin.playlist.title')->setColumns(6);
        yield SlugField::new('slug')->setColumns(3)->hideOnIndex();
        yield TextField::new('kind', '@music.admin.playlist.kind')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => PlaylistKind::class, 'choice_label' => fn (PlaylistKind $k) => '@music.playlist.kind.'.$k->value])
            ->formatValue(fn ($value) => $value instanceof PlaylistKind ? $value->value : $value);
        yield TextField::new('url', '@music.admin.playlist.url')->setColumns(12)->setHelp('@music.admin.playlist.url_help');
        yield IntegerField::new('position', '@music.admin.playlist.position')->setColumns(2);
        yield BooleanField::new('visible', '@music.admin.playlist.visible')->setColumns(2);
        yield BooleanField::new('featured', '@music.admin.playlist.featured')->setColumns(2);
        yield TextareaField::new('description', '@music.admin.playlist.description')->hideOnIndex();
    }
}
