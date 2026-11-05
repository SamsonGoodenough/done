<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Encrypted while queued (ShouldBeEncrypted): the mailable carries the raw,
 * pre-hash invitation token (see Invitation::generateRawToken()), which would
 * otherwise sit in plaintext in the jobs/failed_jobs table until a worker
 * picks it up.
 */
class InvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken,
    ) {}

    public function build(): self
    {
        return $this->subject('You\'re invited to Done')
            ->view('emails.invitation', [
                'invitation' => $this->invitation,
                'inviteUrl' => rtrim(config('app.url'), '/').'/invite/'.$this->rawToken,
            ]);
    }
}
