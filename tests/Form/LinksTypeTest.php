<?php

namespace Base\Music\Tests\Form;

use Base\Music\Form\LinksType;
use Omnisong\Platform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/** The form alone, with the Validator extension (each URL carries its constraint) - no kernel. */
final class LinksTypeTest extends TestCase
{
    private static function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
    }

    public function testOneUrlFieldPerPlatformInTheirRankTheLabelLeftOut(): void
    {
        $form = self::forms()->create(LinksType::class, ['spotify' => 'https://open.spotify.com/album/abc']);
        $names = array_keys($form->all());

        self::assertNotContains('label', $names);
        self::assertNotContains('shop', $names);
        self::assertSame(['bandcamp', 'spotify', 'apple_music'], \array_slice($names, 0, 3));
        self::assertSame('Apple Music', $form->get('apple_music')->getConfig()->getOption('label'));
        self::assertSame('https://open.spotify.com/album/abc', $form->get('spotify')->getData());
    }

    public function testSubmittedIntoTheArrayTheEntityStores(): void
    {
        $form = self::forms()->create(LinksType::class, []);
        $form->submit(['qobuz' => 'https://www.qobuz.com/de-de/album/x', 'deezer' => '']);

        $data = $form->getData();
        self::assertSame('https://www.qobuz.com/de-de/album/x', $data['qobuz']);
        self::assertNull($data['deezer']);
        self::assertTrue($form->isValid());

        $bad = self::forms()->create(LinksType::class, []);
        $bad->submit(['spotify' => 'not an address']);
        self::assertFalse($bad->isValid());
        self::assertCount(\count(LinksType::platforms()), $data);
        self::assertNotContains(Platform::LABEL, LinksType::platforms());
    }
}
