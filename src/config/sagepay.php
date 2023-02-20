<?php

return [
    'name' => 'SagePay',
    'key' => 'payment_sagepay',
    'support_subscription' => false,
    'support_ecommerce' => true,
    'support_marketplace' => true,
    'support_online_refund' => true,
    'manage_remote_plan' => false,
    'require_token_confirm' => false,
    'manage_remote_product' => false,
    'manage_remote_sku' => false,
    'manage_remote_order' => false,
    'supports_swap' => false,
    'supports_swap_in_grace_period' => false,
    'require_invoice_creation' => false,
    'require_plan_activation' => false,
    'capture_payment_method' => false,
    'require_default_payment_set' => false,
    'can_update_payment' => false,
    'create_remote_customer' => false,
    'require_payment_token' => false,
    'support_reservation' => false,
    'support_connect_account' => false,
    'settings' => [
        'vendor' => [
            'label' => 'SagePay::labels.settings.vendor',
            'type' => 'text',
            'required' => false,
        ],
        'ip_address' => [
            'label' => 'SagePay::labels.settings.ip_address',
            'type' => 'text',
            'required' => false,
        ],
        'threeds_option' => [
            'label' => 'SagePay::labels.settings.threeds_option',
            'type' => 'select',
            'options' => [
                \Corals\Modules\Payment\SagePay\Message\ConstantsInterface::APPLY_3DSECURE_APPLY => 'Apply',
                \Corals\Modules\Payment\SagePay\Message\ConstantsInterface::APPLY_3DSECURE_FORCE => 'Force'
            ],
            'required' => false,
        ],
        'test_mode' => [
            'label' => 'SagePay::labels.settings.test_mode',
            'type' => 'boolean'
        ],
    ],
    'events' => [
    ],
    'webhook_handler' => \Corals\Modules\Payment\SagePay\Gateway::class,
];
