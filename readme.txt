=== Luciditi Age Assurance Integration ===
Contributors: arissianluciditi, alikhallad
Tags: age verification, age estimation, age assurance, luciditi, privacy, DIATF, online safety act
Requires at least: 5.6
Tested up to: 6.5.2
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL-3.0-or-later

== Description ==

'Highly Effective' Age Verification into your website in less than 10 minutes.

Luciditi Age Assurance provides a low-friction user experience that is entirely privacy-preserving.  I.e. None of the data used to verify an individual is ever received by you, just a notification of above your desired age.

It can configured to operate as an 'Age Gate' (preventing any access without age verification or previous visit age verification) or in 'eCommerce mode' (based on an age-restricted product selection, prompting the user at checkout) when using WooCommerce.  

Geolocation features let you enable/disable age assurance in different countries and territories.  It can even detect when a VPN is used in an attempt to bypass your geolocation configuration.

Automatically display custom pages based on the visitor's status, such as first-time users, returning users or failed verifications.  Configure a whole host of options through the admin dashboard, including authentication, logging as well as geolocation/vpn detection.

= Users can prove their age using a variety of methods including =

* Age Estimation (selfie)
* ID document scan 
* Digital Identity 
* Online Banking (UK banks)
* Mobile Network Operator Lookup (if enabled, country specific)

Users visiting your site on a PC/Laptop can user 'realtime handover' in order to user their mobile camera for high quality imaging of selfies and ID documents.  All data used by the Luciditi platform is immediately deleted after use.

= Features =

* Integrate Luciditi Age Assurance SDK into your WordPress website in under 10 mins!
* Automatic display of custom landing pages based on visitor status
* Modes: Age Gate or Product Based (WooCommerce)
* Configure plugin settings: Min Age, Styling, User Messages, Geolocation, VPN Detection and more through the admin dashboard
* Download access logs for detailed information on verification attempts
* Minimal database calls to ensure website performance is not effected
* Exclude search engine bots from the verification process to avoid SEO complications

== Installation ==

- Upload the luciditi-age-assurance folder to the /wp-content/plugins/ directory or install the plugin through the WordPress plugins screen directly.
- Activate the plugin through the 'Plugins' screen in WordPress.
- Navigate to the 'Settings' => 'Age Assurance' section in the WordPress admin dashboard to configure the plugin settings.

== Plugin options ==

The plugin provides a Settings page in the WordPress admin dashboard where you can configure the various options. The Settings page consists of the following sections:

- General: Enable/disable the age assurance functionality, set the minimum age requirement, and retention period for database records
- API Credentials: Enter your Luciditi API username and API key
- Messages and Styling: Customize branding, landing pages content and general styling
- Configuration: - Enable step-up to ID document verification on estimation failure, Set Geographical rules for access restrictions and defaults, redirection rules, VPN detection

== Plugin Logs ==

You can download the plugin access logs from the admin dashboard. The logs contain detailed information about the verification attempts. To download the logs, click on the "Download Logs" button, and a file will be generated for download.

== Screenshots ==

1. Initial Age Assurance challenge dialog
2. Age Assurance introduction
3. Successfully completed Age Assurance
4. Landing Pages & Styling configuration

== Frequently Asked Questions ==

= Can I customize the landing pages? =
The plugin automatically displays custom landing pages based on the visitor's status. You can customize the content and styling of the landing pages through the plugin settings.

= How can I authenticate the Luciditi Age Assurance API? =
In the plugin options, enter your Luciditi API username and API key for authentication with the Luciditi Age Assurance API.

= How do I obtain an API key for Luciditi Age Assurance =
Visit https://luciditi.co.uk and signup for a free business account.

= How can I download the plugin access logs? =
Access logs can be downloaded from the Settings page by clicking on the "Download Logs" button.

= How do I enable WooCommerce mode =
In settings, on the General tab, change the 'Enable' dropdown to "WooCommerce (Product Based)".  Set your default age and save.  For each of your products you can then set the age restriction or set it at category level instead.  When the user adds an age restricted product to their basket, they will be prompted when they try and checkout unless they have already verified their age on a previous visit.


== Changelog ==

= 1.0.0 =
* Initial release.

= 1.0.1 =
* Updates and fixes.

= 1.0.2 =
* Added "self-declare" feature
* Added "under-age" redirection
* Added "geolocation" and geo access rules.

= 1.0.3 =
* Added support to WooCommerce
