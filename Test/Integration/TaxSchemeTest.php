<?php

namespace Gw\AutoCustomerGroupNewZealand\Test\Integration;

use Gw\AutoCustomerGroupNewZealand\Model\TaxScheme;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoAppArea frontend
 */
class TaxSchemeTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var TaxScheme
     */
    private $taxScheme;

    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->taxScheme = $this->objectManager->get(TaxScheme::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
        $this->guestCartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->guestCartRepository = $this->objectManager->get(GuestCartRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoAdminConfigFixture current_store currency/options/default GBP
     * @magentoAdminConfigFixture current_store currency/options/base GBP
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/exchangerate 0.4617
     * @dataProvider getOrderValueDataProvider
     */
    public function testGetOrderValue(
        $qty1,
        $price1,
        $qty2,
        $price2,
        $expectedValue
    ): void {
        $product1 = $this->productFactory->create();
        $product1->setTypeId('simple')
            ->setId(1)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 1')
            ->setSku('simple1')
            ->setPrice($price1)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product1);
        $product2 = $this->productFactory->create();
        $product2->setTypeId('simple')
            ->setId(2)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 2')
            ->setSku('simple2')
            ->setPrice($price2)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product2);
        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        $quote = $this->guestCartRepository->get($maskedCartId);
        $quote->addProduct($product1, $qty1);
        $quote->addProduct($product2, $qty2);
        $this->quoteRepository->save($quote);
        $result = $this->taxScheme->getOrderValue(
            $quote
        );
        $this->assertEqualsWithDelta($expectedValue, $result, 0.009);
    }

    /**
     * @return array
     */
    public function getOrderValueDataProvider(): array
    {
        // Quantity 1
        // Base Price 1
        // Quantity 2
        // Base Price 2
        // Expected Order Value Scheme Currency
        return [
            [1, 200.00, 1, 100.00, 433.18], // 200GBP in NZD
            [2, 101.00, 1, 200.00, 433.18], // 200GBP in NZD
            [3, 100.00, 1, 200.00, 433.18], // 200GBP in NZD
            [20, 25.50, 1, 200.00, 433.18], // 200GBP in NZD
            [20, 25.50, 1, 400.00, 866.36]  // 400GBP in NZD
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/domestic 1
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importb2b 2
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importtaxed 3
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importuntaxed 4
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importthreshold 1000
     * @dataProvider getCustomerGroupDataProvider
     */
    public function testGetCustomerGroup(
        $merchantCountryCode,
        $merchantPostCode,
        $customerCountryCode,
        $customerPostCode,
        $taxIdValidated,
        $orderValue,
        $expectedGroup
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            $merchantCountryCode,
            ScopeInterface::SCOPE_STORE
        );
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            $merchantPostCode,
            ScopeInterface::SCOPE_STORE
        );
        $result = $this->taxScheme->getCustomerGroup(
            $customerCountryCode,
            $customerPostCode,
            $taxIdValidated,
            $orderValue,
            $storeId
        );
        $this->assertEquals($expectedGroup, $result);
    }

    /**
     * @return array
     */
    public function getCustomerGroupDataProvider(): array
    {
        //Merchant Country Code
        //Merchant Post Code
        //Customer Country Code
        //Customer Post Code
        //taxIdValidated
        //OrderValue
        //Expected Group
        return [
            // NZ to NZ, value doesn't matter, GST number status doesn't matter - Domestic
            ['NZ', null, 'NZ', null, false, 999, 1],
            ['NZ', null, 'NZ', null, true, 1001, 1],
            ['NZ', null, 'NZ', null, false, 999, 1],
            ['NZ', null, 'NZ', null, false, 999, 1],
            ['NZ', null, 'NZ', null, true, 999, 1],
            ['NZ', null, 'NZ', null, false, 1001, 1],
            ['NZ', null, 'NZ', null, false, 1001, 1],
            // Import into NZ, value doesn't matter, valid GST - Import B2B
            ['FR', null, 'NZ', null, true, 999, 2],
            ['FR', null, 'NZ', null, true, 1001, 2],
            // Import into NZ, value below threshold, Should only be B2C at this point - Import Taxed
            ['FR', null, 'NZ', null, false, 999, 3],
            ['FR', null, 'NZ', null, false, 999, 3],
            // Import into NZ, value above threshold, Should only be B2C at this point - Import Untaxed
            ['FR', null, 'NZ', null, false, 1001, 4],
            ['FR', null, 'NZ', null, false, 1001, 4],
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/validate_online 1
     * @dataProvider checkTaxIdDataProviderOnline
     *
     */
    public function testCheckTaxIdOnline(
        $countryCode,
        $taxId,
        $isValid
    ): void {
        $result = $this->taxScheme->checkTaxId(
            $countryCode,
            $taxId
        );
        $this->assertEquals($isValid, $result->getIsValid());
    }

    /**
     * @return array
     */
    public function checkTaxIdDataProviderOnline(): array
    {
        //Country code
        //Tax Id
        //IsValid
        return [
            ['NZ', '',                  false],
            ['NZ', null,                false],
            ['NZ', '9429050853731',     true], // Correct format, valid NZBN, valid GST online
            ['NZ', '9429049835892',     true], // Correct format, valid NZBN, valid GST online
            ['NZ', '9429032097351',     false], // Correct format, valid NZBN, no GST online
            ['NZ', '9429036975273',     false], // Correct format, valid NZBN, no GST online
            ['NZ', 'htght',             false],
            ['NZ', 'nntreenhrhnjrehh',  false],
            ['NZ', '4363546743743',     false],
            ['NZ', '7234767476612',     false],
            ['NZ', 'th',                false],
            ['NZ', '786176152',         false],
            ['GB', '9429050853731',     false], // Unsupported country, despite valid number
        ];
    }

    /**
     * @dataProvider checkTaxIdDataProviderOffline
     *
     */
    public function testCheckTaxIdOffline(
        $countryCode,
        $taxId,
        $isValid
    ): void {
        $result = $this->taxScheme->checkTaxId(
            $countryCode,
            $taxId
        );
        $this->assertEquals($isValid, $result->getIsValid());
    }

    /**
     * @return array
     */
    public function checkTaxIdDataProviderOffline(): array
    {
        //Country code
        //Tax Id
        //IsValid
        return [
            ['NZ', '',                  false],
            ['NZ', null,                false],
            ['NZ', '9429050853731',     true], //Correct format, valid NZBN
            ['NZ', '9429049835892',     true], //Correct format, valid NZBN
            ['NZ', '9429032097351',     true], //Correct format, valid NZBN
            ['NZ', '9429036975273',     true], //Correct format, valid NZBN
            ['NZ', 'htght',             false],
            ['NZ', 'nntreenhrhnjrehh',  false],
            ['NZ', '4363546743743',     false],
            ['NZ', '7234767476612',     false],
            ['NZ', 'th',                false],
            ['NZ', '786176152',         false],
        ];
    }
}

