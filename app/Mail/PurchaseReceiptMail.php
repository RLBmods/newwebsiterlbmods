<?php

namespace App\Mail;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public Purchase $purchase;

    /**
     * Create a new message instance.
     */
    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $user = $this->purchase->user;
        $product = $this->purchase->product;

        return $this->subject('Your RLBmods receipt - ' . $this->purchase->order_id)
            ->view('emails.purchase_receipt', [
                'purchase' => $this->purchase,
                'user' => $user,
                'product' => $product,
            ]);
    }
}

