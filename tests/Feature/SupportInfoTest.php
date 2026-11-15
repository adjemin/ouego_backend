<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\TestCase;

class SupportInfoTest extends TestCase
{
    /** @test */
    public function it_returns_support_contacts_and_legal_links_without_authentication()
    {
        Setting::create(['name' => 'SUPPORT_EMAIL', 'value' => 'support@example.com']);
        Setting::create(['name' => 'SUPPORT_PHONE', 'value' => '+2250700000000']);
        Setting::create(['name' => 'SUPPORT_WHATSAPP', 'value' => '+2250700000001']);
        Setting::create(['name' => 'PRIVACY_POLICY_URL', 'value' => 'https://example.com/privacy']);
        Setting::create(['name' => 'TERMS_OF_USE_URL', 'value' => 'https://example.com/terms']);

        $this->getJson('/api/v1/support')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'support@example.com',
                    'phone' => '+2250700000000',
                    'whatsapp' => '+2250700000001',
                    'privacy_policy_url' => 'https://example.com/privacy',
                    'terms_of_use_url' => 'https://example.com/terms',
                ],
            ]);
    }

    /** @test */
    public function missing_settings_are_returned_as_null()
    {
        $this->getJson('/api/v1/support')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Informations du support récupérées avec succès',
                'data' => [
                    'email' => null,
                    'phone' => null,
                    'whatsapp' => null,
                    'privacy_policy_url' => null,
                    'terms_of_use_url' => null,
                ],
            ]);
    }
}
