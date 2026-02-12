<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\CampaignStatus;
use App\Models\Attachment;
use App\Models\Campaign;
use App\Models\CampaignVisit;
use App\Models\Organization;
use App\Notifications\NewApplicationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PublicCampaignController extends Controller
{
    public function show(Request $request, string $orgSlug, string $campaignSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();
        $campaignQuery = $organization->campaigns()
            ->where('slug', $campaignSlug);

        if (! $this->canPreviewOrganizationCampaigns($organization)) {
            $campaignQuery->where('status', CampaignStatus::Published);
        }

        $campaign = $campaignQuery->firstOrFail();

        if ($this->isPublishedCampaign($campaign)) {
            DB::table('campaigns')
                ->where('id', $campaign->id)
                ->increment('views_count');
            $campaign->views_count++;

            $tracking = $this->resolveTrackingData($request, $campaign->id);
            $request->session()->put("campaign_tracking.{$campaign->id}", $tracking);

            CampaignVisit::create([
                'campaign_id' => $campaign->id,
                'source' => $tracking['source'],
                'source_medium' => $tracking['source_medium'],
                'source_campaign' => $tracking['source_campaign'],
                'referrer_url' => $tracking['referrer_url'],
                'session_id' => $request->session()->getId(),
            ]);
        }

        return view('campaigns.show', [
            'organization' => $organization,
            'campaign' => $campaign,
        ]);
    }

    public function apply(Request $request, string $orgSlug, string $campaignSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();
        $campaign = $organization->campaigns()
            ->where('slug', $campaignSlug)
            ->where('status', CampaignStatus::Published)
            ->firstOrFail();
        $tracking = $this->resolveTrackingData($request, $campaign->id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'cover_letter_text' => ['nullable', 'string'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'cover_letter' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ], [
            'first_name.required' => 'Bitte geben Sie Ihren Vornamen ein.',
            'last_name.required' => 'Bitte geben Sie Ihren Nachnamen ein.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
            'phone.max' => 'Die Telefonnummer ist zu lang.',
            'linkedin_url.url' => 'Bitte geben Sie eine gueltige LinkedIn-URL ein.',
            'portfolio_url.url' => 'Bitte geben Sie eine gueltige Portfolio-URL ein.',
            'resume.required' => 'Bitte laden Sie Ihren Lebenslauf hoch.',
            'resume.mimes' => 'Der Lebenslauf muss eine PDF-, DOC- oder DOCX-Datei sein.',
            'resume.max' => 'Der Lebenslauf darf maximal 10 MB gross sein.',
            'cover_letter.required' => 'Bitte laden Sie Ihr Anschreiben hoch.',
            'cover_letter.mimes' => 'Das Anschreiben muss eine PDF-, DOC- oder DOCX-Datei sein.',
            'cover_letter.max' => 'Das Anschreiben darf maximal 10 MB gross sein.',
        ]);

        $application = null;

        DB::transaction(function () use ($campaign, $validated, $request, $tracking, &$application) {
            $application = $campaign->applications()->create([
                'status' => ApplicationStatus::New,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'portfolio_url' => $validated['portfolio_url'] ?? null,
                'cover_letter_text' => $validated['cover_letter_text'] ?? null,
                'source' => $tracking['source'],
                'source_medium' => $tracking['source_medium'],
                'source_campaign' => $tracking['source_campaign'],
                'referrer_url' => $tracking['referrer_url'],
            ]);

            $application->recordActivity('submitted', null, [
                'source' => $tracking['source'],
                'medium' => $tracking['source_medium'] ?? '-',
                'campaign' => $tracking['source_campaign'] ?? '-',
            ]);

            $resumeFile = $request->file('resume');
            $resumePath = $resumeFile->storeAs(
                "applications/{$application->id}",
                'resume.' . $resumeFile->getClientOriginalExtension(),
                'local'
            );

            Attachment::create([
                'application_id' => $application->id,
                'type' => 'resume',
                'original_name' => $resumeFile->getClientOriginalName(),
                'mime_type' => $resumeFile->getClientMimeType(),
                'size_bytes' => $resumeFile->getSize(),
                'storage_path' => $resumePath,
            ]);

            $coverLetterFile = $request->file('cover_letter');
            $coverLetterPath = $coverLetterFile->storeAs(
                "applications/{$application->id}",
                'cover-letter.' . $coverLetterFile->getClientOriginalExtension(),
                'local'
            );

            Attachment::create([
                'application_id' => $application->id,
                'type' => 'cover_letter',
                'original_name' => $coverLetterFile->getClientOriginalName(),
                'mime_type' => $coverLetterFile->getClientMimeType(),
                'size_bytes' => $coverLetterFile->getSize(),
                'storage_path' => $coverLetterPath,
            ]);
        });

        Notification::send(
            $organization->admins()->get(),
            new NewApplicationNotification($application)
        );

        return redirect()->route('campaign.thanks', [
            'org_slug' => $organization->slug,
            'campaign_slug' => $campaign->slug,
        ]);
    }

    public function thanks(string $orgSlug, string $campaignSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();
        $campaign = $organization->campaigns()
            ->where('slug', $campaignSlug)
            ->where('status', CampaignStatus::Published)
            ->firstOrFail();

        return view('campaigns.thanks', [
            'organization' => $organization,
            'campaign' => $campaign,
        ]);
    }

    private function resolveTrackingData(Request $request, int $campaignId): array
    {
        $sessionTracking = $request->session()->get("campaign_tracking.{$campaignId}", []);

        $source = $this->normalizeTrackingValue(
            $request->query('utm_source')
            ?? $request->query('source')
            ?? ($sessionTracking['source'] ?? null)
        );
        $sourceMedium = $this->normalizeTrackingValue(
            $request->query('utm_medium')
            ?? ($sessionTracking['source_medium'] ?? null)
        );
        $sourceCampaign = $this->normalizeTrackingValue(
            $request->query('utm_campaign')
            ?? ($sessionTracking['source_campaign'] ?? null)
        );
        $referrerUrl = $this->normalizeTrackingValue(
            $request->headers->get('referer')
            ?? ($sessionTracking['referrer_url'] ?? null)
        );

        return [
            'source' => $source ? mb_strtolower($source) : 'direct',
            'source_medium' => $sourceMedium ? mb_strtolower($sourceMedium) : null,
            'source_campaign' => $sourceCampaign,
            'referrer_url' => $referrerUrl,
        ];
    }

    private function normalizeTrackingValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    private function canPreviewOrganizationCampaigns(Organization $organization): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->organizations()
            ->whereKey($organization)
            ->exists();
    }

    private function isPublishedCampaign(Campaign $campaign): bool
    {
        $status = $campaign->status;

        if ($status instanceof CampaignStatus) {
            return $status === CampaignStatus::Published;
        }

        return (string) $status === CampaignStatus::Published->value;
    }
}
