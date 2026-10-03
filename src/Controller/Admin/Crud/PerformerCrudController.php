<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Base\Music\Entity\Performer;
use Base\Music\Enum\PerformerKind;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/** Who plays with the musician: a name, what they play, a site; their order in the billing. */
class PerformerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Performer::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-people-group';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', '@music.admin.performer.name')->setColumns(5);
        yield TextField::new('kind', '@music.admin.performer.kind')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => PerformerKind::class, 'choice_label' => fn (PerformerKind $k) => '@music.performer.kind.'.$k->value])
            ->formatValue(fn ($value) => $value instanceof PerformerKind ? $value->value : $value);
        yield TextField::new('role', '@music.admin.performer.role')->setColumns(4)->setHelp('@music.admin.performer.role_help');
        yield TextField::new('url', '@music.admin.performer.url')->setColumns(9)->hideOnIndex();
        yield IntegerField::new('position', '@music.admin.performer.position')->setColumns(3);
    }
}
