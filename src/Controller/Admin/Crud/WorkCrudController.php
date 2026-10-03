<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Field\BooleanField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Music\Entity\Work;
use Base\Music\Enum\Formation;
use Base\Music\Enum\Period;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/** The repertoire: who wrote it, what, for whom, how long; visible on /repertoire or kept aside. */
class WorkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Work::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-book-open';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('composer')->add('visible');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('composer', '@music.admin.work.composer')->setColumns(4);
        yield TextField::new('title', '@music.admin.work.title')->setColumns(5);
        yield TextField::new('opus', '@music.admin.work.opus')->setColumns(3);
        yield TextField::new('formation', '@music.admin.work.formation')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => Formation::class, 'choice_label' => fn (Formation $f) => '@music.formation.'.$f->value])
            ->formatValue(fn ($value) => $value instanceof Formation ? $value->value : $value);
        yield TextField::new('instrumentation', '@music.admin.work.instrumentation')->setColumns(5)->hideOnIndex();
        yield TextField::new('period', '@music.admin.work.period')->setColumns(4)->hideOnIndex()
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => Period::class, 'required' => false, 'placeholder' => '—', 'choice_label' => fn (Period $p) => '@music.period.'.$p->value])
            ->formatValue(fn ($value) => $value instanceof Period ? $value->value : $value);
        yield IntegerField::new('year', '@music.admin.work.year')->setColumns(2);
        yield IntegerField::new('duration', '@music.admin.work.duration')->setColumns(2)->hideOnIndex();
        yield IntegerField::new('position', '@music.admin.work.position')->setColumns(2)->hideOnIndex();
        yield BooleanField::new('visible', '@music.admin.work.visible')->setColumns(2);
        yield TextareaField::new('movements', '@music.admin.work.movements')->hideOnIndex()->setHelp('@music.admin.work.movements_help');
    }
}
