## Changelog

[Unreleased]
- Added Honduras to countries list
- Added Belize to countries list
- Add validation and control the length and special characters in the description of the order sent to the gateway
- Update plugin site information url
- Update the message information when there are pending payments, do not show the payment authorization number
- Fix getting payment installments

### [2.19.7] - 2022-07-12
- Update headers when processing pending transactions
- Fix error when canceling the payment process when mixed payments are inactive
- Fix error in sonda process: undefined REQUEST_METHOD
- Fix error when acquirer don't use installments

### [2.19.6] - 2022-05-09
- Update dnetix/redirection package
- Add headers when processing pending transactions

### [2.19.5] - 2022-05-05
- Fix error with taxes
- Add reference to order note

### [2.19.4] - 2022-04-18
- Change payment method name on order note
- Change Chile endpoint for test

### [2.19.3] - 2022-04-12
- Add payment detail note
- fix note duplication on payment flow

### [2.19.2] - 2022-03-24
- Change ecuador endpoint
- Fix bug on payment result
- Add custom notes (WIP)

### [2.19.1] - 2021-11-22
- Fix ecuador test endpoint
- Remove country form validation

### [2.19.0] - 2021-11-08
- Added Puerto Rico to Countries list

### [2.18.9] - 2021-10-27
- Fixed cron job path

### [2.18.8] - 2021-09-22
- Changed production endpoints

### [2.18.7] - 2021-08-31
- Changed branding

### [2.18.6] - 2021-08-19
- Updated dnetix/redirection package

### [2.18.5] - 2021-08-19
- Updated Getnet endpoints

### [2.18.1] - 2021-05-06
- Added sonda path on admin panel
- Fixed translations on custom image and url

### [2.18.0] - 2021-05-06
- Added custom connection url
- Added custom payment button image

### [2.17.5] - 2021-04-27
- Added Chile payment gateway

### [2.17.1] - 2020-10.23
- Updated dnetix/redirection plugin

### [2.17.0] - 2020-10.23
- Added ico tax rate
- Added base devolution amount to tax detail object
- Changed PlacetoPay text to Placetopay
- Fixed authorization code problem

### [2.16.1] - 2020-09-02
- Fixed placetopay production url
- Added Costa Rica to countries list

### [2.16.0] - 2020-09-02
- Added skip result to admin panel
- Added skip result to payment request
- Added support to php 7.4
- Added default status to payment order
- Fixed notification sonda error
- Added Costa Rica to countries list
- Updated placetopay endpoint for production environment

### [2.15.1] - 2019-09-23
- Update dnetix library in composer.json

### [2.14.5] - 2019-07-10
- Added support to partial payments
- Fix transactions detail for partial payments
- Fix order reference number

### [2.14.0] - 2019-06-06
- Add support for partial payments
- Improve admin form

### [2.13.1] - 2018-05-22
- Upgraded redirection dependency for support multiples currencies in validation
- Added support for cancelled orders
- Added support for countries Colombia, Ecuador(CO, EC)

### [2.11.6] - 2017-12-01
- Added status order to the page of return and a link to PlacetoPay for view order detail
- Updated the file of translations `es_CO`
- Added field list in configuration for the expiration in minutes
- Added product names to the description of the request for PlacetoPay
- Added validations to field of the checkout
- Updated logo PlacetoPay
- Added link in the logo to PlacetoPay site

### [2.6.6] - 2017-11-01
- Fixed alert message was displayed in debug mode of WordPress
