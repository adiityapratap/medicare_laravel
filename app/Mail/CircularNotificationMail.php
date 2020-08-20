<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CircularNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private  $emailContent = '';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $subject, string $emailContent)
    {
        $this->emailContent = $emailContent;
        $this->subject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_NO_REPLY_ADDRESS','info@sprinkleway.com'))
            ->subject($this->subject)
            ->markdown('emails.circular.notification')
            ->with([
                'emailContent' => $this->emailContent
            ]);
    }
}
