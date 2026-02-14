<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CompanySignupController;
use App\Http\Controllers\PostmarkWebhookController;
use App\Http\Controllers\PublicCampaignController;
use App\Http\Middleware\VerifyPostmarkWebhookBasicAuth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/company-signup', [CompanySignupController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('company-signup.store');

Route::post('/webhooks/postmark', PostmarkWebhookController::class)
    ->middleware(VerifyPostmarkWebhookBasicAuth::class)
    ->name('webhooks.postmark');

Route::middleware(['auth'])->group(function () {
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])
        ->name('attachments.download');
});

Route::get('/{org_slug}/{campaign_slug}', [PublicCampaignController::class, 'show'])
    ->name('campaign.show');

Route::post('/{org_slug}/{campaign_slug}/apply', [PublicCampaignController::class, 'apply'])
    ->middleware('throttle:applications')
    ->name('campaign.apply');

Route::get('/{org_slug}/{campaign_slug}/thanks', [PublicCampaignController::class, 'thanks'])
    ->name('campaign.thanks');
