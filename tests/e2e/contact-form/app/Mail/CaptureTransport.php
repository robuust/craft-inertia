<?php

namespace App\Mail;

use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/** Local transport after native rendering; never opens a socket or process. */
class CaptureTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $directory = storage_path('mail-capture');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create local mail capture.');
        }
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '.eml';
        if (file_put_contents($path, $message->toString(), LOCK_EX) === false) {
            throw new RuntimeException('Cannot capture rendered mail.');
        }
        chmod($path, 0600);
    }

    public function __toString(): string
    {
        return 'local-capture';
    }
}
