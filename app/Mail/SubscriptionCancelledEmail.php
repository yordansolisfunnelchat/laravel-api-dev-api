<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Tu suscripción a Ezcala AI ha sido cancelada')
                    ->view('emails.subscription_cancelled')
                    ->with([
                        'name' => $this->name,
                    ]);
    }
}