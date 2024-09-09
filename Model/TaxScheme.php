<?php

namespace Gw\AutoCustomerGroupNewZealand\Model;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterface;
use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterfaceFactory;
use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * New Zealand NZBN Test numbers for Sandbox (as of 11/07/2024)
 * 9429032097351 - MICROSOFT NEW ZEALAND LIMITED - NO GST
 * 9429036975273 - GOOGLE NEW ZEALAND LIMITED - NO GST
 * 9429050853731 - TRACY'S TEST COMPANY LIMITED - GST 111111111
 * 9429049835892 - WOF 00916825 LIMITED - GST 111111111
 *
 * Real New Zealand numbers (as of 11/07/2024)
 * 9429039098740 - MICROSOFT NEW ZEALAND LIMITED - NO GST
 * 9429034243282 - GOOGLE NEW ZEALAND LIMITED - NO GST
 * 9429041535110 - AMAZON SERVICES LIMITED - GST 115706691
 * 9429049999198 - G.S.GILL. LIMITED - GST 134953500
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TaxScheme  implements TaxSchemeInterface
{
    const CODE = "newzealandgst";
    const SCHEME_CURRENCY = 'NZD';
    const array SCHEME_COUNTRIES = ['NZ'];

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var DateTime;
     */
    private $dateTime;

    /**
     * @var TaxIdCheckResponseInterfaceFactory
     */
    protected $ticrFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CurrencyFactory
     */
    public $currencyFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param TaxIdCheckResponseInterfaceFactory $ticrFactory
     * @param ClientFactory $clientFactory
     * @param Json $serializer
     * @param DateTime $datetime
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        TaxIdCheckResponseInterfaceFactory $ticrFactory,
        ClientFactory $clientFactory,
        Json $serializer,
        DateTime $datetime
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->ticrFactory = $ticrFactory;
        $this->clientFactory = $clientFactory;
        $this->serializer = $serializer;
        $this->dateTime = $datetime;
    }

    /**
     * Get the order value, in scheme currency
     *
     * The Selling goods to consumers in New Zealand Guide provides information on what to do where
     * an order contains a mixture of items below and above the threshold. It recommends to charge
     * GST on the low value items and not charge GST on the high value items. This situation is too
     * complex for this module to handle at present, therefore if any single item on the order is
     * above the GST Threshold, then no GST will be charged on the order, and the correct GST will be
     * charged at the New Zealand border.
     *
     * https://www.ird.govt.nz/-/media/project/ir/home/documents/forms-and-guides/ir200---ir299/ad264/ad264-2019.pdf
     *
     * @param Quote $quote
     * @return float
     */
    public function getOrderValue(Quote $quote): float
    {
        $mostExpensive = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $itemPrice = $item->getBasePrice() - ($item->getBaseDiscountAmount() / $item->getQty());
            if ($itemPrice > $mostExpensive) {
                $mostExpensive = $itemPrice;
            }
        }
        return ($mostExpensive / $this->getSchemeExchangeRate($quote->getStoreId()));
    }

    /**
     * Get customer group based on Validation Result and Country of customer
     * @param string $customerCountryCode
     * @param string|null $customerPostCode
     * @param bool $taxIdValidated
     * @param float $orderValue
     * @param int|null $storeId
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        bool $taxIdValidated,
        float $orderValue,
        ?int $storeId
    ): ?int {
        $merchantCountry = $this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (empty($merchantCountry)) {
            $this->logger->critical(
                "Gw/AutoCustomerGroupNewZealand/Model/TaxScheme::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }

        $importThreshold = $this->getThresholdInSchemeCurrency($storeId);

        //Merchant Country is in New Zealand
        //Item shipped to New Zealand
        //Therefore Domestic
        if (in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //GST Number Supplied
        //Therefore Import B2B
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            $taxIdValidated) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //All items equal or below threshold
        //Therefore Import Taxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //Any item is above threshold
        //Therefore Import Unaxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return null;
    }

    /**
     * Peform validation of the NZBN Number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string|null $taxId
     * @return TaxIdCheckResponseInterface
     * @throws GuzzleException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): TaxIdCheckResponseInterface {
        $taxIdCheckResponse = $this->ticrFactory->create();

        if (!in_array($countryCode, self::SCHEME_COUNTRIES)) {
            $taxIdCheckResponse->setRequestMessage(__('Unsupported country.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(false);
            return $taxIdCheckResponse;
        }

        $taxIdCheckResponse = $this->validateFormat($taxIdCheckResponse, $taxId);

        if ($taxIdCheckResponse->getIsValid() && $this->scopeConfig->isSetFlag(
                "autocustomergroup/" . self::CODE . "/validate_online",
                ScopeInterface::SCOPE_STORE
            )) {
            $taxIdCheckResponse = $this->validateOnline($taxIdCheckResponse, $taxId);
        }

        return $taxIdCheckResponse;
    }

    /**
     * Perform offline validation of the Tax Identifier
     *
     * @param $taxIdCheckResponse
     * @param $taxId
     * @return TaxIdCheckResponseInterface
     */
    private function validateFormat($taxIdCheckResponse, $taxId): TaxIdCheckResponseInterface
    {
        if (($taxId === null || strlen($taxId) < 1)) {
            $taxIdCheckResponse->setRequestMessage(__('You didn\'t supply a Business Registration Number to check.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            return $taxIdCheckResponse;
        }
        if (preg_match("/^[0-9]{13}$/i", $taxId)) {
            $taxIdCheckResponse->setIsValid(true);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('Business Registration Number is the correct format.'));
        } else {
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('Business Registration Number is not the correct format.'));
            return $taxIdCheckResponse;
        }
        if ($this->isValidNzbn($taxId)) {
            $taxIdCheckResponse->setIsValid(true);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('Business Registration Number is validated (Offline).'));
        } else {
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('Business Registration Number is not valid (Offline).'));
            return $taxIdCheckResponse;
        }
        return $taxIdCheckResponse;
    }

    /**
     * Perform online validation of the Tax Identifier
     *
     * @param $taxIdCheckResponse
     * @param $taxId
     * @return TaxIdCheckResponseInterface
     */
    private function validateOnline($taxIdCheckResponse, $taxId): TaxIdCheckResponseInterface
    {
        $accesstoken = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/accesstoken",
            ScopeInterface::SCOPE_STORE
        );
        if (empty($accesstoken)) {
            $this->logger->critical(
                "AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : " .
                "No Access Token"
            );
            $taxIdCheckResponse->setRequestMessage(__('No access token.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(false);
            return $taxIdCheckResponse;
        }

        try {
            if ($this->scopeConfig->getValue(
                    "autocustomergroup/" . self::CODE . "/environment",
                    ScopeInterface::SCOPE_STORE
                ) == Environment::ENVIRONMENT_PRODUCTION) {
                $baseUrl = "https://api.business.govt.nz/gateway/nzbn/v5";
            } else {
                $baseUrl = "https://api.business.govt.nz/sandbox/nzbn/v5";
            }
            $client = $this->clientFactory->create();
            $response = $client->request(
                Request::HTTP_METHOD_GET,
                $baseUrl . "/entities/" . $taxId . "/gst-numbers",
                [
                    'headers' => [
                        "Ocp-Apim-Subscription-Key" => $accesstoken,
                        'Accept' => "application/json"
                    ]
                ]
            );
            $responseBody = $response->getBody();
            $registrations = $this->serializer->unserialize($responseBody->getContents());
            if (!is_array($registrations)) {
                $taxIdCheckResponse->setIsValid(false);
                $taxIdCheckResponse->setRequestMessage(__('Error communicating with NZBN API.'));
                $this->logger->error(
                    "Gw/AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : Could not interpret " .
                    " response",
                    ['registration' => $registrations]
                );
                $taxIdCheckResponse->setRequestSuccess(false);
                return $taxIdCheckResponse;
            }
            $taxIdCheckResponse->setRequestMessage(__('GST Number not registered at the NZBN Register for this NZBN.'));
            $taxIdCheckResponse->setIsValid(false);
            foreach ($registrations as $registration) {
                $identifier = $registration['uniqueIdentifier'];
                $gst = $registration['gstNumber'];
                $startDate = $registration['startDate'];
                $now = $this->dateTime->gmtDate();
                if ($startDate <= $now && $this->isValidGst($gst)) {
                    $taxIdCheckResponse->setRequestMessage(
                        __('GST Registration Number ' . $gst . ' is validated for NZBN ' . $taxId)
                    );
                    $taxIdCheckResponse->setIsValid(true);
                    $taxIdCheckResponse->setRequestDate($now);
                    $taxIdCheckResponse->setRequestSuccess(true);
                    $taxIdCheckResponse->setRequestIdentifier($identifier);
                    break;
                }
            }
        } catch (BadResponseException $e) {
            switch ($e->getCode()) {
                case 404:
                    $taxIdCheckResponse->setIsValid(false);
                    $taxIdCheckResponse->setRequestSuccess(true);
                    $taxIdCheckResponse->setRequestMessage(__('NZBN Number not found.'));
                    break;
                default:
                    $taxIdCheckResponse->setIsValid(false);
                    $taxIdCheckResponse->setRequestSuccess(false);
                    $taxIdCheckResponse->setRequestMessage(__('There was an error checking the NZBN number.'));
                    $this->logger->error(
                        "Gw/AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : Error received " .
                        "from Server. " . $e->getCode() . " " . $e->getMessage()
                    );
                    break;
            }
        }
        return $taxIdCheckResponse;
    }

    /**
     * Validate an GST Number
     *
     * https://wiki.scn.sap.com/wiki/display/CRM/New+Zealand
     *
     * @param string|null $gst
     * @return bool
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isValidGst(?string $gst): bool
    {
        $weightsa = [3,2,7,6,5,4,3,2];
        $weightsb = [7,4,3,2,5,2,7,6];
        $gst = preg_replace('/[^0-9]/', '', $gst ?? "");
        if (!is_numeric($gst)) {
            return false;
        }
        $check = $gst[strlen($gst)-1];
        $withoutcheck = substr($gst, 0, strlen($gst)-1);
        if (strlen($withoutcheck) == 7) {
            $withoutcheck = "0".$withoutcheck;
        }
        if (strlen($withoutcheck) != 8) {
            return false;
        }
        $sum = 0;
        foreach (str_split($withoutcheck) as $key => $digit) {
            $sum += ((int) $digit * $weightsa[$key]);
        }
        $remainder = $sum % 11;
        if ($remainder == 0) {
            $calculatedCheck = $remainder;
        } else {
            $calculatedCheck = 11 - $remainder;
            if ($calculatedCheck == 10) {
                $sum = 0;
                foreach (str_split($withoutcheck) as $key => $digit) {
                    $sum += ((int) $digit * $weightsb[$key]);
                }
                $remainder = $sum % 11;
                if ($remainder == 0) {
                    $calculatedCheck = $remainder;
                } else {
                    $calculatedCheck = 11 - $remainder;
                    if ($calculatedCheck == 10) {
                        return false;
                    }
                }
            }
        }
        if ($calculatedCheck == $check) {
            return true;
        }
        return false;
    }

    /**
     * Validate an NZBN Number
     *
     * https://www.gs1.org/services/how-calculate-check-digit-manually
     *
     * @param string|null $nzbn
     * @return bool
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isValidNzbn(?string $nzbn): bool
    {
        $weights = [1,3,1,3,1,3,1,3,1,3,1,3];

        if (!is_numeric($nzbn)) {
            return false;
        }
        if (strlen($nzbn) != 13) {
            return false;
        }
        $check = $nzbn[strlen($nzbn)-1];
        $withoutcheck = substr($nzbn, 0, strlen($nzbn)-1);

        $sum = 0;
        foreach (str_split($withoutcheck) as $key => $digit) {
            $sum += ((int) $digit * $weights[$key]);
        }
        $calculatedCheck = (ceil($sum/10) * 10) - $sum;
        if ($calculatedCheck == $check) {
            return true;
        }
        return false;
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return __("New Zealand GST Scheme for Low Value Imports");
    }

    /**
     * Get the scheme code
     *
     * @return string
     */
    public function getSchemeId(): string
    {
        return self::CODE;
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getFrontEndPrompt(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/frontendprompt",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string
     */
    public function getSchemeCurrencyCode(): string
    {
        return self::SCHEME_CURRENCY;
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency(?int $storeId): float
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSchemeRegistrationNumber(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return array
     */
    public function getSchemeCountries(): array
    {
        return self::SCHEME_COUNTRIES;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . "/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate(?int $storeId): float
    {
        if ($this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . '/usemagentoexchangerate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            $websiteBaseCurrency = $this->scopeConfig->getValue(
                Currency::XML_PATH_CURRENCY_BASE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $exchangerate = $this->currencyFactory
                ->create()
                ->load($this->getSchemeCurrencyCode())
                ->getAnyRate($websiteBaseCurrency);
            if (!$exchangerate) {
                $this->logger->critical(
                    "Gw/AutoCustomerGroupNewZealand/Model/TaxScheme::getSchemeExchangeRate() : " .
                    "No Magento Exchange Rate configured for " . self::SCHEME_CURRENCY . " to " .
                    $websiteBaseCurrency . ". Using 1.0"
                );
                $exchangerate = 1.0;
            }
            return (float)$exchangerate;
        }
        return (float)$this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/exchangerate",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
