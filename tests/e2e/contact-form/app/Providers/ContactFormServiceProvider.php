<?php

namespace App\Providers;

use App\Mail\CaptureTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/** Registers only the disposable fixture's local mail boundary. */
class ContactFormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('capture', fn() => new CaptureTransport());
        // This private contact fixture sends no page or flash state to SSR.
        Inertia::disableSsr(true);
    }
}
