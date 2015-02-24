=== Ninja Demo ===
Contributors: kstover, jameslaws
Donate link: http://ninjademo.com
Tags: demo, demonstration
Requires at least: 3.7
Tested up to: 4.1.1
Stable tag: 1.0.12

License: GPLv2 or later

== Description ==
Ninja Demo is a plugin designed to make it easy to create WordPress product demos. See our site: http://ninjademo.com for more detailed information.

== Screenshots ==

To see up to date screenshots, visit [ninjademo.com](http://ninjademo.com).

== Upgrade Notice ==

= 1.0.13 (24 February 2014) =

*Bugs:*

* Whitelisting the customizer should now work properly for demo sandboxes.

== Changelog ==

= 1.0.13 (24 February 2014) =

*Bugs:*

* Whitelisting the customizer should now work properly for demo sandboxes.

= 1.0.11 (12 December 2014) =

*Bugs:*

* Fixed a bug with creating sandboxes in environments with multiple source ids.

= 1.0.9 (07 October 2014) =

*Changes:*

* Users are now created dynamically per-sandbox. You can select the role that is created from the Ninja Demo menu.

*Bugs:*

* Sandboxes should now be created more quickly.
* Fixed a bug with Ninja Demo CSS that added an unnecessary top margin.

= 1.0.8 (23 July 2014) =

*Bugs:*

* Fixed a bug that could prevent sandboxes from being deleted properly.

= 1.0.7 (18 July 2014) =

*Changes:*

* Added action hooks that fire before and after the anti-spam question in the try_demo shortcode.

= 1.0.6 (10 July 2014) =

*Changes:*

* Added a filter to the length of time a user must wait before creating a new sandbox.

= 1.0.5 (3 July 2014) =

*Bugs:*

* Fixed a bug that could cause extra space in the header.
* Fixed a bug that could prevent licenses from being activated properly.
* Fixed a bug that could cause wp-admins to redirect when accessed from sandboxes.

*Changes:*

* Added a restriction that limit an IP address to one sandbox creation per 10 minutes.

= 1.0.4 (10 June 2014) =

*Bugs:*

* Fixed a bug that caused main sites to be deleted in some circumstances.

*Changes:*

* Any subsite can now be cloned using the [try_demo source_id=4] shortcode, where 4 is the blog_id of the site you want to base the sandbox on. Defaults to the current blog_id.

* The only user that will be added to the sandbox is the one set to auto-login, with the appropriate role. For security reasons, it is best to remove this auto-login user from any other site.

* Added a new admin page to the Network Admin Dashboard. The license field as well as an overview of sandboxes makes up this page.

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
