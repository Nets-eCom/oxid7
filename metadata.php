<?php

$sMetadataVersion = '2.1';

$aModule = [
    'id' => 'nexi-checkout',
    'title' => 'Nexi Checkout',
    'version' => '1.0.0',
    'author' => 'Nexi Checkout',
    'url' => '', // @todo
    'email' => '', // @todo
    'thumbnail' => 'nexi-checkout_logo_medium.png',
    'description' => [
        'de' => 'Nexi Checkout einfach sicher zahlen',
        'en' => 'Nexi Checkout safe online payments'
    ],
    'extend' => [
        \OxidEsales\Eshop\Application\Controller\Admin\OrderOverview::class => NexiCheckout\Extend\Application\Controller\Admin\OrderOverview::class,
        \OxidEsales\Eshop\Application\Controller\PaymentController::class => NexiCheckout\Extend\Application\Controller\PaymentController::class,
        \OxidEsales\Eshop\Application\Controller\OrderController::class => NexiCheckout\Extend\Application\Controller\OrderController::class,
        \OxidEsales\Eshop\Application\Controller\ThankYouController::class => NexiCheckout\Extend\Application\Controller\ThankyouController::class,
        \OxidEsales\Eshop\Application\Model\Order::class => NexiCheckout\Extend\Application\Model\Order::class,
        \OxidEsales\Eshop\Application\Model\Payment::class => NexiCheckout\Extend\Application\Model\Payment::class,
        \OxidEsales\Eshop\Application\Model\PaymentGateway::class => NexiCheckout\Extend\Application\Model\PaymentGateway::class,
    ],
    'settings' => [
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_merchant_id',          'type' => 'str',    'value' => '',],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_blMode',               'type' => 'select', 'value' => '0', 'constraints' => '0|1'],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_secret_key_live',      'type' => 'str',    'value' => ''],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_checkout_key_live',    'type' => 'str',    'value' => ''],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_secret_key_test',      'type' => 'str',    'value' => ''],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_checkout_key_test',    'type' => 'str',    'value' => ''],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_terms_url',            'type' => 'str',    'value' => 'https://mysite.com/index.php?cl=content&oxloadid=oxagb'],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_merchant_terms_url',   'type' => 'str',    'value' => 'https://cdn.dibspayment.com/terms/easy/terms_of_use.pdf'],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_checkout_mode',        'type' => 'select', 'value' => 'hosted', 'constraints' => 'embedded|hosted'],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_autocapture',          'type' => 'bool',   'value' => 'false'],
        ['group' => 'nexi_checkout_main', 'name' => 'nexi_checkout_blDebug_log',          'type' => 'bool',   'value' => 'false'],
    ],
    'events' => [
        'onActivate' => '\NexiCheckout\Core\Events::onActivate',
        'onDeactivate' => '\NexiCheckout\Core\Events::onDeactivate'
    ]
];
