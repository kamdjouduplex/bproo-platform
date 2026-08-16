<?php

namespace Tests\Unit\School;

use PHPUnit\Framework\TestCase;
use School\Support\SchoolLocaleCatalog;
use School\Support\Sms\HttpWebhookSmsGateway;
use School\Support\Sms\TwilioSmsGateway;
use School\Support\StudentPhotoStorage;

class SchoolProductHelpersTest extends TestCase
{
    public function test_locale_catalog_includes_french_default_set(): void
    {
        $keys = SchoolLocaleCatalog::keys();
        $this->assertContains('fr', $keys);
        $this->assertContains('en', $keys);
        $this->assertContains('ar', $keys);
    }

    public function test_http_sms_gateway_skips_when_not_configured(): void
    {
        $gateway = new HttpWebhookSmsGateway('', '');
        $this->assertFalse($gateway->configured());
        $result = $gateway->send('+237670000000', 'Hello');
        $this->assertTrue($result['skipped'] ?? false);
        $this->assertFalse($result['ok']);
    }

    public function test_twilio_sms_gateway_skips_when_not_configured(): void
    {
        $gateway = new TwilioSmsGateway('', '', '');
        $this->assertFalse($gateway->configured());
        $result = $gateway->send('+237670000000', 'Hello');
        $this->assertTrue($result['skipped'] ?? false);
    }

    public function test_student_photo_url_passthrough_for_absolute_urls(): void
    {
        $this->assertSame(
            'https://cdn.example/photo.jpg',
            StudentPhotoStorage::url('https://cdn.example/photo.jpg')
        );
        $this->assertNull(StudentPhotoStorage::url(null));
    }

    public function test_role_catalog_defines_three_operational_roles(): void
    {
        $names = array_column(\School\Support\SchoolRoleCatalog::defaultRoles(), 'name');
        $this->assertSame(['directeur', 'enseignant', 'caissier'], $names);

        $directeur = \School\Support\SchoolRoleCatalog::defaultRoles()[0]['permissions'];
        $this->assertContains('school_publications.approve', $directeur);
        $this->assertContains('school_payments.verify', $directeur);

        $enseignant = \School\Support\SchoolRoleCatalog::defaultRoles()[1]['permissions'];
        $this->assertContains('school_exams.marks', $enseignant);
        $this->assertNotContains('school_payments.verify', $enseignant);

        $caissier = \School\Support\SchoolRoleCatalog::defaultRoles()[2]['permissions'];
        $this->assertContains('school_payments.verify', $caissier);
        $this->assertNotContains('school_exams.marks', $caissier);
    }
}
