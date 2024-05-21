<?php

namespace Plugin\MonduPayment\Src\Services;

use JTL\Plugin\Helper;


class ConfigService
{
    const API_DEVELOPMENT_URL = 'http://localhost:3000/api/v1/';
    const API_SANDBOX_URL = 'https://api.demo.mondu.ai/api/v1/';
    const API_PRODUCTION_URL = 'https://api.mondu.ai/api/v1/';

    const WIDGET_DEVELOPMENT_URL = 'http://localhost:3002/dist/widget.js';
    const WIDGET_SANDBOX_URL = 'https://checkout.demo.mondu.ai/widget.js';
    const WIDGET_PRODUCTION_URL = 'https://checkout.mondu.ai/widget.js';

    const AUTHORIZATION_FLOW = 'authorization_flow';
    const CONFIRMATION_FLOW = 'confirmation_flow';

    private $config;
    private $plugin;

    private static $instances = [];

    public function __construct()
    {
        $this->plugin = Helper::getPluginById('MonduPayment');
        $this->config = $this->plugin->getConfig();
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getPluginVersion() {
        return $this->plugin->getCurrentVersion()->getOriginalVersion();
    }

    public function getPluginName() {
        return "mondu_payment_jtl5";
    }

    public function getSandboxMode()
    {
        return $this->config->getValue('sandbox_mode') == '1';
    }

    public function getApiSecret()
    {
        return $this->config->getValue('api_secret');
    }

    public function getWebhooksSecret()
    {
        return $this->config->getValue('webhooks_secret');
    }

    public function shouldMarkOrderAsPaid()
    {
        return $this->config->getValue('mark_order_as_paid') == '1';
    }

    public function getPaymentMethodGroupEnabled()
    {
        return $this->config->getValue('payment_method_group_enabled');
    }

    public function getPaymentMethodNameVisible()
    {
        return $this->config->getValue('payment_method_name_visible');
    }

    public function getNetTermTitle()
    {
        return $this->config->getValue('net_term_title');
    }

    public function getNetTermDescription()
    {
        return $this->config->getValue('net_term_description');
    }

    public function getPaymentMethodByKPlugin($kPlugin)
    {
        return $this->config->getValue($kPlugin . '_payment_method');
    }

    public function getNetTermByKPlugin($kPlugin)
    {
        return $this->config->getValue($kPlugin . '_net_term');
    }

    public function getApiUrl()
    {
        if ($this->getSandboxMode()) {
            return self::API_SANDBOX_URL;
        }

        return self::API_PRODUCTION_URL;
    }

    public function getWidgetUrl()
    {
        if ($this->getSandboxMode()) {
            return self::WIDGET_SANDBOX_URL;
        }

        return self::WIDGET_PRODUCTION_URL;
    }

    public function getBenefitsText()
    {
        return [
            'invoice' => $this->getConfigurationDescription('invoice_benefits'),
            'direct_debit' => $this->getConfigurationDescription('sepa_benefits'),
            'installment' => $this->getConfigurationDescription('installments_benefits')
        ];

    }

    public function getConfigurationDescription($key)
    {
        return $this->config->getOption($key)->description;
    }

    public function getOrderFlow()
    {
        return self::AUTHORIZATION_FLOW;
    }

    public function getPaymentMethodNetTerm($method)
    {
        return $this->config->getValue($method.'_net_term');
    }

    public static function getInstance() {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }
}
