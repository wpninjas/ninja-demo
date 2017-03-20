Ninja Demo
============

Ninja Demo is a commercial plugin available from [http://ninjademo.com](http://ninjademo.com). The plugin is hosted here on a public Github repository in order to better faciliate community contributions from developers and users alike. If you have a suggestion, a bug report, or a patch for an issue, feel free to submit it here. We do ask, however, that if you are using the plugin on a live site that you please purchase a valid license from the [website](http://ninjademo.com). We cannot provide support to anyone who does not hold a valid license key.

<h2>Setting Up Ninja Demo</h2>

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

<h2>Plugin Settings</h2>
This is an overview of the Ninja Demo settings page and the purpose of each setting.
<ul>
 	<li><strong>Offline Mode</strong> - Puts the demo into an "offline" state. Both new users who visit and those currently in sandboxes will be shown a message letting them know that the demo site isn't available. All current sandboxes will be removed.</li>
 	<li><strong>Prevent New Sandboxes</strong> - New visitors to your demo site will be greeted with the "offline" message, but users who currently have sandboxes will not be disturbed.</li>
 	<li><strong>Auto-Login Users As</strong> - When a sandbox is created, the visitor to your demo site will be automatically logged-in with this user account.</li>
 	<li><strong>With this role </strong>- What role should this user be given in the new sandbox?</li>
 	<li><strong>Delete All Sandboxes</strong> - Does what it says on the tin. Everyone currently using a sandbox that is cloned from this site will be redirected to demo site.</li>
 	<li><strong>Restriction Settings</strong> - A white-list of wp-admin pages that a user can access. This doesn't give users capabilities that their role doesn't permit. For example, if your plugin has an admin menu that is restricted to admins, only admin users will be able to see it if the page is checked. <em>Anything in the wp-admin that isn't checked will not be accessible by anyone who is not a network admin.</em></li>
 	<li><strong>Enable Logging </strong>- Create a log file for every sandbox created. These are saved in: WP_CONTENT/nd-logs/</li>
</ul>

<h2>Shortcodes</h2>
<ul>
 	<li><strong>[try_demo source_id=] - </strong>This simple shortcode outputs everything you need to allow your users to create their first sandbox. The <strong>source_id</strong> parameter is optional. If it is specified, the sandbox will clone the desired blog_id. If it is omitted, the <strong>source_id</strong> will default to the current blog_id. You can place this in a text widget or any page or post to display a simple anti-spam question and a button to begin the demo.</li>
 	<li><strong>[is_sandbox][/is_sandbox] - </strong>Use this shortcode to wrap any content that you want to only be displayed if the user is in a sandbox.</li>
 	<li><strong>[is_not_sandbox][/is_not_sandbox] - </strong>Use this shortcode to wrap any content you only want displayed on the main site (non-sandbox).</li>
 	<li><strong>[is_sandbox_expired][/is_sandbox_expired] - </strong>Use this shortcode to wrap any content that you want displayed to a user returning to an expired sandbox.</li>
  
<h2>Action Hooks</h2>  
  These are the action hooks currently available in the Ninja Demo plugin, along with a description of each. If you are in need of an action hook that isn't currently available, please feel free to contact us or submit a support ticket with the suggestion. We are happy to add reasonable hooks to the plugin.
<ul>
 	<li><strong>nd_ip_lockout</strong> - Fired <em>after</em> a user IP address is locked out. Passes one argument, the user's $ip.</li>
 	<li><strong>nd_create_sandbox</strong> - Fired <em>after</em> a new sandbox is created. Passes one argument, the $id of the newly created blog.</li>
 	<li><strong>nd_delete_sandbox</strong> - Fired <em>before</em> a sandbox is deleted, whether automatically or manually. Passes one argument, the $id of the blog about to be deleted.</li>
</ul>

<h2>Filter Hooks</h2>  
These are the filters currently available in the Ninja Demo plugin, along with a description of each. If you are in need of a filter that isn't currently available, please feel free to contact us or submit a support ticket with the suggestion. We are happy to add reasonable filters to the plugin.
<h4>Error Messages</h4>
<ul>
 	<li><strong>nd_offline_msg</strong> - Message shown to all users when the demo is in "offline mode" or to new users when the "prevent new sandboxes" option is selected. Found in <em>classes/restrictions.php</em> Line 48 and <em>classes/sandbox.php </em>Line 71.</li>
 	<li><strong>nd_block_msg</strong> - Message shown to all users when a page has been disallowed. Found in <em>classes/restrictions.php</em> Lines 194, 200, 206, 225, 231, and 305.</li>
</ul>
<h4>Access Restrictions</h4>
<ul>
 	<li><strong>nd_allowed_pages</strong> - Used to white-list pages for view that do not appear in the admin menu and therefore aren't selectable in the Ninja Demo settings. Passed an array of allowed pages like: array( 'options.php', 'index.php' ). Found in <em>classes/restrictions.php</em> Line 93 (<a href="https://gist.github.com/wpnzach/c2fcf67f501ef5ef493579fdbaa0b92e">See here for a usage example</a>)</li>
 	<li><strong>nd_show_menu_pages</strong> - Passed an array of top-level menu pages that were white-listed in the Ninja Demo settings. This can be used to show or hide top-level admin pages to specific users or specific sandboxes. Found in <em>classes/restrictions.php</em> Line 98.</li>
 	<li><strong>nd_show_submenu_pages</strong> - Passed an array of submenu pages that were white-listed in the Ninja Demo settings. This can be used to show or hide submenu pages to specific users or specific sandboxes. Found in <em>classes/restrictions.php</em> Line 99.</li>
 	<li><strong>nd_allowed_cpts</strong> - Passed an array of allowed post types gathered from the Ninja Demo settings. The array is an associative array of arrays structured like: array( 'post' =&gt; array( 'edit' =&gt; 1, 'new' =&gt; 0 ), 'cpt' =&gt;array( 'edit' =&gt; 1, 'new' =&gt; 1 ) ). Again, this is a white-list, so post types that are not on this list will be unaccessible.  Found in <i>classes/restrictions.php</i> Line 170.</li>
 	<li><strong>nd_allowed_cts</strong> - Passed an array of allowed taxonomies gathered from the Ninja Demo settings. The array is an associative array of arrays structured like: array( 'post_type' =&gt; 'categories' =&gt; array( 'edit' =&gt; 1 ) ). Again, this is a white-list, so taxonomies that are not on this list will be unaccessible. Found in <em>classes/restrictions.php</em> Line 173.</li>
</ul>
<h4>Sandboxes</h4>
<ul>
 	<li><strong>nd_global_tables</strong> - An array of tables that should <em>not</em> be cloned when creating a new sandbox. Found in <em>classes/sandbox.php </em>Line 54.</li>
 	<li><strong>nd_create_redirect</strong> - After a sandbox has been successfully created, this is the url that the user will be redirected to. It is passed the address of the current site, along with the blog id for the newly created sandbox. Found in <em>classes/sandbox.php</em> Line 638.</li>
 	<li><strong>nd_purge_sandbox</strong> - When a sandbox is purged, or automatically deleted, this filter is passed a bool and the $blog_id. If false is returned from the filter, the sandbox will not be purged. Can be used to keep specific sandboxes alive longer than the 'nd_sandbox_lifespan' setting. Found in <em>classes/sandbox.php</em><em> </em>Line 279.</li>
 	<li><strong>nd_sandbox_lifespan</strong> - By default, sandboxes are destroyed after 15 minutes of inactivity. This filter is passed the amount of idle time allowed in seconds. Found in <em>classes/sandbox.php</em> Line 304.</li>
 	<li><strong>nd_activate_plugin</strong> - When a new sandbox is created, should active plugins be re-activated on the new sandbox site? This filter is ran <em>individually</em> for <em>every</em> active plugin and is passed a bool false and the name of the plugin in question. Expects a bool; defaults to false. Found in <em>classes/sandbox.php </em>Line 617.</li>
</ul>


</ul>

<h2>Other Functions</h2>
Here are a few helpful methods and functions that can be used when customizing your demo for product. They can all be called from anywhere in your php files.
<ul>
 	<li><strong>Ninja_Demo()-&gt;is_admin_user()</strong> - Returns bool( true ) if the current user is a network admin. Wrapper for: current_user_can( 'manage_network_options' ).</li>
 	<li><strong>Ninja_Demo()-&gt;is_sandbox()</strong>  - Returns bool( true ) if the code is being used inside a sandbox.</li>
</ul>
