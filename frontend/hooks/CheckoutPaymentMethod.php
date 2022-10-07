<?php

namespace Plugin\MonduPayment\Hooks;

use Exception;
use JTL\Shop;
use JTL\Link\LinkInterface;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Services\ConfigService;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;

class CheckoutPaymentMethod
{
    private $linkHelper;
    private $configService;
    private $smarty;
    private $cache;
    private Debugger $debugger;
    private MonduClient $monduClient;

    public function __construct() {
        $this->linkHelper = Shop::Container()->getLinkService();
        $this->configService = new ConfigService(); 
        $this->smarty = Shop::Smarty();
        $this->cache = Shop::Container()->getCache();
        $this->monduClient = new MonduClient();
        $this->debugger = new Debugger();
    }
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public function execute($args_arr = []): void
    {
        // TODO: Filter net terms
        $this->filterPaymentMethods();

        if (!$this->isPaymentGroupingEnabled()){
          return;
        }

        $this->assignMonduGroups();
    }

    private function filterPaymentMethods()
    { 
        $allowedPaymentMethodsCache = $this->cache->get('mondu_payment_methods');
        $allowedPaymentMethods = $allowedPaymentMethodsCache ? $allowedPaymentMethodsCache : $this->getAllowedPaymentMethods();

        $paymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');

        foreach ($paymentMethods as $key => $method) {
            if ($method->cAnbieter == 'Mondu') {
                $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                
                if (!in_array($paymentMethodType, $allowedPaymentMethods)){
                  unset($paymentMethods[$key]);
                }
            }
        }

        $this->smarty->assign('Zahlungsarten', $paymentMethods);

    }

    private function getAllowedPaymentMethods()
    {
        try {
            $apiAllowedPaymentMethods = $this->monduClient->getPaymentMethods();

            if(!isset($apiAllowedPaymentMethods['payment_methods'])){
                $this->debugger->log('[ERROR]: Get Payment Methods request failed.');

                $this->setMonduPaymentMethodsCache(['error']);
                return ['error'];
            } 

            $allowedPaymentMethods = array_map(function ($method) {
              return $method['identifier'];
            }, $apiAllowedPaymentMethods['payment_methods']);

            $this->setMonduPaymentMethodsCache($allowedPaymentMethods);

            return $allowedPaymentMethods;

        } catch (Exception $e) {
            $this->debugger->log('[ERROR]: Get Allowed Payment Methods failed with exception: ' . $e->getMessage());

            $this->setMonduPaymentMethodsCache(['error']);
            return ['error'];
        }
    }

    public function setMonduPaymentMethodsCache($methods)
    {
        // TODO: Change expiration to 3600
        $this->cache->set('mondu_payment_methods', $methods, ['mondu'], 15);
    }

    private function isPaymentGroupingEnabled()
    {
        $groupEnabled = $this->configService->getPaymentMethodGroupEnabled() == '1';
        
        $this->smarty->assign('paymentMethodGroupEnabled', $groupEnabled);

        return $groupEnabled;
    }

    private function assignMonduGroups()
    {
        $availablePaymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');
        $netTerms = [14, 30, 45, 60];
        $monduGroups = [];

        // Config
        $netTermTitle = $this->configService->getNetTermTitle();
        $netTermDescription = $this->configService->getNetTermDescription();

        // Invoice & Direct Debit 
        foreach ($netTerms as $netTerm) {
          $paymentMethods = array_filter($availablePaymentMethods, function ($method) use ($netTerm) {
            return $method->cAnbieter == 'Mondu' && strpos($method->cModulId, $netTerm . 'tagen') !== false;
          });

          if (count($paymentMethods) != 0) {
              $monduGroups[] = [
                'title' => str_replace('{net_term}', $netTerm, $netTermTitle),
                'description' => str_replace('{net_term}', $netTerm, $netTermDescription),
                'payment_methods' => $paymentMethods
              ];
          }
        }

        // Installments
        $installmentPaymentMethods = array_filter($availablePaymentMethods, function ($method) {
          return $method->cAnbieter == 'Mondu' && strpos($method->cModulId, 'monduratenzahlung') !== false;
        });

        if (count($installmentPaymentMethods) > 0) {
          $installment = reset($installmentPaymentMethods);

          if (count($installmentPaymentMethods) != 0) {
            $monduGroups[] = [
              'title' => $installment->angezeigterName[$_SESSION['cISOSprache']],
              'description' => $installment->cHinweisText[$_SESSION['cISOSprache']],
              'payment_methods' => $installmentPaymentMethods
            ];
          }
        }

        $this->smarty->assign('monduGroups', $monduGroups);
    }
}

$hook = new CheckoutPaymentMethod();
$hook->execute();