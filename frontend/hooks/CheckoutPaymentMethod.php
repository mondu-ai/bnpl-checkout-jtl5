<?php

namespace Plugin\MonduPayment\Hooks;
use JTL\Plugin\Helper;

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
    private $plugin;


    public function __construct() {
        $this->linkHelper = Shop::Container()->getLinkService();
        $this->configService = new ConfigService(); 
        $this->smarty = Shop::Smarty();
        $this->cache = Shop::Container()->getCache();
        $this->monduClient = new MonduClient();
        $this->debugger = new Debugger();
        $this->plugin = Helper::getPluginById('MonduPayment');
    }
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public function execute($args_arr = []): void
    {
        $this->filterPaymentMethods();

        if (!$this->isPaymentGroupingEnabled()){
          return;
        }

        $this->createMonduGroups();
    }

    private function filterPaymentMethods()
    { 
        $allowedPaymentMethodsCache = $this->cache->get('mondu_payment_methods');
        $allowedPaymentMethods = $allowedPaymentMethodsCache ? $allowedPaymentMethodsCache : $this->getAllowedPaymentMethods();
      
        $paymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');
        $monduPaymentMethods = [];

        foreach ($paymentMethods as $key => $method) {
            if ($method->cAnbieter == 'Mondu') {
                $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                $monduPaymentMethods[$method->kZahlungsart] = $method->cModulId;
                if (!in_array($paymentMethodType, $allowedPaymentMethods)){
                  unset($paymentMethods[$key]);
                }
            }
        }

        $this->smarty->assign('MonduPaymentMethods', $monduPaymentMethods);
        $this->smarty->assign('Zahlungsarten', $paymentMethods);
    }

    private function isPaymentGroupingEnabled()
    {
        $groupEnabled = $this->configService->getPaymentMethodGroupEnabled() == '1';
        
        $this->smarty->assign('paymentMethodGroupEnabled', $groupEnabled);

        return $groupEnabled;
    }

    private function createMonduGroups()
    {
        $availablePaymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');
        
        $allowedNetTermsCache = $this->cache->get('mondu_net_terms');
        $netTerms = $allowedNetTermsCache ? $allowedNetTermsCache : $this->getAllowedNetTerms();

        $monduGroups = [];

        // Config
        $benefits = $this->configService->getBenefitsText();
        $netTermTitle = $this->configService->getNetTermTitle();
        $netTermDescription = $this->configService->getNetTermDescription();

        // Invoice & Direct Debit 
        foreach ($netTerms as $netTerm) {
          $paymentMethods = array_filter($availablePaymentMethods, function ($method) use ($netTerm) {
            return $method->cAnbieter == 'Mondu' && strpos($method->cModulId, $netTerm . 'tagen') !== false;
          });

          foreach ($paymentMethods as $key => $method) {
            $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
            $paymentMethods[$key]->monduBenefits = str_replace('{net_term}', $netTerm, $benefits[$paymentMethodType]);
          }

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

        foreach ($installmentPaymentMethods as $key => $method) {
          $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
          $installmentPaymentMethods[$key]->monduBenefits = $benefits[$paymentMethodType];
        }

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

        $this->smarty->assign('Zahlungsarten', array_filter($availablePaymentMethods, function ($method) {
          return $method->cAnbieter != 'Mondu';
        }));

        $this->smarty->assign('monduFrontendUrl', $this->plugin->getPaths()->getFrontendURL());
        $this->smarty->assign('monduGroups', $monduGroups);
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

    private function getAllowedNetTerms()
    {
      try {
          $allowedNetTerms = $this->monduClient->getNetTerms();

          if(!isset($allowedNetTerms['payment_terms'])){
              $this->debugger->log('[ERROR]: Get Net Terms request failed.');

              $this->setMonduNetTermsCache([]);
              return [];
          } 

          $netTerms = array_unique(array_filter(array_map(function ($paymentMethod) {
            if ($this->getBuyerCountryCode() == $paymentMethod['country_code']){
                return $paymentMethod['net_term'];
            }
          }, $allowedNetTerms['payment_terms'])));

          $this->setMonduNetTermsCache($netTerms);
          
          return $netTerms;

      } catch (Exception $e) {
          $this->debugger->log('[ERROR]: Get Allowed Net Terms failed with exception: ' . $e->getMessage());

          $this->setMonduNetTermsCache([]);
          return [];
      }
    }

    public function getBuyerCountryCode()
    {
        $customer = Frontend::getCustomer();

        return $customer->cLand;
    }

    public function setMonduPaymentMethodsCache($methods)
    {
        $this->cache->set('mondu_payment_methods', $methods, ['mondu'], 3600);
    }

    public function setMonduNetTermsCache($methods)
    {
        $this->cache->set('mondu_net_terms', $methods, ['mondu'], 3600);
    }
}

$hook = new CheckoutPaymentMethod();
$hook->execute();