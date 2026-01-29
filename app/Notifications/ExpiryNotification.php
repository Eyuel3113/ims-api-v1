<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExpiryNotification extends Notification
{
    use Queueable;

    public $stock;

    public function __construct($stock)
    {
        $this->stock = $stock;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'ExpiryNotification';
    }

    public function toArray($notifiable)
    {
        $productName = $this->stock->product->name ?? 'Unknown Product';
        $warehouseName = $this->stock->warehouse->name ?? 'Unknown Warehouse';
        $expiryDate = $this->stock->expiry_date->format('Y-m-d');

        return [
            'title' => 'Stock Expiry Alert',
            'message' => "Batch of '{$productName}' in '{$warehouseName}' is expiring on {$expiryDate}. Quantity: {$this->stock->quantity}",
            'type' => 'expiry_alert',
            'product_id' => $this->stock->product_id,
            'warehouse_id' => $this->stock->warehouse_id,
            'expiry_date' => $expiryDate,
        ];
    }
}
