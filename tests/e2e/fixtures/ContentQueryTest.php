<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** @internal */
class ContentQueryTest extends TestCase
{
    #[DataProvider('queryCases')]
    public function testQueriesReturnOnlyTheRequestedPublicSlice(string $query, array $titles, int $page): void
    {
        $response = $this->get('/articles?'.$query, $this->headers());

        $response->assertOk()->assertJsonPath('props.pagination.currentPage', $page)
            ->assertJsonPath('props.entries', fn (array $entries): bool => array_column($entries, 'title') === $titles);
        $this->assertLessThanOrEqual(20, count($response->json('props.entries')));
    }

    /** @return array<string, array{string, array, int}> */
    public static function queryCases(): array
    {
        return [
            'search and oldest page two' => ['q=E2E%20article&sort=oldest&page=2', ['E2E article 21', 'E2E article 22'], 2],
            'taxonomy and title' => ['topic=architecture&sort=title', ['E2E article 21', 'E2E article 22'], 1],
            'clamped taxonomy page' => ['topic=culture&page=999', ['E2E article 22'], 1],
            'unknown taxonomy' => ['topic=missing', [], 1],
            'unpublished taxonomy' => ['topic=unpublished-topic-secret', [], 1],
            'empty search' => ['q=unmatched', [], 1],
        ];
    }

    public function testInvalidQueryShapesUseServerDefaults(): void
    {
        $this->get('/articles?q[]=invalid&sort[]=title&page[]=9&topic[]=secret', $this->headers())
            ->assertOk()->assertJsonPath('props.filters', ['q' => '', 'topic' => '', 'sort' => 'newest'])
            ->assertJsonPath('props.pagination.currentPage', 1)->assertJsonCount(20, 'props.entries');
    }

    public function testContentUsesNativeValuesAndExplicitPublicKeys(): void
    {
        $fixtures = json_decode(file_get_contents(getenv('POC_FIXTURES_PATH')), true, flags: JSON_THROW_ON_ERROR);
        $content = $fixtures['content'];
        $response = $this->get(parse_url($content['article']['url'], PHP_URL_PATH), $this->headers());
        $response->assertOk()->assertJsonPath('props.entry.image', $content['image'])
            ->assertJsonPath('props.entry.richText', $content['richText'])
            ->assertJsonPath('props.globals.tagline', $content['tagline'])
            ->assertJsonCount(1, 'props.entry.related')->assertJsonCount(2, 'props.entry.topics')
            ->assertJsonCount(2, 'props.entry.blocks')->assertJsonCount(1, 'props.entry.blocks.1.children');
        $this->assertEqualsCanonicalizing(['id', 'title', 'url', 'postDate', 'summary', 'body', 'richText', 'related', 'topics', 'image', 'blocks'], array_keys($response->json('props.entry')));
        $this->assertStringNotContainsString('internalNote', $response->getContent());
        $this->assertStringNotContainsString('javascript:', $response->json('props.entry.richText'));
    }

    public function testMissingSectionReturnsAnEmptyPageWithTheSameContract(): void
    {
        config(['site-content.articles.section' => 'missingSection']);

        $this->get('/articles?page=99', $this->headers())->assertOk()
            ->assertJsonPath('props.section.exists', false)->assertJsonPath('props.entries', [])
            ->assertJsonPath('props.pagination', ['currentPage' => 1, 'lastPage' => 1, 'total' => 0, 'prevUrl' => null, 'nextUrl' => null]);
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Inertia-Version' => Vite::manifestHash()];
    }
}
