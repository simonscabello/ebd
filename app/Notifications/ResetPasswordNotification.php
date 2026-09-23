<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de redefinição de senha, enviado pela fila para não travar a requisição
 * (e não expor, pelo tempo de resposta, se o e-mail existe).
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha · EBD')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta na EBD.')
            ->action('Criar nova senha', $this->resetUrl($notifiable))
            ->line("O link vale por {$minutes} minutos.")
            ->line('Se não foi você, pode ignorar este e-mail com tranquilidade.')
            ->salutation('Um abraço, '.config('ebd.church_name'));
    }
}
