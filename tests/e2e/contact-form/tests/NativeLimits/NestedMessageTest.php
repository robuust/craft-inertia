<?php

namespace Tests\NativeLimits;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** An intentionally failing native regression, outside the supported scalar suite. */
class NestedMessageTest extends TestCase
{
    public function testUnselectedNestedMessageShouldReturnNativeValidationInsteadOf500(): void
    {
        Cache::flush();
        $before = glob(storage_path('mail-capture/*.eml')) ?: [];
        $response = $this->from('/contact')->post('/actions/contact-form/send', [
            'fromEmail' => 'visitor@contact.test', 'message' => ['unselected' => 'Nested body'],
        ], ['Accept' => 'text/html']);

        $this->assertSame($before, glob(storage_path('mail-capture/*.eml')) ?: []);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertRedirect('/contact')->assertSessionHasErrors('message');
    }
}
