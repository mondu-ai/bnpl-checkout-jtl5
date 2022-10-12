<?php

namespace Plugin\MonduPayment\Src\Services;

use JTL\Plugin\Helper;


class ConfigService
{
    const API_SANDBOX_URL = 'http://localhost:3000/api/v1/';
    const API_SANDBOX_URLs = 'https://api.demo.mondu.ai/api/v1/';
    const API_PRODUCTION_URL = 'https://api.mondu.ai/api/v1/';

    const WIDGET_SANDBOX_URL = 'http://localhost:3002/dist/widget.js';
    const WIDGET_SANDBOX_URLs = 'https://checkout.demo.mondu.ai/widget.js';
    const WIDGET_PRODUCTION_URL = 'https://checkout.mondu.ai/widget.js';

    private $config;

    public function __construct()
    {
        $this->config = Helper::getPluginById('MonduPayment')->getConfig();
    }

    public function getConfig()
    {
        return $this->config;
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

    public function getPaymentMethodGroupEnabled()
    {
        return $this->config->getValue('payment_method_group_enabled');
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
}
