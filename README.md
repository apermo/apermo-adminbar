# Apermo AdminBar #
* Contributors: apermo
* Tags: admin bar, adminbar, admin, developer, development, staging, robots, keyboard, shortcut
* Requires at least: 4.0
* Tested up to: 4.7.0
* Stable tag: 1.1.2
* License: GNU General Public License v2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin allows you to add links between a development, staging and live version of your website, and adds them to the AdminBar.

## Description ##

This plugin enhances the AdminBar and adds links to development, staging and live version of your website, furthermore it allows you to choose a color scheme of your AdminBar for all users on a website, including the frontend.
You also have to option to control the robots.txt visibility settings, in the newest version you get a watermark for posts that are in draft or scheduled, and a keyboard shortcut to hide the adminbar.

If you want to participate in the development [head over to GitHub](https://github.com/apermo/apermo-adminbar)!

## Installation ##

1. Upload the plugin files to the `/wp-content/plugins/apermo-adminbar` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the 'Apermo AdminBar' plugin through the 'Plugins' menu in WordPress
3. Open Settings -> Apermo AdminBar to set up the links and colors (currently you have to repeat this on all sites)

== Screenshots ==

1. The basic idea of the plugin, 3 instances of a website, with 3 distinct color schemes and quicklinks between the instances.
2. The Adminbar on the frontend, showing the info panel on the right and the watermark for a draft post. The info panel can be hidden with a click. And there are keyboard shortcuts to hide the whole adminbar and the watermark to see what the site looks like for a regular user.
3. The settings page, with the options for the first of the 3 default stages.
4. The import and export function on the settings page.
5. The settings page when the settings are set using the filter `add_filter( 'apermo-adminbar-sites', 'sites_filter' );`

## Frequently Asked Questions ##

### I have more than 3 sites, can I add more? ###
You can do so with `add_filter( 'apermo-adminbar-types', 'your_filter' );`

### I want more color schemes! ###
Feel free to add more, there are other plugins that do so. Or have a look at [wp_admin_css_color() in the WordPress Codex](https://codex.wordpress.org/Function_Reference/wp_admin_css_color)

### Can I save the color schemes to my theme? ###
Yes, you can. Simply add and alter the following example somewhere to the functions.php of your theme

<code>
add_filter( 'apermo-adminbar-sites', 'sites_filter' );

function sites_filter( $sites ) {
    $sites['dev']['url'] = 'http://dev.your-site.tld';
    $sites['dev']['whitelist'] = array( 1,42 );
    $sites['staging']['url'] = 'http://staging.your-site.tld';
    $sites['live']['url'] = 'https://www.your-site.tld';
    return $sites;
}
</code>

### Can I hide, for example the development page, for certain users ###
Yes, you can use the filter `add_filter( 'apermo-adminbar-sites', 'sites_filter' );` with the option `whitelist` to allow access to the corresponding site only for the whitelisted user ids.
An option for this might be added in the future. 

### Can I change the default capability needed to access the quicklinks? ###
Yes, use `add_filter( 'apermo-adminbar-caps', 'sites_filter' );` and just return the desired capability.

### I do not need the watermark, how can I remove it? ###
The simplest way is to use `add_filter( 'apermo-adminbar-watermark', function( $bool ) { return false; } );` and turn the feature off.

### I do not need the statusbox, how can I remove it? ###
The simplest way is to use `add_filter( 'apermo-adminbar-statusbox', function( $bool ) { return false; } );` and turn the feature off.

### I do not need the keyboard shortcuts, how can I remove it? ###
The simplest way is to use `add_filter( 'apermo-adminbar-keycodes', function( $bool ) { return false; } );` and turn the feature off.


### How can I help with the development of this plugin? ###
Head over to the [GitHub Repository](https://github.com/apermo/apermo-adminbar) and start reading. Every bit of help is highly appreciated!

## Changelog ##



### 1.1.2 ###
* changed: Keyboard shortcuts had to be changed as they colided with windows standards
* Hide Watermark: Mac: CMD + CTRL + W - Win/Linux: ALT + SHIFT + W
* Hide Adminbar: Mac: CMD + CTRL + A - Win/Linux: ALT + SHIFT + A
* fixed: backend color scheme was overwritten if being set by a user prior to plugin activation

### 1.1.1 ###
* fixed: keyboard shortcut for watermark is now CTRL + D
* changed: made status icons bigger & clearer, changed color for scheduled status

### 1.1.0 ###
* added: keyboard shortcut CTRL + E to hide/show the adminbar in frontend
* added: watermark for draft/scheduled post, to remind an editor of the current post status
* added: keyboard shortcut CTRL + W to hide/show the watermark
* added: the statusbox, a box containing useful information about the post, directly visible in the frontend


### 1.0.0 ###
* fixed: do not add a spacer if no quicklinks are added
* added: option to hide stages by whitelisting the stage for given user ids
* added: option to set the default capability needed to use the quicklinks

### 0.9.11 ###
* fixed: css from admin_bar was loaded late, so &gt;a&lt; tags mostly where miscolored.

### 0.9.10 ###
* disable all options if filter is used
* fixed: robots.txt defaults were ignored

### 0.9.9 ###
* added support for multisite domain mapping
* added support for robots.txt

### 0.9.6 ###
* fixed typos

### 0.9.5 ###
* fixed bug for subfolder installations

### 0.9.4 ###
* added an export and import option
* minor improvements

### 0.9.3 ###
* Removed Scheme URL from saved options
* added filter 'apermo-adminbar-sites' to give the option of saving the settings in a theme

### 0.9.2 ###
* Some minor code improvements

### 0.9.1 ###
* Bug fixes and optimizations - Thanks to @kau-boy for the help

### 0.9.0 ###
* Initial Release