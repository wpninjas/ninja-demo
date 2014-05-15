=== Ninja Demo ===
Contributors: kstover, jameslaws
Donate link: http://ninjademo.com
Tags: demo, demonstration
Requires at least: 3.7
Tested up to: 3.9.1
Stable tag: 1.0.3

License: GPLv2 or later

== Description ==
Ninja Demo is a plugin designed to make it easy to create WordPress product demos. See our site: http://ninjademo.com for more detailed information.

== Screenshots ==

To see up to date screenshots, visit [ninjademo.com](http://ninjademo.com).

== Changelog ==

= 1.0.3 =

*Bugs:*

* Theme switching should now work whenever the Themes page is whitelisted.
* Theme options pages should now work if they are hooked onto the themes.php page.

*Changes:*

* Adding an exact page + querystring will now always allow that exact match to be shown. _wpnonce is always ignored.

= 1.0.2 =

*Changes:*

* Added a filter to the redirect page when a sandbox is created.
* Added support for 'localhost:port' DB hosts.
* Changed the activation filter so that it fires once per plugin and passes the plugin name each time.

= 1.0.1 =

*Bugs:*

* Fixed a bug that could cause activation to fail if other plugins used the EDD SL updater class.

= 1.0 =

* Initial plugin release.
