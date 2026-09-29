<?php

namespace App\Services\Payments;

use App\Models\SubscriptionInvoice;

interface PaymentGateway
{
    public function isConfigured(): bool;

    /** Returns a hosted checkout URL for the invoice (no card data ever touches Vector7). */
    public function checkoutUrl(SubscriptionInvoice $invoice): string;

    /** Constant-time verification of the webhook signature. */
    public function verifyWebhook(string $payload, ?string $signature): bool;
}
