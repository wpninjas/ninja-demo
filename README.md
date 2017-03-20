Ninja Demo
============

Ninja Demo is a commercial plugin available from [http://ninjademo.com](http://ninjademo.com). The plugin is hosted here on a public Github repository in order to better faciliate community contributions from developers and users alike. If you have a suggestion, a bug report, or a patch for an issue, feel free to submit it here. We do ask, however, that if you are using the plugin on a live site that you please purchase a valid license from the [website](http://ninjademo.com). We cannot provide support to anyone who does not hold a valid license key.

Ninja Demo is extremely easy to get up and running; this document will show you how.
<h3>1. Create a multisite installation of WordPress</h3>
Ninja Demo runs exclusively on multisite WordPress installations to create sandboxes for each of your users.
<ul>
 	<li><a href="http://codex.wordpress.org/Create_A_Network">Create a Network</a>.</li>
 	<li>Only use a subdirectory install for your demo site.</li>
 	<li>Make sure your site url and main site title are unique from each other. (i.e. localhost/demo/ - My Demo is <strong>not</strong> ok, while demo.mysite.com - My Demo <strong>is</strong> ok.)</li>
</ul>
Because of its reliance on multisite, it isn't possible to demonstrate multisite-specific products with Ninja Demo.
<h3>2. Network Activate Ninja Demo</h3>
Ninja Demo should be network activated to give it full access to the main site and all sandboxes.
<h3>3. Set up your Ninja Demo settings</h3>
Pick the site on your network that you'd like to create sandboxes from. Visit <a title="Plugin Settings" href="http://ninjademo.com/docs/usage/plugin-settings/">the Ninja Demo settings</a> and configure the options as you would like. At this point, you may want to leave your demo in Offline Mode until you have set up the demo site's content as you intend it to be presented to your users.
<h3>4. Set up your main site's content</h3>
All that's really left is setting up your site the way you would like for the demo. Some things you might consider:
<ul>
 	<li>What theme you want activated for your demo.</li>
 	<li>Install and activate any plugins that you will need available.</li>
 	<li>Create any pages, posts, or other site-specific content needed for your demo.</li>
</ul>
Once you're done, there is only one thing left to do.
<h3>5. Add your "Try Demo" shortcode</h3>
Once you've gotten your site setup the way you want, you'll add this shortcode to your site: [try_demo].

If you'd like, you can place this shortcode on your main site and clone another subsite by adding the source_id (the ID of the blog that you want to create a sandbox from) parameter to your shortcode: [try_demo source_id=4].
<h3>6. Turn off Offline Mode</h3>
This will turn your demo on for the world to see. Let people know about your demo and let the sand fly.
