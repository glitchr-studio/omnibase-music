<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Field\AssociationField;
use Base\Field\BooleanField;
use Base\Field\CollectionField;
use Base\Field\DateField;
use Base\Field\DateTimeField;
use Base\Field\EditorField;
use Base\Field\IdField;
use Base\Field\ImageField;
use Base\Field\IntegerField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Music\Entity\Release;
use Base\Music\Enum\ReleaseType;
use Base\Music\Form\LinksType;
use Base\Music\Form\TrackType;
use Base\Music\Service\ImportReport;
use Base\Music\Service\Importer;
use Base\Music\Service\Peaks;
use Omnisong\Exception\OmnisongException;
use Omnisong\Exception\UnavailableException;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The discography in the back office: a record's label and number, its
 * date, its sleeve, its tracks (each with its excerpt or a catalogue's
 * preview), its links, its prizes, its liner notes. Three buttons do the
 * typing: "Complete" asks the catalogues (Omnisong) what they know of it,
 * "Look up" makes a record from one URL or UPC, "Waveforms" draws its
 * tracks' peaks with ffmpeg. Nothing typed by hand is ever overwritten.
 */
class ReleaseCrudController extends AbstractCrudController
{
    private Importer $importer;
    private Peaks $peaks;

    #[Required]
    public function setMusicServices(Importer $importer, Peaks $peaks): void
    {
        $this->importer = $importer;
        $this->peaks = $peaks;
    }

    public static function getEntityFqcn(): string
    {
        return Release::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-record-vinyl';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('state')->add('label')->add('featured')->add('upcoming');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('cover', '@music.admin.release.cover')->setColumns(4)->setRequired(false);
        yield TextField::new('title', '@music.admin.release.title')->setColumns(8);
        yield StateField::new('state')->setColumns(4);
        yield SlugField::new('slug')->setColumns(4)->hideOnIndex();
        yield TextField::new('type', '@music.admin.release.type')->setColumns(4)->hideOnIndex()
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => ReleaseType::class, 'choice_label' => fn (ReleaseType $type) => '@music.release.type.'.$type->value])
            ->formatValue(fn ($value) => $value instanceof ReleaseType ? $value->value : $value);
        yield AssociationField::new('label', '@music.admin.release.label')->setColumns(4)->setRequired(false);
        yield TextField::new('catalogue', '@music.admin.release.catalogue')->setColumns(4)->setRequired(false);
        yield TextField::new('upc', '@music.admin.release.upc')->setColumns(4)->hideOnIndex()->setRequired(false);
        yield TextField::new('labelUrl', '@music.admin.release.label_url')->setColumns(8)->hideOnIndex()->setHelp('@music.admin.release.label_url_help')->setRequired(false);
        yield DateField::new('releasedAt', '@music.admin.release.released_at')->setColumns(4);
        yield IntegerField::new('plays', '@music.admin.release.plays')->hideOnForm()->setHelp('@music.admin.release.plays_help');
        yield BooleanField::new('featured', '@music.admin.release.featured')->setColumns(2);
        yield BooleanField::new('upcoming', '@music.admin.release.upcoming')->setColumns(2)->hideOnIndex();
        yield TextField::new('presaveUrl', '@music.admin.release.presave_url')->setColumns(8)->hideOnIndex()->setRequired(false);
        yield DateTimeField::new('publishedAt', '@music.admin.release.published_at')->setColumns(4)->hideOnIndex();
        yield TextField::new('coverUrl', '@music.admin.release.cover_url')->setColumns(12)->hideOnIndex()->setHelp('@music.admin.release.cover_url_help');
        yield AssociationField::new('performers', '@music.admin.release.performers')->allowMultipleChoices()->setRequired(false)->setColumns(12)->hideOnIndex();
        yield TextField::new('headline', '@music.admin.release.headline')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('excerpt', '@music.admin.release.excerpt')->hideOnIndex()->setHelp('@music.admin.release.excerpt_help');
        yield CollectionField::new('tracks', '@music.admin.release.tracks')->setEntryType(TrackType::class)->allowAdd()->allowDelete()->hideOnIndex()
            ->setFormTypeOptions(['by_reference' => false, 'allow_object' => true]);
        yield TextField::new('links', '@music.admin.release.links')->onlyOnForms()
            ->setFormType(LinksType::class)->setHelp('@music.admin.release.links_help');
        yield TextareaField::new('awards', '@music.admin.release.awards')->hideOnIndex()->setHelp('@music.admin.release.awards_help');
        yield EditorField::new('content', '@music.admin.release.content')->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);
        foreach ([Actions::PAGE_DETAIL, Actions::PAGE_EDIT] as $page) {
            $actions
                ->add($page, Action::new('complete', '@music.admin.release.action.complete', 'fa-solid fa-wand-magic-sparkles')->linkToCrudAction('complete'))
                ->add($page, Action::new('peaks', '@music.admin.release.action.peaks', 'fa-solid fa-wave-square')->linkToCrudAction('peaks'));
        }
        $actions->add(Actions::PAGE_INDEX, Action::new('lookup', '@music.admin.release.action.lookup', 'fa-solid fa-magnifying-glass')
            ->createAsGlobalAction()->linkToCrudAction('lookup'));

        return $actions;
    }

    /** What the catalogues know of this record, into its empty fields. */
    #[AdminAction('/{entityId}/complete')]
    public function complete(string $entityId): Response
    {
        /** @var Release $release */
        $release = $this->findEntity($entityId);
        try {
            $report = $this->importer->complete($release);
            $this->entityManager->flush();
            $this->flashReport($report);
        } catch (\InvalidArgumentException) {
            $this->addFlash('warning', new TranslatableMessage('admin.release.flash.no_reference', [], 'music'));
        } catch (UnavailableException) {
            $this->addFlash('warning', new TranslatableMessage('admin.release.flash.unavailable', [], 'music'));
        } catch (OmnisongException|\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRecord($release);
    }

    /** The waveforms of this record's excerpts, with ffmpeg. */
    #[AdminAction('/{entityId}/peaks')]
    public function peaks(string $entityId): Response
    {
        /** @var Release $release */
        $release = $this->findEntity($entityId);
        if (!$this->peaks->isAvailable()) {
            $this->addFlash('warning', new TranslatableMessage('admin.release.flash.no_ffmpeg', [], 'music'));

            return $this->redirectToRecord($release);
        }
        $done = 0;
        foreach ($release->getTracks() as $track) {
            $done += $this->peaks->compute($track) ? 1 : 0;
        }
        $this->entityManager->flush();
        $this->addFlash('success', new TranslatableMessage('admin.release.flash.peaks', ['count' => $done], 'music'));

        return $this->redirectToRecord($release);
    }

    /** One URL (any platform) or one UPC: the record it names, made or completed. */
    #[AdminAction('/lookup', methods: ['GET', 'POST'])]
    public function lookup(Request $request): Response
    {
        $value = trim($request->request->getString('reference'));
        if ($request->isMethod('POST') && '' !== $value) {
            try {
                $release = $this->importer->import($this->importer->reference($value), $report);
                if (null !== $release) {
                    $this->entityManager->flush();
                    $this->flashReport($report);

                    return $this->redirectToRecord($release, Action::EDIT);
                }
                $this->addFlash('warning', new TranslatableMessage($report?->isUnavailable() ? 'admin.release.flash.unavailable' : 'admin.release.flash.not_found', ['reference' => $value], 'music'));
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('warning', new TranslatableMessage('admin.release.flash.bad_reference', ['reference' => $value], 'music'));
            } catch (UnavailableException) {
                $this->addFlash('warning', new TranslatableMessage('admin.release.flash.unavailable', [], 'music'));
            } catch (OmnisongException|\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->renderCrud('@Music/admin/release/lookup.html.twig', [
            'reference' => $value,
            'available' => $this->importer->isAvailable(),
        ]);
    }

    public function createEntity(string $entityFqcn): object
    {
        $release = new Release();
        $user = $this->getUser();
        if ($user instanceof \Base\Entity\User) {
            $release->addOwner($user);
        }

        return $release;
    }

    private function flashReport(?ImportReport $report): void
    {
        if (null === $report) {
            return;
        }
        if ($report->changedSomething()) {
            $this->addFlash('success', new TranslatableMessage('admin.release.flash.completed', [
                'fields' => \count($report->filled),
                'created' => $report->tracksCreated,
                'updated' => $report->tracksUpdated,
            ], 'music'));
        } else {
            $this->addFlash('info', new TranslatableMessage($report->found ? 'admin.release.flash.nothing_new' : 'admin.release.flash.not_found_here', [], 'music'));
        }
        if ($report->incomplete) {
            $this->addFlash('warning', new TranslatableMessage('admin.release.flash.incomplete', ['catalogues' => implode(', ', $report->incomplete)], 'music'));
        }
    }

    private function redirectToRecord(Release $release, string $action = Action::DETAIL): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(static::class)->setAction($action)->setEntityId($this->fieldValueResolver->entityIdentifier($release))->generateUrl());
    }
}
