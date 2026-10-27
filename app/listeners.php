<?php

declare(strict_types=1);

/**
 * Cartographie événements => listeners (extends par les modules).
 *
 * @return array<class-string,list<class-string>>
 */
return [
    \Modules\Payroll\Events\PayslipValidated::class => [
        \Modules\Payroll\Listeners\GeneratePayslipDocument::class,
    ],
    \Modules\Invoicing\Events\InvoiceIssued::class => [
        \Modules\Invoicing\Listeners\GenerateInvoiceDocument::class,
    ],
    \Modules\Subscriptions\Events\SubscriptionInvoiceGenerated::class => [
        \Modules\Subscriptions\Listeners\LogRecurringRevenue::class,
    ],
];
