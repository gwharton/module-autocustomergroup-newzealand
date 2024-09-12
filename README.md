<h1>AutoCustomerGroup - New Zealand Addon</h1>
<p>Magento 2 Module - Module to add New Zealand functionality to gwharton/module-autocustomergroup</p>

<h2>New Zealand GST Scheme for Low Value Imports</h2>
<p>This Scheme applies to shipments being sent from anywhere in the world to Consumers (Not B2B transactions) in New Zealand.</p>
<p>As of 1st December 2019, all sellers must (if their turnover to New Zealand exceeds 60,000 NZD) register for the New Zealand GST scheme, and collect GST for B2C transactions at the point of sale and remit to the New Zealand Government.</p>
<p>The module is capable of automatically assigning customers to the following categories.</p>
<ul>
    <li><b>Domestic</b> - For shipments within New Zealand, normal New Zealand GST rules apply.</li>
    <li><b>Import B2B</b> - For shipments from outside of New Zealand to New Zealand and the buyer presents their GST validated NZBN Number, then GST should not be charged.</li>
    <li><b>Import Taxed</b> - For imports into New Zealand, where the value of each individual item is below or equal to 1,000 NZD, then GST should be charged on the order.</li>
    <li><b>Import Untaxed</b> - For imports into New Zealand, where one or more individual items value is above 1,000 NZD, then GST should NOT be charged and instead will be collected at the New Zealand border along with any duties due.</li>
</ul>
<p>You need to create the appropriate tax rules and customer groups, and assign these customer groups to the above categories within the module configuration. Please ensure you fully understand the tax rules of the country you are shipping to. The above should only be taken as a guide.</p>

<h2>Government Information</h2>
<p>Scheme information can be found <a href="https://www.ird.govt.nz/gst/gst-for-overseas-businesses/gst-on-low-value-imported-goods" target="_blank">on the IRD website here</a>.</p>

<h2>Order Value</h2>
<p>For the New Zealand GST Scheme, the following applies (This can be confirmed
    <a href="https://www.ird.govt.nz/-/media/project/ir/home/documents/forms-and-guides/ir200---ir299/ad264/ad264-2019.pdf"
    target="_blank">here</a>) : </p>
<ul>
    <li>When determining whether GST should be charged (GST Threshold) Shipping or Insurance Costs are not included in the value of the goods.</li>
    <li>When determining the amount of GST to charge the Goods value does include Shipping and Insurance Costs.</li>
</ul>
<p>The <a href="https://www.ird.govt.nz/-/media/project/ir/home/documents/forms-and-guides/ir200---ir299/ad264/ad264-2019.pdf" target="_blank">Selling goods to consumers
    in New Zealand Guide</a> provides information on what to do where an order contains a mixture of items below and above the threshold. It recommends to charge
    GST on the low value items and not charge GST on the high value items. This situation is too complex for this module to handle at present, therefore
    if any single item on the order is above the GST Threshold, then no GST will be charged on the order, and the correct GST will be charged at the
    New Zealand border.</p>
<p>More information on the scheme can be found on the
    <a href="https://www.ird.govt.nz/gst/gst-for-overseas-businesses/gst-on-low-value-imported-goods" target="_blank">New Zealand Inland Revenue Website</a></p>

<h2>Pseudocode for group allocation</h2>
<p>Groups are allocated by evaluating the following rules in this order (If a rule matches, no further rules are evaluated).</p>
<ul>
<li>IF MerchantCountry IS New Zealand AND CustomerCountry IS New Zealand THEN Group IS Domestic.</li>
<li>IF MerchantCountry IS NOT New Zealand AND CustomerCountry IS New Zealand AND TaxIdentifier IS VALID THEN Group IS ImportB2B.</li>
<li>IF MerchantCountry IS NOT New Zealand AND CustomerCountry IS New Zealand AND OrderValue IS LESS THAN OR EQUAL TO Threshold THEN Group IS ImportTaxed.</li>
<li>IF MerchantCountry IS NOT New Zealand AND CustomerCountry IS New Zealand AND OrderValue IS MORE THAN Threshold THEN Group IS ImportUntaxed.</li>
<li>ELSE NO GROUP CHANGE</li>
</ul>

<h2>NZBN Number Verification</h2>
<ul>
<li><b>Offline Validation</b> - A simple format and checksum validation is performed.</li>
<li><b>Online Validation</b> - In addition to the offline checks above, an online validation check is performed with the New Zealand NZBN verification service. The online check not only ensures that the NZBN number is valid, but also checks that the NZBN number has an associated GST number registered with it, and it is in date and valid.</li>
</ul>
<p>To obtain an access token for the NZBN Verification service, follow the procedure.</p>
<ul>
<li>Sign up for an account at <a href="https://api.business.govt.nz" target="_blank">the New Zealand Government API Website</a></li>
<li>Create an application at the "My Applications" section</li>
<li>Subscribe to the NZBN Version 5 API in the "Apis" section</li>
<li>You may be required to sign a license agreement to use the service</li>
<li>Generate Subscription Keys for both sandbox and production services</li>
<li>Generate access tokens with -1 seconds validity time (this ensures they don't expire)</li>
</ul>
<p>Note : The New Zealand Government do NOT require you to perform online validation before accepting an NZBN number as valid.</p>

<h2>Configuration Options</h2>
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
