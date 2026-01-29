<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LowStockNotification extends Notification
{
    use Queueable;

    public $product;
    public $currentStock;

    public function __construct($product, $currentStock)
    {
        $this->product = $product;
        $this->currentStock = $currentStock;
    }

    public function via($notifiable)
    {
        return ['database']; // add 'mail' later
    }

    public function databaseType(object $notifiable): string
    {
        return 'LowStockNotification';
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Low Stock Alert',
            'message' => "Product '{$this->product->name}' is low on stock. Current: {$this->currentStock}, Min: {$this->product->min_stock}",
            'type' => 'low_stock',
            'url' => '/products/' . $this->product->id,
        ];
    }
}