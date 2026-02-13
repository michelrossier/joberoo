<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment)
    {
        $attachment->load('application.campaign.organization');

        $organization = $attachment->application->campaign->organization;
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && ! $user->organizations()->whereKey($organization)->exists()) {
            abort(403);
        }

        $attachment->application->recordActivity('attachment_downloaded', null, [
            'type' => $attachment->type,
            'file' => $attachment->original_name,
        ], $user->id);

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->original_name
        );
    }
}
