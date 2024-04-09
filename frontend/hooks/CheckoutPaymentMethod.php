<?php

namespace Plugin\MonduPayment\Hooks;

use JTL\Cache\JTLCacheInterface;
use JTL\Plugin\Helper;
use Exception;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\Session\Frontend;
use JTL\Smarty\JTLSmarty;
use Plugin\MonduPayment\Src\Services\ConfigService;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;
use Plugin\MonduPayment\Src\Helpers\TranslationHelper;

class CheckoutPaymentMethod
{
    private ConfigService $configService;
    private JTLSmarty $smarty;
    private JTLCacheInterface $cache;
    private Debugger $debugger;
    private MonduClient $monduClient;
    private ?PluginInterface $plugin;

    public function __construct() {
        $this->configService = new ConfigService(); 
        $this->smarty = Shop::Smarty();
        $this->cache = Shop::Container()->getCache();
        $this->monduClient = new MonduClient();
        $this->debugger = new Debugger();
        $this->plugin = Helper::getPluginById('MonduPayment');
    }

    /**
     * @param array $args_arr
     *
     * @throws Exception
     */
    public function execute(array $args_arr = []): void
    {
        unset($_SESSION['monduOrderUuid']);
        unset($_SESSION['monduCartHash']);

        $this->filterPaymentMethods();

        if (!$this->isPaymentGroupingEnabled()){
            return;
        }

        $this->createMonduGroups();
    }

    /**
     * @return void
     */
    private function filterPaymentMethods(): void
    {
        $allowedPaymentMethodsCache = $this->cache->get('mondu_payment_methods');
        $allowedPaymentMethods = $allowedPaymentMethodsCache ?: $this->getAllowedPaymentMethods();
      
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

    /**
     * @return bool
     */
    private function isPaymentGroupingEnabled(): bool
    {
        $groupEnabled = $this->configService->getPaymentMethodGroupEnabled() == '1';
        $paymentMethodNameVisible = $this->configService->getPaymentMethodNameVisible() == '1';
        
        $this->smarty->assign('paymentMethodGroupEnabled', $groupEnabled);
        $this->smarty->assign('paymentMethodNameVisible', $paymentMethodNameVisible);

        return $groupEnabled;
    }

    /**
     * @param $original
     *
     * @return string
     */
    private function __translate($original): string
    {
      $getText   = Shop::Container()->getGetText();
      $oldLocale = $getText->getLanguage();
      $locale    = TranslationHelper::getLocaleFromISO(Shop::Lang()->getIso());
      
      $getText->setLanguage($locale);
      $translation = \__($original);
      $getText->setLanguage($oldLocale);

      return $translation;
    }

    /**
     * @return void
     */
    private function createMonduGroups(): void
    {
        $availablePaymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');
        
        $allowedNetTermsCache = $this->cache->get('mondu_net_terms');
        $netTerms = $allowedNetTermsCache ?: $this->getAllowedNetTerms();

        $monduGroups = [];

        // Config
        $benefits = $this->configService->getBenefitsText();
        $netTermTitle = $this->configService->getNetTermTitle();
        $netTermDescription = $this->configService->getNetTermDescription();

        // Invoice & Direct Debit 
        foreach ($netTerms as $netTerm) {
            $paymentMethods = array_filter($availablePaymentMethods, function ($method) use ($netTerm) {
                return $method->cAnbieter == 'Mondu' && str_contains( $method->cModulId, $netTerm . 'tagen' );
            });

            foreach ($paymentMethods as $method) {
                $paymentMethodType     = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                $method->monduBenefits = str_replace('{net_term}', $netTerm, $this->__translate($benefits[$paymentMethodType]));
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
          return $method->cAnbieter == 'Mondu' && str_contains($method->cModulId, 'monduratenzahlung');
        });

        foreach ($installmentPaymentMethods as $method) {
          $paymentMethodType     = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
          $method->monduBenefits = $this->__translate($benefits[$paymentMethodType]);
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

    /**
     * @return array|string[]
     */
    private function getAllowedPaymentMethods()
    {
        try {
            $apiAllowedPaymentMethods = $this->monduClient->getPaymentMethods();

            if (!isset($apiAllowedPaymentMethods['payment_methods'])){
                $this->debugger->log('[ERROR]: Get Payment Methods request failed.');

                $this->setMonduPaymentMethodsCache(['error']);
                return ['error'];
            } 

            $allowedPaymentMethods = array_map(function ($method) {
              return $method['identifier'];
            }, (array) $apiAllowedPaymentMethods['payment_methods']);

            $this->setMonduPaymentMethodsCache($allowedPaymentMethods);

            return $allowedPaymentMethods;

        } catch (Exception $e) {
            $this->debugger->log('[ERROR]: Get Allowed Payment Methods failed with exception: ' . $e->getMessage());

            $this->setMonduPaymentMethodsCache(['error']);
            return ['error'];
        }
    }

    /**
     * @return array
     */
    private function getAllowedNetTerms(): array
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
            }, (array) $allowedNetTerms['payment_terms'])));

        $this->setMonduNetTermsCache($netTerms);

        return $netTerms;

        } catch (Exception $e) {
            $this->debugger->log('[ERROR]: Get Allowed Net Terms failed with exception: ' . $e->getMessage());

            $this->setMonduNetTermsCache([]);
            return [];
        }
    }

    /**
     * @return string
     */
    public function getBuyerCountryCode(): string
    {
        $customer = Frontend::getCustomer();

        return $customer->cLand;
    }

    /**
     * @param $methods
     *
     * @return void
     */
    public function setMonduPaymentMethodsCache($methods): void
    {
        $this->cache->set('mondu_payment_methods', $methods, ['mondu'], 3600);
    }

    /**
     * @param $methods
     *
     * @return void
     */
    public function setMonduNetTermsCache($methods): void
    {
        $this->cache->set('mondu_net_terms', $methods, ['mondu'], 3600);
    }
}

$hook = new CheckoutPaymentMethod();
$hook->execute();