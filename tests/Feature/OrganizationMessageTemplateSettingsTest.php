<?php

namespace Tests\Feature;

use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationMessageTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_application_status_message_templates_in_organization_settings(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->users()->attach($admin, ['role' => 'admin']);

        $this->actingAs($admin);
        Filament::setTenant($organization, true);

        $templates = [
            [
                'name' => 'Danke',
                'subject' => 'Vielen Dank fuer Ihre Bewerbung',
                'body_html' => '<p>Wir melden uns zeitnah.</p>',
            ],
            [
                'name' => 'Bitte Geduld',
                'subject' => 'Zwischenstand',
                'body_html' => '<p>Bitte haben Sie noch etwas Geduld.</p>',
            ],
        ];

        $editorState = array_map(
            static fn (array $template): array => [
                ...$template,
                'body_html' => RichContentRenderer::make($template['body_html'])->toArray(),
            ],
            $templates,
        );

        Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->set('data.application_status_message_templates', $editorState)
            ->call('save')
            ->assertHasNoErrors();

        $organization->refresh();

        $this->assertSame($templates, $organization->application_status_message_templates);
    }
}
