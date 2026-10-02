<?php

namespace App\Domain\User\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu WebTheThao')
            ->greeting('Xin chào '.($notifiable->name ?? '').',')
            ->line('Bạn nhận được email này vì có yêu cầu đặt lại mật khẩu cho tài khoản này.')
            ->action('Đặt lại mật khẩu', $url)
            ->line('Liên kết có hiệu lực trong 60 phút.')
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này. Mật khẩu hiện tại vẫn giữ nguyên.');
    }
}
