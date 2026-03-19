<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\TripRequest;

class NoDriverFoundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TripRequest $tripRequest)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('Aucun chauffeur n\'a pu vous trouver à votre commade #' . $this->tripRequest->order_id . '.')
            ->action('Cliquez ici pour réessayer', url('/'))
            ->line('Merci d\'utiliser notre application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Aucun chauffeur n\'a pu vous trouver à votre commade #' . $this->tripRequest->order_id . '.',
            'expires_in' => 15,
            'action' => url('/'),
            'action_text' => 'Cliquez ici pour réessayer'
        ];
    }
}
