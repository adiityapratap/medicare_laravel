<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class HomeworkCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    private  $emailContent = '';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($emailContent)
    {
        $this->emailContent = $emailContent;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_NO_REPLY_ADDRESS','info@sprinkleway.com'))
            ->subject($this->emailContent['subject'])
            ->markdown('emails.homeworks.homeworkCreated')
            ->with([
                'emailBody' => $this->emailContent['body']
            ]);
    }
}
