<h1>AutoCustomerGroup - New Zealand Addon</h1>
<p>Magento 2 Module - Module to add New Zealand functionality to gwharton/module-autocustomergroup</p>
<h2>New Zealand GST Scheme for Low Value Imports</h2>
<h3>Configuration Options</h3>
<ul>
<li><b>Enabled</b> - Enable/Disable this Tax Scheme.</li>
<li><b>Tax Identifier Field - Customer Prompt</b> - Displayed under the Tax Identifier field at checkout when a shipping country supported by this module is selected. Use this to include information to the user about why to include their Tax Identifier.</li>
<li><b>Validate Online</b> - Whether to validate NZBN numbers with the NZBN Business Register Service, or just perform simple format validation.</li>
<li><b>Environment</b> - Whether to use the Sandbox or Production servers for the NZBN Validation Service.</li>
<li><b>API Access Token</b> - The API Access Token provided by the NZBN Business Register website for API access.</li>
<li><b>GST Registration Number</b> - The GST Registration Number for the Merchant. This is not currently used by the module, however supplementary functions in AutoCustomerGroup may use this, for example displaying on invoices etc.</li>
<li><b>Import GST Threshold</b> - If the order value is above the GST Threshold, no GST should be charged.</li>
<li><b>Use Magento Exchange Rate</b> - To convert from NZD Threshold to Store Currency Threshold, should we use the Magento Exchange Rate, or our own.</li>
<li><b>Exchange Rate</b> - The exchange rate to use to convert from NZD Threshold to Store Currency Threshold.</li>
<li><b>Customer Group - Domestic</b> - Merchant Country is within New Zealand, Item is being shipped to New Zealand.</li>
<li><b>Customer Group - Import B2B</b> - Merchant Country is not within New Zealand, Item is being shipped to New Zealand, GST Number passed validation by module.</li>
<li><b>Customer Group - Import Taxed</b> - Merchant Country is not within New Zealand, Item is being shipped to New Zealand, All items valued at or below the Import GST Threshold.</li>
<li><b>Customer Group - Import Untaxed</b> - Merchant Country is not within New Zealand, Item is being shipped to New Zealand, One or more items in the order is valued above the Import GST Threshold.</li>
</ul>
<h2>Integration Tests</h2>
<p>To run the integration tests, you need your own credentials for the NZBN Business Register API. Please add them to config-global.php.</p>
<p>Please note that the integration tests are configured to use the NZBN Business Register API Sandbox.</p>
<ul>
<li>autocustomergroup/newzealandgst/accesstoken</li>
</ul>
