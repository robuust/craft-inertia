<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['frontend.enabled' => true]);
        Http::preventStrayRequests();
    }

    public function testContactPagesNeverReachSsrOrExposeSettingsAndSubmissionData(): void
    {
        $response = $this->get('/contact', $this->inertiaHeaders());
        $response->assertOk()->assertJsonPath('component', 'Contact')
            ->assertJsonPath('props.initial', ['fromName' => '', 'fromEmail' => '', 'subject' => '', 'message' => ''])
            ->assertJsonMissingPath('props.settings')->assertJsonMissingPath('props.structuredData')
            ->assertDontSee('inbox@contact.test')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(url('/contact/thanks'), Crypt::decrypt($response->json('props.redirect')));

        $this->withSession(['_old_input' => ['fromName' => 'private-person', 'unexpected' => 'private-extra']])
            ->get('/contact')->assertOk()->assertSee('private-person')->assertDontSee('private-extra');
        $this->get('/contact/thanks', $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('component', 'ContactThanks')->assertJsonMissingPath('props.initial')
            ->assertDontSee('private-person');
        Http::assertNothingSent();
    }

    public function testNativeValidationReturnsFieldErrorsAndGlobalFlashWithoutMail(): void
    {
        Cache::flush();
        $before = $this->captures();
        $this->from('/contact')->post('/actions/contact-form/send', [
            'fromName' => 'Keep my name', 'fromEmail' => 'invalid', 'subject' => 'Keep my subject', 'message' => '',
        ], ['Accept' => 'text/html', 'X-Inertia' => 'true', 'X-Inertia-Error-Bag' => 'contact'])
            ->assertRedirect('/contact')->assertSessionHasErrors(['fromEmail', 'message'])
            ->assertSessionHasInput('subject', 'Keep my subject');
        $this->get('/contact', $this->inertiaHeaders() + ['X-Inertia-Error-Bag' => 'contact'])
            ->assertOk()->assertJsonStructure(['props' => ['errors' => ['contact' => ['fromEmail', 'message']]]])
            ->assertJsonPath('flash.error', 'There was a problem with your submission, please check the form and try again!');
        $this->assertSame($before, $this->captures());
    }

    public function testNativeMailRendersAndHandsOffWithServerRecipientAndSubmittedReplyTo(): void
    {
        Cache::flush();
        $before = $this->captures();
        $this->from('/contact')->post('/actions/contact-form/send', [
            'fromName' => 'Native Visitor', 'fromEmail' => 'visitor@contact.test',
            'subject' => 'Boundary subject', 'message' => 'Boundary body with <script>private</script>',
            'toEmail' => 'attacker@contact.test', 'to' => 'attacker@contact.test',
            'redirect' => Crypt::encrypt(url('/contact/thanks')),
        ], ['Accept' => 'text/html', 'X-Inertia' => 'true'])
            ->assertRedirect('/contact/thanks')->assertSessionHasNoErrors();
        $files = array_values(array_diff($this->captures(), $before));
        $this->assertCount(1, $files);
        $mail = file_get_contents($files[0]);
        $this->assertStringContainsString('To: inbox@contact.test', $mail);
        $this->assertStringContainsString('Reply-To: On behalf of Native Visitor <visitor@contact.test>', $mail);
        $this->assertStringContainsString('Subject: Contact fixture - Boundary subject', $mail);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $mail);
        $this->assertStringContainsString('Boundary body', $mail);
        $this->assertStringNotContainsString('attacker@contact.test', $mail);
        $this->get('/contact/thanks', $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('flash.success', 'Your message has been sent.')
            ->assertDontSee('visitor@contact.test')->assertDontSee('Boundary body');
    }

    public function testNativeSettingsRejectAttachmentsWithoutMail(): void
    {
        Cache::flush();
        $before = $this->captures();
        $this->from('/contact')->post('/actions/contact-form/send', [
            'fromEmail' => 'visitor@contact.test', 'message' => 'Body',
            'attachment' => UploadedFile::fake()->create('local.txt', 1, 'text/plain'),
        ], ['Accept' => 'text/html'])->assertRedirect('/contact')->assertSessionHasErrors('attachment');
        $this->assertSame($before, $this->captures());
    }

    public function testNativeOptionalNameAndSubjectCanBeOmitted(): void
    {
        Cache::flush();
        $before = $this->captures();
        $this->from('/contact')->post('/actions/contact-form/send', [
            'fromEmail' => 'minimal@contact.test', 'message' => 'Minimal native body',
        ], ['Accept' => 'text/html'])->assertRedirect('/contact')->assertSessionHasNoErrors();
        $files = array_values(array_diff($this->captures(), $before));
        $this->assertCount(1, $files);
        $mail = file_get_contents($files[0]);
        $this->assertStringContainsString('Subject: Contact fixture', $mail);
        $this->assertStringContainsString('minimal@contact.test', $mail);
        $this->assertStringContainsString('Minimal native body', $mail);
    }

    public function testConfiguredNativeThrottleRejectsFourthAttemptWithoutMail(): void
    {
        Cache::flush();
        $before = $this->captures();
        for ($i = 0; $i < 3; $i++) {
            $this->from('/contact')->post('/actions/contact-form/send', [], ['Accept' => 'text/html'])->assertRedirect('/contact');
        }
        $response = $this->post('/actions/contact-form/send', [], ['Accept' => 'text/html']);
        $response->assertStatus(429)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $this->captures());
    }

    public function testMailConfigurationHasNoExternalTransportEvenWithUnknownMailer(): void
    {
        $this->assertSame(['capture'], array_keys(config('mail.mailers')));
        $this->assertSame('capture', config('mail.default'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailer [smtp] is not defined.');
        Mail::mailer('smtp');
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Inertia-Version' => app(\Illuminate\Foundation\Vite::class)->manifestHash(), 'Accept' => 'text/html'];
    }

    /** @return list<string> */
    private function captures(): array
    {
        return glob(storage_path('mail-capture/*.eml')) ?: [];
    }
}
