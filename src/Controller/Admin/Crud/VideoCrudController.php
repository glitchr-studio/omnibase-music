<?php

namespace Base\Music\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Field\AssociationField;
use Base\Field\BooleanField;
use Base\Field\DateField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\ImageField;
use Base\Field\IntegerField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Field\VideoField;
use Base\Music\Entity\Release;
use Base\Music\Entity\Video;

/**
 * The films: a file of the site's, or a YouTube or Vimeo address (the id
 * is read off it), a poster - always: the embed loads only on a click,
 * the poster is what is seen until then - where and when, what was played.
 */
class VideoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Video::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-film';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('state')->add('featured');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('poster', '@music.admin.video.poster')->setColumns(4)->setRequired(false);
        yield TextField::new('title', '@music.admin.video.title')->setColumns(8);
        yield StateField::new('state')->setColumns(4);
        yield SlugField::new('slug')->setColumns(4)->hideOnIndex();
        yield DateTimeField::new('publishedAt', '@music.admin.video.published_at')->setColumns(4)->hideOnIndex();
        yield TextField::new('youtubeId', '@music.admin.video.youtube')->setColumns(4)->setHelp('@music.admin.video.youtube_help');
        yield TextField::new('vimeoId', '@music.admin.video.vimeo')->setColumns(4)->hideOnIndex();
        yield VideoField::new('file', '@music.admin.video.file')->setColumns(4)->setRequired(false)->hideOnIndex()->setFormTypeOption('multiple', false);
        yield IntegerField::new('duration', '@music.admin.video.duration')->setColumns(3)->hideOnIndex()->setHelp('@music.admin.video.duration_help');
        yield DateField::new('recordedAt', '@music.admin.video.recorded_at')->setColumns(3);
        yield TextField::new('venue', '@music.admin.video.venue')->setColumns(4)->hideOnIndex();
        yield BooleanField::new('featured', '@music.admin.video.featured')->setColumns(2);
        yield AssociationField::new('work', '@music.admin.video.work')->setColumns(6)->setRequired(false)->hideOnIndex();
        // A release is a thread: picked from the list, as marketplace picks a product's store.
        yield SelectField::new('release', '@music.admin.video.release')->setClass(Release::class)->setRequired(false)->setColumns(6)->hideOnIndex();
        yield AssociationField::new('performers', '@music.admin.video.performers')->allowMultipleChoices()->setRequired(false)->setColumns(12)->hideOnIndex();
        yield TextField::new('headline', '@music.admin.video.headline')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('excerpt', '@music.admin.video.excerpt')->hideOnIndex();
    }

    public function createEntity(string $entityFqcn): object
    {
        $video = new Video();
        $user = $this->getUser();
        if ($user instanceof \Base\Entity\User) {
            $video->addOwner($user);
        }

        return $video;
    }
}
