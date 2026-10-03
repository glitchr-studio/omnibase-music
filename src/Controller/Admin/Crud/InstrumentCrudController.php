<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\BooleanField;
use Base\Field\IdField;
use Base\Field\ImageField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Music\Entity\Instrument;

/** The instrument: its maker, its year and place, its story, who lends it, a picture. */
class InstrumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Instrument::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-guitar';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('image', '@music.admin.instrument.image')->setColumns(4)->setRequired(false);
        yield TextField::new('name', '@music.admin.instrument.name')->setColumns(5);
        yield TextField::new('kind', '@music.admin.instrument.kind')->setColumns(3);
        yield TextField::new('maker', '@music.admin.instrument.maker')->setColumns(4)->hideOnIndex();
        yield TextField::new('place', '@music.admin.instrument.place')->setColumns(3);
        yield IntegerField::new('year', '@music.admin.instrument.year')->setColumns(2);
        yield TextField::new('model', '@music.admin.instrument.model')->setColumns(3)->hideOnIndex();
        yield TextField::new('loan', '@music.admin.instrument.loan')->setColumns(6)->hideOnIndex()->setHelp('@music.admin.instrument.loan_help');
        yield BooleanField::new('visible', '@music.admin.instrument.visible')->setColumns(3);
        yield BooleanField::new('featured', '@music.admin.instrument.featured')->setColumns(3);
        yield TextareaField::new('provenance', '@music.admin.instrument.provenance')->hideOnIndex();
        yield TextareaField::new('description', '@music.admin.instrument.description')->hideOnIndex();
    }
}
