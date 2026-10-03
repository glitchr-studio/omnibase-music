<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\CountryField;
use Base\Field\IdField;
use Base\Field\ImageField;
use Base\Field\SlugField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Music\Entity\Label;

/**
 * The labels: a name, a logo, a site, a shop, and how the page of one of
 * its records is written ({catalogue}, {upc}, {slug}) - with that, a
 * record needs only its number to link to its label.
 */
class LabelCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Label::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-building-columns';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('logo', '@music.admin.label.logo')->setColumns(4)->setRequired(false);
        yield TextField::new('name', '@music.admin.label.name')->setColumns(5);
        yield SlugField::new('slug')->setColumns(3)->hideOnIndex();
        yield TextField::new('url', '@music.admin.label.url')->setColumns(6);
        yield TextField::new('shopUrl', '@music.admin.label.shop_url')->setColumns(6)->hideOnIndex();
        yield TextField::new('releaseUrlPattern', '@music.admin.label.release_url_pattern')->setColumns(9)->hideOnIndex()->setHelp('@music.admin.label.release_url_pattern_help');
        yield CountryField::new('country', '@music.admin.label.country')->setColumns(3);
        yield TextareaField::new('description', '@music.admin.label.description')->hideOnIndex();
    }
}
