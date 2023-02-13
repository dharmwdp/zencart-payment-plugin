# zencart-payment-plugin

Omnipay ZenCart Plugin v1.0
=============================

Compatible with Zen Cart 1.5.0 (and presumably above)

Developed by Omnipay

Installation
------------

1. Using your FTP Client or SSH/SCP, copy the directories on **zencart_directory** to your ZenCart root.
2. Log into ZenCart Admin/Management, and click on Modules -> Payment
3. Click on **Omnipay**, then click **Install** on the right hand side.
4. Enter your account details.

Default credentials are provided, however if you wish to obtain a free Test account please [Contact Omnipay](https://psp.digitalworld.com.sa/contact-us)

Uninstalling
------------

1. Log into ZenCart Admin/Management, and click on Modules -> Payment
2. Click on **Omnipay**, then click **Remove**.
3. Click **Remove** to confirm.
4. Delete the files listed below.


Files
-----

* omnipay_callback.php
* omnipay_success.php
* includes/modules/payment/omnipay.php
* includes/modules/payment/omnipay
* includes/languages/english/modules/payment/omnipay.php

Requirements
------------

* cURL with SSL support compiled into PHP
* A Fat Omnipay account (and of course, an Internet Merchant Facility setup for this account)

Testing
-------

While in Test Mode you can use any of the card numbers detailed.
Card No.: 5105105105105100
cvv: 999,
Card No.: 5123450000000008
cvv: 100


