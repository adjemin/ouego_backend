<?php

namespace App\Notifications;

use App\Models\TripRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RideRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tripRequest;
    /**
     * Create a new notification instance.
     */
    public function __construct(TripRequest $tripRequest)
    {
        $this->tripRequest = $tripRequest;
    }
    

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail']; // 'database', 'fcm' , 'database', 'broadcast'
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('Une nouvelle course vous a été attribuée. Cliquez pour accepter.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }


  
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
           'trip_request_id' => $this->tripRequest->id,
            'message' => 'Une nouvelle course vous a été attribuée. Cliquez pour accepter.',
            'expires_in' => 15
        ];
    }
}
