=== Smart Cookie Kit ===
Contributors: shark986
Donate link: https://paypal.me/modugnonicola
Tags: gdpr, eprivacy, cookie, cookie law, banner
Requires at least: 4.6
Tested up to: 5.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Preventive blocking of third party cookies for GDPR/EU Cookie Law/ePrivacy. Translatable, cacheable, lightweight, powerful!

== Description ==
!!! Version 2.2 of plugin is compatible with Gutenberg for translations
!!! Version 2.1 of plugin is fully compatible with WPML and Polylang to easly manage texts translations !!!
!!! Version 2 of plugin is GDPR compliant - Cookie name for user preferences is now "CookiePreferences-[wp_install_dir]" !!!

Like others plugin, Smart Cookie Kit blocks cookies until the visitor accepts them, but it unlocks them asyncronously (without refreshing the current page): this logic increases server performance, improve user experience, and, more important, makes the plugin compatible with cache plugins!!
Thinked for marketing-oriented sites, this plugin blocks standard scripts and conversion/remarketing scripts (including the "noscript" tag too).

Why I decided to create this plugin?
There are a lot of plugins for cookies management... But:

* Many work on the server, modifying some code portions directly in the output: if is there a cache system, it will cache the page in the version for the user that "fired" the cache creation (with modified scripts if the user has not accepted cookies, or with the original scripts if the cookies were accepted), and that version will be served to ALL visitors, regardless of their acceptance.
The problem: it is not possible use cache systems.

* When user accepts cookies, it is necessary to open another page to have original scripts working. A lot of plugin have done a step forward, making an automatic refresh of the visited page, but web servers have to work again to serve the "correct" page and this may be a serious problem with slow connections (think to mobile devices), heavy pages (without cached pages!!), pages with external resources (Youtube videos, Ads, Social Plugins, ...). Has it ever appened that you start reading some content or jump below to search content of interest and the page scrolls back on the top because of a refresh?!
The problem: worst server performance and user experience.

* Many lets specify a page that contains cookie policy (to link that in the banner), but often they show the cookie banner in that page too.
This is not a big problem, I know, but it is a detail! ;)

I searched for a plugins that would not unblock cookies directly in the output from the server, to permit the use of cache systems; more over, I would like "things" faster and smoother without page refreshes and heavy loads.
My research had not satisfactory results (for me), so I decided to write my own plugin!

The main functionality are the following:

* **It recognises and modify automatically** scripts, iframes and images to block them (the block happens directly in the output from the server).

* **It permits you to tag code** to block.

* It shows the cookie banner only if it is necessary.

* It includes in the banner the link to your cookie policy page, and it does not show the banner in that page!

* Once the user accepts the policy (or when policy is already accepted), **it unblocks and run scripts via javascript, without a page refresh**. Being via javascript, **the unblock happens even if there are active cache systems**!

== Frequently Asked Questions ==

= Is the plugin compatible with WPML and Polylang? =

From version 2.1, Smart Cookie Kit detects when a site is published in multiple languages with WPML or Polylang. In that case, it activates an option to insert the content of the banner in a mask similar to that for posts and pages, so it is possible to manage the contents translations following the normal translation procedure of the mentioned plugins.

= JavaScript error "google is not defined" =

This error occurs because "something" is trying to init the maps when the Google Map script is not loaded yet (it was blocked by Smart Cookie Kit!).
Give a read to this thread: https://wordpress.org/support/topic/uncaught-referenceerror-google-is-not-defined-5/

= Parse error: syntax error, unexpected '[' in smart-cookie-kit/plugin_options.php =

This problem is caused by the PHP version used on the web server. Please, change your web server configuration (or ask to your system administrator) to use a PHP version >= 5.4 (checking if others components raise errors).

= Does the plugin stores visitors policy acceptance? =

Yes, optionally. The plugin can save a log into the server every time a visitor updates his preferences.
The logs are stored and protected from public access in the "/wp-content/cookie-preferences-log/" directory.

= The banner is not responsive / On mobile the banner is not centered =

Depending on the theme, the default CSS rules may not be enough to view correctly the banner.
It may help modify the field "CSS for banner content" adding this rule: "box-sizing:border-box;"

= Is it possible to remove the minimized button when banner is hided? =

Yes, optionally. From the version 2.0.4 of Smart Cookie Kit :)


== Installation ==
To install the plugin and get it working..

1. Decompress the file 'smart-cookie-kit.zip' and upload the dir 'smart-cookie-kit' to the dir 'wp-content/plugins/' of your WordPress site.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The plugin is working now with default settings. You can configure it from "Settings" > "Smart Cookie Kit" menù.


== Screenshots ==
1. Opened banner example, with default settings
2. Closed banner example, with default settings
3. Backend help page
4. Backend graphic option page (1/2)
5. Backend graphic option page (2/2)
6. Backend graphic option page (mobile style section)
7. Backend graphic option page (desktop style section)
8. Backend logic option page
9. Backend banner translation custom post


== Changelog ==

= 2.3.2 =
* Fixed a XSS vulnerability (thanks to patchstack.com )

= 2.3.1 =
* Minor bug fix

= 2.3.0 =
* Added a compatibility mode for Facebook Pixel (for official and unofficial plugins - EXPERIMENTAL).
* Google reCaptcha block can be optionally disabled.
* SCK now manages the AutomateWoo "Session tracking" feature.
* It is now possible to know server side if current user accepted cookies (more details in the backend help page).
* Minor bug fix

= 2.2.4 =
* SCK was not blocking cookies on wp-login.php page. Fixed (thanks to @delbono72).
* SCK was not blocking some ShareThis cookies. Fixed.
* SCK is now compatible with Polylang PRO
* SCK is now compatible with GAinWP Google Analytics Integration for WordPress

= 2.2.3 =
* Autoptimize's author changed a filter in its code in version 2.6.0. Updated SCK to reflect this change
* Corrected a bug to remove all SCK data from the database on uninstall

= 2.2.2 =
* SCK is now compatible with Async Javascript
* Bridge theme changed its shortcode for adding Google Maps. SCK updated to correctly manage them.
* Added the Instagram embed script to the block of SCK.
* It is now possibile to INCLUDE additional Javascripts scripts to the block

= 2.2.1 =
* Minor bugs fix

= 2.2.0 =
* Smart Cookie Kit is officially compatible with Gutenberg (even if it is not used to manage translations)
* Updated the "Simple HTML Dom" Library to version 1.8.1 for compatibility with PHP 7.3.1
* Smart Cookie Kit now fires standard Javascript events to let you able to manage custom functionalities (more details in the "Help and support" page of the plugin)
* It is now possible to EXCLUDE some Javascripts from the block
* Added a security check for NGINX web servers
* Minor bugs fix

= 2.1.3 =
* SCK was blocking "Google Tag Manager by DuracellTomi" even if the option to block Google Tag Manager was disabled. Solved.
* Version 2.1.2 caused some graphic issues. Solved.
* Smart Cookie Kit did not show a placeholder for Google reCAPTCHA. Solved.
* Added native support for Google Maps added through the shortcode of Bridge theme.

= 2.1.2 =
* Fixed a minor bug for the maximized banner in the policy page.
* Added the possibility to unlock services when users scroll the page (implicit consent).

= 2.1.1 =
* Fixed a bug between Cache Enabler plugin and Customizr theme.

= 2.1.0 =
* Banner translations can be now easily managed like page and post translations via WPML and Polylang.
* Added a field to customize the placeholder text.
* The banner content and the placeholder text fields are now "rich text fields", so you can format the content and add links.
* If present, the link to the page that contains the cookie policy has been inserted automatically in the "Banner text" field.
* Changed the settings section of the plugin. Settings are available now on the main menù of the backend.
* Avada was adding Google Map directly in "contact" pages (not only through the Fusion Builder element): blocked!
* Enfold theme 4.4 was improved for better performance and changed how maps are handled: solved!
* Added an option to reload the web page when user disables cookies.
* Solved a compatibility problem with plugins that use "Simple HTML Dom" library.
* Solved a compatibility problem with "Google Tag Manager by DuracellTomi" plugin.

= 2.0.6 =
* Added native support for Google Maps added by Divi Builder, Avia Builder (Enfold theme), Fusion Builder (Avada theme), Visual Composer (Jupiter and others themes) and Cornerstone builder.
* Added some integrations with cache plugins (W3 Total Cache, Fastest Cache, WP-Rocket, Litespeed Cache) for optimizations with DEFER scripts attribute.
* Added compatibility with Divi and Cornerstone frontend builder.
* Solved minor bugs for compatibility with Microsoft Edge and Mozilla Firefox.

= 2.0.5 =
* Solved a minor bug related to the minimized banner.

= 2.0.4 =
* Added an option to show a "Content not available" placeholder where elements have been blocked in the page.
* Added an option to choose whether or not to show the minimized button to open the banner.
* Added a shortcode to get a link that opens the banner.
* Smart Cookie Kit is now compatibile with Autoptimize.
* Restored the backlayer option.

= 2.0.3 =
* The log directory has been moved from "/wp-content/plugins/smart-cookie-kit/logs/" to "/wp-content/cookie-preferences-log/".
* The log directory has been protected to avoid browser access to its content.
* Log files has been converted to a csv format.
* Minor compatibility issues has been fixed.
* Solved minor translations related bugs.
* Javascript code has been improved.

= 2.0.2 =
* Solved minor bugs.

= 2.0.1 =
* Solved warning: "The first argument should be either a string or an integer" in plugin_options.php on line 42
* Sometimes Google Tag Manager was not blocked.
* Sometimes Google Analytics was blocked even if anonymized.
* Solved minor bugs.

= 2.0.0 =
* Plugin was modified to be full GDPR compliant!
* Cookie name to save user preference is changed in "CookiePreferences-[wp_install_dir]"
* Scroll and click consent deprecated.
* Added in cookie and in server log informations about first cookie acceptance and following updates.
* It is possible now to handle Google Tag Manager.
* Client side code rewrited in pure Javascript: plugin now does NOT require jQuery.
* Solved minor bugs.

= 1.2.7 =
* Solves an error of the previous update.

= 1.2.6 =
* Yoast SEO compatibility. Solved a compatibility bug with Yoast on "application/ld+json" scripts.

= 1.2.5 =
* W3 Total Cache compatibility. SCK replaces known js scripts with its empty.js but W3TC minification minifies the empty script removing the reference to the original script, so SCK can't run the original script. Now SCK integrates a filter to exlude it's empty.js from the W3TC minification.

= 1.2.4 =
* Minor bug fix.

= 1.2.3 =
* Minor bug fix.

= 1.2.2 =
* Smart Cookie Kit blocks now scripts from tawk.to chat system.

= 1.2.1 =
* Smart Cookie Kit blocks now pixels from PixelYourSite plugin too.

= 1.2.0 =
* Made optional (even if it is active by default) the policy when user scrolls the page (thanks to Angelo for the suggestion).
* Added an offset to permit to the user to make a little scroll before trigger the implicit consent (thanks to @diegocanal for the suggestion - https://wordpress.org/support/topic/great-concept-beautifully-implemented).
* Added (optionally) option to implicit accept the policy when the user clicks on some elements of the page (it is possible to exclude some).
* It is now possible to save on the server (as well as on the user browser) a log of users that accept the policy (included the way of acceptance) - thanks to @woodypad for the suggestion, https://wordpress.org/support/topic/registra-i-consensi-records-consents)
* Added a debug mode

= 1.1.1 =
* Solved a bug: some themes don't print scripts in the footer. Added an option to insert scripts in the header of the page.

= 1.1.0 =
The previous version of the plugin was adding a layer to the page content to avoid clicks on any element in the page, so the user had to accept the cookie policy to get the content "available".
On this version I made that layer optional and disabled for default, thinking that this is a better choice for the search engine spiders.
Also added others options to customize the layout of the banner.

= 1.0.4 =
* Minor bug fix.

= 1.0.3 =
* Minor bug fix.

= 1.0.2 =
* IT Cookie law tutorial added.
* Minor bug fix (translations related).
* English translation added.

= 1.0.1 =
* Minor bug fix.

= 1.0.0 =
* Option page added.
* Plugin is ready for translations.

= 0.1.0 =
* Test version.

== Support ==
Please write on the WordPress official support area: https://wordpress.org/support/plugin/smart-cookie-kit
For questions, support or suggestions, you could write to: info[-at-]nicolamodugno[-dot-]it