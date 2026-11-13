<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use Closure;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\ElementCaches;
use Illuminate\Http\Request;

/** Request-local serializer inputs and bounded, explicit projection helpers. */
final readonly class FieldContext
{
    /**
     * @param Entry $entry Owner entry, including its actual preview identity.
     * @param FieldInterface $field Native field and its unchanged handle.
     * @param mixed $value Native normalized value, or an ordered list of native elements for Assets/Entries/Matrix.
     * @param Site $site Current resolved site.
     * @param Request $request Current request; never include it in the result.
     * @param int $depth Current entry relation depth (root is zero).
     * @param string $path Diagnostic field path through the relation tree.
     * @param Closure(): mixed $native Lazy default projection, without re-entering serializer selection.
     * @param Closure(Entry, list<string>): array<string, mixed> $project Bounded child projection preserving ancestors.
     */
    public function __construct(
        public Entry $entry,
        public FieldInterface $field,
        public mixed $value,
        public Site $site,
        public Request $request,
        public int $depth,
        public string $path,
        private Closure $native,
        private Closure $project,
    ) {
    }

    /** @return mixed Native field projection with the normal child serializer pipeline. */
    public function defaultValue(): mixed
    {
        return ($this->native)();
    }

    /**
     * @param Entry $entry Visible related entry from the native value or an authorized project query.
     * @param list<string> $fields Explicit selected child handles; an empty list exposes identity only.
     * @return array<string, mixed> Selected fields with the original recursion/dependency rules.
     */
    public function projectEntry(Entry $entry, array $fields = []): array
    {
        return ($this->project)($entry, $fields);
    }

    /**
     * @param Asset $asset Visible native relation asset or explicitly selected project asset.
     * @param list<string> $profiles Project image profiles, empty for the unchanged native asset shape.
     * @return array<string, mixed> Original asset plus optional responsive variants.
     */
    public function projectAsset(Asset $asset, array $profiles = []): array
    {
        return app(AssetProjection::class)->project($asset, $profiles);
    }

    /**
     * @param ElementInterface $element Additional element used by a custom project query.
     */
    public function collect(ElementInterface $element): void
    {
        ElementCaches::collectCacheInfoForElement($element);
    }
}
