<?php

declare(strict_types=1);

use CraftCms\Cms\Asset\AssetUploadHandler;
use CraftCms\Cms\Asset\Data\AssetIngest;
use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Enums\AssetIngestStatus;
use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Assets;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Entries;
use CraftCms\Cms\Field\Markdown;
use CraftCms\Cms\Field\Matrix;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Filesystem\Data\UploadedFile;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function seedContentFixtures(array $fixtures): array
{
    if (!app()->environment('e2e') || DB::selectOne('select current_database() as name')->name !== 'inertia_poc_e2e') {
        throw new RuntimeException('Content fixtures require the isolated E2E database.');
    }

    contentField(new Markdown(['name' => 'Block copy', 'handle' => 'blockCopy', 'sanitizeHtml' => true]));
    $textType = createType('pocTextBlock', ['heading', 'blockCopy']);
    $children = new Matrix(['name' => 'Children', 'handle' => 'nestedContent']);
    $children->setEntryTypes([$textType]);
    contentField($children);
    $groupType = createType('pocGroupBlock', ['heading', 'nestedContent']);
    $unsupportedType = createType('pocUnsupportedBlock', ['internalNote']);

    foreach (['richText', 'richCopy'] as $handle) {
        contentField(new Markdown(['name' => ucfirst($handle), 'handle' => $handle, 'sanitizeHtml' => true]));
    }
    foreach (['relatedArticles', 'topics', 'relatedStories', 'themes'] as $handle) {
        contentField(new Entries(['name' => ucfirst($handle), 'handle' => $handle]));
    }
    foreach (['heroImage', 'projectImage', 'coverImage', 'caseImage'] as $handle) {
        contentField(new Assets(['name' => ucfirst($handle), 'handle' => $handle]));
    }
    foreach (['contentBlocks', 'projectContent', 'storyBlocks', 'caseContent'] as $handle) {
        $field = new Matrix(['name' => ucfirst($handle), 'handle' => $handle]);
        $field->setEntryTypes([$textType, $groupType, $unsupportedType]);
        contentField($field);
    }

    contentTypeFields('pocArticle', ['richText', 'relatedArticles', 'topics', 'heroImage', 'contentBlocks']);
    contentTypeFields('pocProject', ['projectImage', 'projectContent']);
    contentTypeFields('pocJournal', ['richCopy', 'relatedStories', 'themes', 'coverImage', 'storyBlocks']);
    contentTypeFields('pocCaseStudy', ['caseImage', 'caseContent']);

    $topicType = createType('pocTopic');
    $topicSection = contentSection('topics', $topicType);
    $architecture = createEntry($topicSection, $topicType, 'Architecture');
    $culture = createEntry($topicSection, $topicType, 'Culture');
    $hiddenTopic = createEntry($topicSection, $topicType, 'Unpublished topic secret', ['enabled' => false]);

    $settingsType = createType('pocSiteSettings', ['tagline']);
    $settingsSection = contentSection('siteSettings', $settingsType, SectionType::Single);
    $settings = Entry::find()->sectionId($settingsSection->id)->status(null)->one();
    $settings->title = 'Site settings';
    $settings->setFieldValue('tagline', 'Native single content shared across public pages.');
    save($settings, Elements::saveElement($settings));

    $asset = contentImage();
    $article = Entry::find()->id($fixtures['published'][21]['id'])->one();
    $related = Entry::find()->id($fixtures['published'][20]['id'])->one();
    $related->setFieldValue('topics', [$architecture->id]);
    save($related, Elements::saveElement($related));

    $richText = "## Native rich text\n\nA **formatted** paragraph with [a safe link](https://example.com/content).\n\n<script>window.__contentXss = 1</script>\n\n[Unsafe](javascript:alert(1))";
    $blocks = contentBlocks('Article');
    $article->setFieldValues([
        'richText' => $richText,
        'relatedArticles' => [$related->id, $fixtures['disabled']['id']],
        'topics' => [$architecture->id, $culture->id, $hiddenTopic->id],
        'heroImage' => [$asset->id],
        'contentBlocks' => $blocks,
    ]);
    save($article, Elements::saveElement($article));

    $project = Entry::find()->id($fixtures['project']['id'])->one();
    $project->setFieldValues(['projectImage' => [$asset->id], 'projectContent' => contentBlocks('Project')]);
    save($project, Elements::saveElement($project));

    $journal = Entry::find()->id($fixtures['alternateArticle']['id'])->one();
    $journal->setFieldValues([
        'richCopy' => '## Alternate rich text'."\n\n".'Journal **content** from different handles.',
        'relatedStories' => [$journal->id],
        'themes' => [$culture->id],
        'coverImage' => [$asset->id],
        'storyBlocks' => contentBlocks('Journal'),
    ]);
    save($journal, Elements::saveElement($journal));

    $case = Entry::find()->id($fixtures['alternateProject']['id'])->one();
    $case->setFieldValues(['caseImage' => [$asset->id], 'caseContent' => contentBlocks('Case')]);
    save($case, Elements::saveElement($case));

    $transform = ['width' => 320, 'height' => 180, 'mode' => 'crop'];
    $asset->getSrcset(['320w', '640w'], $transform);

    return [
        'article' => entryData($article),
        'related' => entryData($related),
        'topics' => [entryData($architecture), entryData($culture)],
        'hiddenTopic' => entryData($hiddenTopic),
        'richText' => $article->getFieldValue('richText')->getHtml(),
        'alternateRichText' => $journal->getFieldValue('richCopy')->getHtml(),
        'tagline' => $settings->getFieldValue('tagline'),
        'image' => [
            'url' => $asset->getUrl($transform),
            'srcset' => $asset->getSrcset(['320w', '640w'], $transform),
            'width' => $asset->getWidth($transform),
            'height' => $asset->getHeight($transform),
            'alt' => $asset->alt,
        ],
    ];
}

function contentField(FieldInterface $field): void
{
    if (Fields::getFieldByHandle($field->handle) === null) {
        save($field, Fields::saveField($field));
    }
}

/** @param list<string> $handles */
function contentTypeFields(string $handle, array $handles): void
{
    $type = EntryTypes::getEntryTypeByHandle($handle);
    $layout = $type->getFieldLayout();
    $tabs = $layout->getTabs();
    $elements = $tabs[0]->getElements();
    foreach ($handles as $fieldHandle) {
        if ($layout->getFieldByHandle($fieldHandle) === null) {
            $elements[] = new CustomField(Fields::getFieldByHandle($fieldHandle));
        }
    }
    $tabs[0]->setElements($elements);
    $layout->setTabs($tabs);
    $type->setFieldLayout($layout);
    save($type, EntryTypes::saveEntryType($type));
}

function contentSection(string $handle, EntryType $type, SectionType $sectionType = SectionType::Channel): Section
{
    $section = new Section(['name' => ucfirst($handle), 'handle' => $handle, 'type' => $sectionType]);
    $section->setEntryTypes([$type]);
    $section->setSiteSettings([new SectionSiteSettings(['siteId' => Sites::getCurrentSite()->id, 'hasUrls' => false])]);
    save($section, Sections::saveSection($section));

    return $section;
}

/** @return array<string, array<string, mixed>> */
function contentBlocks(string $prefix): array
{
    return [
        'new1' => ['type' => 'pocTextBlock', 'title' => $prefix.' text block', 'enabled' => true, 'fields' => [
            'heading' => $prefix.' block heading',
            'blockCopy' => 'A **native Markdown** block.',
        ]],
        'new2' => ['type' => 'pocGroupBlock', 'title' => $prefix.' group', 'enabled' => true, 'fields' => [
            'heading' => $prefix.' nested group',
            'nestedContent' => ['new1' => ['type' => 'pocTextBlock', 'title' => $prefix.' nested text', 'enabled' => true, 'fields' => [
                'heading' => $prefix.' nested heading',
                'blockCopy' => 'Nested **Markdown** content.',
            ]]],
        ]],
        'new3' => ['type' => 'pocUnsupportedBlock', 'title' => $prefix.' private block', 'enabled' => true, 'fields' => [
            'internalNote' => 'Unsupported block internal secret',
        ]],
        'new4' => ['type' => 'pocTextBlock', 'title' => $prefix.' disabled block', 'enabled' => false, 'fields' => [
            'heading' => 'Disabled block secret',
            'blockCopy' => 'Disabled nested content secret',
        ]],
    ];
}

function contentImage(): Asset
{
    $volume = new Volume(['name' => 'Content images', 'handle' => 'contentImages', 'fsHandle' => 'uploads', 'hasUrls' => true]);
    save($volume, Volumes::saveVolume($volume));
    $folder = Folders::getRootFolderByVolumeId($volume->id);
    $disk = Storage::disk('local');
    $disk->makeDirectory('fixtures');
    $path = $disk->path('fixtures/native-content.png');
    $image = imagecreatetruecolor(960, 540);
    imagefill($image, 0, 0, imagecolorallocate($image, 36, 88, 120));
    imagepng($image, $path);

    $result = app(AssetUploadHandler::class)->ingest(new AssetIngest(
        source: new UploadedFile($disk, 'fixtures/native-content.png', 'native-content.png'),
        filename: 'native-content.png',
        mimeType: 'image/png',
        folder: $folder,
        sanitizeOnUpload: true,
        asset: new Asset(['title' => 'Native content image', 'alt' => 'Blue content fixture']),
    ));
    if ($result->status !== AssetIngestStatus::Saved) {
        throw new RuntimeException('Could not ingest native content image: '.json_encode($result->asset->errors()->getMessages(), JSON_THROW_ON_ERROR));
    }

    return $result->asset;
}
