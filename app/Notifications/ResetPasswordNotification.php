<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $url = $frontendUrl . '/login?mode=reset&token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('MockChat - I-reset ang iyong Password')
            ->greeting('Kumusta!')
            ->line('Natanggap namin ang request mo para i-reset ang iyong password.')
            ->action('I-reset ang Password', $url)
            ->line('Ang link na ito ay mag-e-expire sa loob ng 60 minuto.')
            ->line('Kung hindi ka nag-request nito, huwag pansinin ang email na ito.')
            ->salutation('Salamat, MockChat Team');
    }
}
