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
        $allowedPaymentMethods = $this->getAllowedPaymentMethods();
        $paymentMethods = $this->smarty->getTemplateVars('Zahlungsarten');
        $monduPaymentMethods = [];
        $allowedNetTerms = $this->getAllowedNetTerms();

        foreach ($paymentMethods as $key => $method) {
            if ($method->cAnbieter == 'Mondu') {
                $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                $monduPaymentMethods[$method->kZahlungsart] = $method->cModulId;

                if (!in_array($paymentMethodType, $allowedPaymentMethods)){
                    unset($paymentMethods[$key]);
                    continue;
                }

                // Set localized image based on payment type (for non-grouped mode)
                switch ($paymentMethodType) {
                    case 'invoice':
                        $method->cBild = $this->getPaymentMethodImage('invoice');
                        break;
                    case 'direct_debit':
                        $method->cBild = $this->getPaymentMethodImage('direct_debit');
                        break;
                    case 'installment':
                        $method->cBild = $this->getPaymentMethodImage('installment');
                        break;
                    case 'pay_now':
                        $method->cBild = $this->getPaymentMethodImage('pay_now');
                        break;
                    default:
                        // Keep default image from database
                        break;
                }
                
                $netTerm = (int) $this->configService->getPaymentMethodNetTerm($method->cModulId);

                // Installments and Pay Now don't have net terms - no filtering needed
                if (!$netTerm) {
                    continue;
                }

                // For invoice and direct_debit, check if net term is allowed
                if (!in_array($netTerm, $allowedNetTerms)) {
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
        
        $this->smarty->assign('paymentMethodGroupEnabled', $groupEnabled);
        // Always show payment method name - no configuration needed
        $this->smarty->assign('paymentMethodNameVisible', true);

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
        
        // Temporarily disable cache - always fetch fresh data from API
        $netTerms = $this->getAllowedNetTerms();

        $monduGroups = [];

        // Config
        $benefits = $this->configService->getBenefitsText();

        // Collect all Invoice methods with different net terms
        $invoiceMethods = [];
        $invoiceNetTerms = [];
        
        foreach ($netTerms as $netTerm) {
            $methods = array_filter($availablePaymentMethods, function ($method) use ($netTerm) {
                $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                return $method->cAnbieter == 'Mondu' && 
                       $paymentMethodType === 'invoice' && 
                       str_contains($method->cModulId, $netTerm . 'tage');
            });
            
            foreach ($methods as $method) {
                $method->monduBenefits = str_replace('{net_term}', $netTerm, $this->__translate($benefits['invoice']));
                $method->monduNetTerm = $netTerm; // Store net term for sorting
                $method->cBild = $this->getPaymentMethodImage('invoice'); // Set localized image
                $invoiceMethods[] = $method;
                $invoiceNetTerms[] = $netTerm;
            }
        }

        // Group all Invoice methods together if any exist
        if (count($invoiceMethods) > 0) {
            // Sort payment methods by net term (14, 30, 45, 60, 90)
            usort($invoiceMethods, function($a, $b) {
                return $a->monduNetTerm <=> $b->monduNetTerm;
            });
            
            $uniqueNetTerms = array_unique($invoiceNetTerms);
            sort($uniqueNetTerms);
            $netTermsText = implode(', ', $uniqueNetTerms) . ' ' . $this->__translate('Tage');
            
            $monduGroups[] = [
                'title' => $this->__translate('Rechnungskauf') . ' (' . $netTermsText . ')',
                'description' => '',
                'image' => $this->getPaymentMethodImage('invoice'),
                'payment_methods' => $invoiceMethods
            ];
        }

        // Collect all SEPA methods with different net terms
        $sepaMethods = [];
        $sepaNetTerms = [];
        
        foreach ($netTerms as $netTerm) {
            $methods = array_filter($availablePaymentMethods, function ($method) use ($netTerm) {
                $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
                return $method->cAnbieter == 'Mondu' && 
                       $paymentMethodType === 'direct_debit' && 
                       str_contains($method->cModulId, $netTerm . 'tage');
            });
            
            foreach ($methods as $method) {
                $method->monduBenefits = str_replace('{net_term}', $netTerm, $this->__translate($benefits['direct_debit']));
                $method->monduNetTerm = $netTerm; // Store net term for sorting
                $method->cBild = $this->getPaymentMethodImage('direct_debit'); // Set localized image
                $sepaMethods[] = $method;
                $sepaNetTerms[] = $netTerm;
            }
        }

        // Group all SEPA methods together if any exist
        if (count($sepaMethods) > 0) {
            // Sort payment methods by net term (14, 30, 45, 60, 90)
            usort($sepaMethods, function($a, $b) {
                return $a->monduNetTerm <=> $b->monduNetTerm;
            });
            
            $uniqueNetTerms = array_unique($sepaNetTerms);
            sort($uniqueNetTerms);
            $netTermsText = implode(', ', $uniqueNetTerms) . ' ' . $this->__translate('Tage');
            
            $monduGroups[] = [
                'title' => $this->__translate('SEPA-Lastschrift') . ' (' . $netTermsText . ')',
                'description' => '',
                'image' => $this->getPaymentMethodImage('direct_debit'),
                'payment_methods' => $sepaMethods
            ];
        }

        // Installments (Ratenkauf) - Collect all installment methods and show as ONE method
        $installmentPaymentMethods = array_filter($availablePaymentMethods, function ($method) {
            // Search for 'ratenkauf' in cModulId (DB has 'kPlugin_2_ratenkauf(3,6,12monaten)')
            return $method->cAnbieter == 'Mondu' && 
                   (str_contains(strtolower($method->cModulId), 'ratenkauf') || 
                    str_contains(strtolower($method->cModulId), 'installment'));
        });

        $installmentPeriods = [];
        foreach ($installmentPaymentMethods as $method) {
            $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
            if (isset($benefits[$paymentMethodType]) && $benefits[$paymentMethodType]) {
                $method->monduBenefits = $this->__translate($benefits[$paymentMethodType]);
            } else {
                $method->monduBenefits = '';
            }
            
            $method->cBild = $this->getPaymentMethodImage('installment'); // Set localized image
            
            // Extract all periods from module ID (e.g., "ratenkauf(3,6,12monaten)" -> "3, 6, 12")
            // The DB has format like: kPlugin_2_ratenkauf(3,6,12monaten)
            if (preg_match('/ratenkauf\(([\d,]+)monaten\)/i', $method->cModulId, $matches)) {
                $periods = explode(',', $matches[1]);
                $installmentPeriods = array_merge($installmentPeriods, $periods);
            }
        }

        // Create ONE Ratenkauf method (no grouping) with all periods in title
        if (count($installmentPaymentMethods) > 0) {
            $uniquePeriods = array_unique($installmentPeriods);
            sort($uniquePeriods, SORT_NUMERIC);
            $periodsText = implode(', ', $uniquePeriods) . ' ' . $this->__translate('Monaten');
            
            $monduGroups[] = [
                'title' => $this->__translate('Ratenkauf') . ' (' . $periodsText . ')',
                'description' => '',
                'image' => $this->getPaymentMethodImage('installment'),
                'payment_methods' => $installmentPaymentMethods
            ];
        }

        // Pay Now (Echtzeitüberweisung) - NO grouping, show as separate method
        $payNowPaymentMethods = array_filter($availablePaymentMethods, function ($method) {
            $paymentMethodType = $this->configService->getPaymentMethodByKPlugin($method->cModulId);
            return $method->cAnbieter == 'Mondu' && $paymentMethodType === 'pay_now';
        });

        foreach ($payNowPaymentMethods as $method) {
            // Pay Now doesn't have benefits configured, use empty string
            $method->monduBenefits = '';
            $method->cBild = $this->getPaymentMethodImage('pay_now'); // Set localized image
            
            // Each Pay Now method as separate group (no grouping)
            $monduGroups[] = [
                'title' => $method->angezeigterName[$_SESSION['cISOSprache']],
                'description' => '',
                'image' => $this->getPaymentMethodImage('pay_now'),
                'payment_methods' => [$method]
            ];
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
                $this->setMonduPaymentMethodsCache(['error']);
                return ['error'];
            } 

            $allowedPaymentMethods = array_map(function ($method) {
              return $method['identifier'];
            }, (array) $apiAllowedPaymentMethods['payment_methods']);

            $this->setMonduPaymentMethodsCache($allowedPaymentMethods);

            return $allowedPaymentMethods;

        } catch (Exception $e) {
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
                $this->setMonduNetTermsCache([]);
                return [];
            }

            $buyerCountry = $this->getBuyerCountryCode();

            $netTerms = array_unique(array_filter(array_map(function ($paymentMethod) use ($buyerCountry) {
                if ($buyerCountry == $paymentMethod['country_code']){
                    return $paymentMethod['net_term'];
                }
            }, (array) $allowedNetTerms['payment_terms'])));

            $this->setMonduNetTermsCache($netTerms);

            return $netTerms;

        } catch (Exception $e) {
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

    /**
     * Get payment method image based on locale and payment type
     * 
     * @param string $paymentType (invoice, direct_debit, installment, pay_now)
     * @return string Path to image
     */
    private function getPaymentMethodImage(string $paymentType): string
    {
        // Get current language ISO code
        $currentLang = Shop::Lang()->getIso(); // Returns 'ger', 'eng', etc.
        
        // Map ISO codes to locale folders
        $localeMap = [
            'ger' => 'de',
            'eng' => 'en',
            'dut' => 'nl',
            'fre' => 'fr'
        ];
        
        // Map payment types to image filenames
        $imageMap = [
            'invoice' => 'invoice_white_rectangle.png',
            'direct_debit' => 'sepa_white_rectangle.png',
            'installment' => 'installments_white_rectangle.png',
            'pay_now' => 'instant_pay_white_rectangle.png'
        ];
        
        // Get locale folder (default to 'de' if not mapped)
        $locale = $localeMap[$currentLang] ?? 'de';
        
        // Get image filename
        $imageFilename = $imageMap[$paymentType] ?? null;
        
        if (!$imageFilename) {
            // Return default image if payment type not recognized
            return $this->plugin->getPaths()->getBaseURL() . 'paymentmethod/images/plugin.png';
        }
        
        // Build localized image path
        $localizedImagePath = $this->plugin->getPaths()->getBaseURL() . 'paymentmethod/images/' . $locale . '/' . $imageFilename;
        $localizedImageFile = $this->plugin->getPaths()->getBasePath() . 'paymentmethod/images/' . $locale . '/' . $imageFilename;
        
        // Check if localized image exists
        if (file_exists($localizedImageFile)) {
            return $localizedImagePath;
        }
        
        // Fallback to default image
        return $this->plugin->getPaths()->getBaseURL() . 'paymentmethod/images/plugin.png';
    }
}

$hook = new CheckoutPaymentMethod();
$hook->execute();