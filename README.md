# Nexi Checkout - Oxid 7 payment module

## Installation:

`composer require nexi-checkout/oxid7`

## Activation:

`vendor/bin/oe-console oe:module:activate nexi-checkout`

## Configuration

1. To configure and set up the module navigate to : Admin > Extensions > Modules
2. Locate and select Nexi Checkout module from the list of installed extension.
3. Select the Overview tab and press the Activate button. If the Nexi Checkout has been installed correct you will
   now see a green checked circle under active column next Nexi Checkout on your list.
4. To activate your new payment method and set up the module for your shop navigate to:
   Admin > Shop Settings > Payment Methods
5. Payment methods are listed in alphabetical order on multiple pages. Find or search for Nexi Checkout and select.
6. On Main tab make sure to check the Active box.
   NOTE: Name and Payment Description input fields can be used for a customized description of the Nexi Checkout
   module to your customers on selection of payment methods.
7. Once assigned User Groups and Countries and translations if needed then remember to Save.
8. Navigate back to the module settings:
   Admin > Extensions > Modules > Nexi Checkout
9. Select the Settings tab and press on Nexi Checkout settings to reveal the content of configuration settings.

## Module settings

1. Mode. Select between Test/Live transactions. Live mode requires an approved account.
   Testcard information can be found here : https://tech.dibspayment.com/easy/test-information
2. Test / Live keys. Login to your Nexi Checkout account. Keys can be found in Company >
   Integration : https://portal.dibspayment.eu/
3. Terms Url. Set the url for your Terms and Conditions page.
4. Merchant Terms Url. Set the url for your Terms of use and Cookies page.
   NOTE : Term Links can be found inside the Nexi Checkout payment window.
5. Icons bar url. Set and customize icons listing by editing directly in parameters in the url or visit our custom Icons
   url generator : https://easymoduler.dk/icon/
6. Checkout Type. Hosted / Embedded. Select between 2 checkout types. Hosted - Nexi Checkout Hosted loads a new payment
   page.
   Embedded checkout inserts the payment window directly on the checkout page.
7. Embedded Checkout Layout. Layout 1 / Layout 2. Select between 2 layouts for your Embedded checkout
8. Auto-capture. This function allows you to instantly charge a payment straight after the order is placed.
   NOTE. Capturing a payment before shipment of the order might be liable to restrictions based upon legislations set in
   your country. Misuse can result in your account being forfeit.
9. Debug. This function will reveal raw api data that you can copy / paste in case you experience errors on your
   transactions. Data will be visible in order details. This is intended to be used when contacting Nexi Checkout
   support.

## Order operations

1. Navigate to admin > Administer Orders > Orders. Select an Order paid through Nexi Checkout .
2. Choose your desired function :
    - Fully cancel / charge / refund your order.
    - Partially charge / refund your order.
      NOTE :
    - Partial functionality handles items in your order as blocks. Custom amount is not possible.
    - Use -/+ to adjust quantity for partial functionality.
    - Press partial button to execute action per line.
3. All transactions by Nexi Checkout are accessible in our portal : https://portal.dibspayment.eu/login
4. Payment status is real-time and updated in Order details even if you make the changes in Nexi Checkout Portal.

### Available operations

* Cancel
* Capture
* Refund
* Partial Capture
* Partial refund

## Troubleshooting

* Nexi Checkout payment module is not visible as a payment method

- Ensure the Nexi Checkout module is activated in Shop Settings and in the Extensions module configuration.
- Ensure the Nexi Checkout module is assigned in available listings in various User Groups and Countries.
- Various User Groups, Countries and Payment Methods setup listings can be found in Admin > Shop Settings > Payment
  Methods and Shipping Methods

* Nexi Checkout payment window is blank

- Ensure your keys in Nexi Checkout module Settings are correct and with no additional blank spaces.
- Temporarily deactivate 3.rd party extension that might affect the functionality of the Nexi Checkout module.

* Payments in live mode dont work

- Ensure you have an approved Live account for production.
- Ensure your Live account is approved for payments with selected currency.
- Ensure payment method data is correct and supported by your Nexi Checkout agreement.

* How do I choose currency or language in the payment window?

- Currency and Language is based on customers selection on the frontend shop page.
- A complete list of supported currencies and languages can be
  found [here](https://developer.nexigroup.com/nexi-checkout/en-EU/docs/nexi-checkout-for-oxid/)

## Contact

- Nexi Checkout provides support for both test and live accounts. Contact information can be
  found [here](https://developer.nexigroup.com/nexi-checkout/en-EU/support/)

**CREATE YOUR FREE Nexi Checkout EASY TEST ACCOUNT HERE : https://portal.dibspayment.eu/registration**
