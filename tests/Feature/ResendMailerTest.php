<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailerTest extends TestCase
{
    public function test_resend_mailer_is_available_for_production_email(): void
    {
        // Le SMTP étant bloqué sur l'hébergement gratuit, l'envoi réel passe par l'API HTTP de Resend
        config(['services.resend.key' => 're_test_key']);

        $mailer = Mail::mailer('resend');

        $this->assertInstanceOf(\Illuminate\Mail\Mailer::class, $mailer);
        $this->assertSame('resend', (string) $mailer->getSymfonyTransport());
    }
}
