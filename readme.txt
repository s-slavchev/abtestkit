=== abtestkit - AB testing for WooCommerce ===
Contributors: abtestkit
Tags: ab testing, split testing, ab testing WooCommerce, split testing WooCommerce, a/b testing WooCommerce, conversion, optimization, experiment, a b testing WooCommerce, CRO,
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://www.abtestkit.io/
Author URI: https://www.abtestkit.io/

Increase WooCommerce Revenue with A/B Testing. Track Real Sales, Not Just Clicks.

== Description ==

= Increase WooCommerce Revenue with A/B Testing. Track Real Sales, Not Just Clicks =

Most A/B tests optimise clicks. abtestkit helps you optimise revenue.

Test product pages, pricing, images, copy and layouts inside WordPress, then see which version drives more orders and more revenue in your WooCommerce store.


### Why abtestkit?
* **Find hidden revenue in your store** - test the changes that affect orders, revenue per visitor, and overall sales.
* **Track real sales, not just clicks** - see which variation actually makes you more money.
* **Built for WooCommerce growth** - test product pages, pricing, images, descriptions, galleries and more.
* **Move faster without guesswork** - launch tests inside WordPress and let abtestkit show the winning version.
* **Protect your live store setup** - product testing is SKU & stock safe, with Version B kept out of public listings.
* **Works with the tools you already use** - Gutenberg, Elementor, Beaver Builder, Bricks, Oxygen, Brizy, Breakdance, and more.
* **Keep control of your data** - data stays in your WordPress database with privacy-friendly tracking.
* **Cache-aware testing for real-world WooCommerce sites – abtestkit helps reduce the risk of page cache distorting active A/B tests, with clear guidance when cache exclusions may be needed.

abtestkit helps WooCommerce store owners stop leaving revenue on the table.

[Privacy Policy](https://www.abtestkit.io/privacy-policy/)
[Terms and Conditions](https://www.abtestkit.io/terms-and-conditions/)

### Use cases
- Test whether a **product price change** increases revenue or quietly hurts sales.
- Find out which **product image or gallery** leads to more purchases.
- Compare two **product descriptions** to see which one converts better.
- Test different **call-to-action text** to increase clicks and completed orders.
- Compare **layouts, headlines and content blocks** on landing pages and product pages.
- Test **ACF templates** and custom product content without guessing.
- Let abtestkit analyse performance and **show you which version wins**.

**Every change on your site could lead to missed revenue. Test the change, find the winner, and apply it with confidence.**

### See the revenue impact of every test

abtestkit is built for WooCommerce users who care about commercial results, not vanity metrics.

Use it to answer questions like:

- Which product page version brings in more revenue?
- Does a lower price increase total sales, or just reduce margin?
- Which image, layout or copy leads to more completed purchases?
- Which variation gives you a better return from the same traffic?

If your store is getting traffic already, every untested product page could be hiding revenue upside.

abtestkit helps you find it.

== Installation ==

1. Upload the `abtestkit` folder to `/wp-content/plugins/`.  
2. Activate the plugin through the Plugins menu in WordPress.  
3. Open the abtestkit dashboard.
4. Click '+ Create New Test' and follow simple setup wizard.
5. Run the ab test, automatically reach the result, apply the winner.

### Key features
✅ WooCommerce product A/B testing with revenue-focused insights  
✅ Full-page testing for landing pages, sales pages and content pages
✅ CSS & HTML testing on products, pages &  posts
✅ Product SKU & stock safe testing architecture
✅ Test variables on products like sizes & colors
✅ Version B shadow product kept out of shop loops, search and public listings  
✅ Automatic winner detection using Bayesian confidence  
✅ Sticky visitor assignment for consistent test experiences  
✅ ACF compatible  
✅ Builder-friendly and cache-aware, with support for real-world WordPress and WooCommerce setups
✅ Privacy-friendly tracking with data stored in your WordPress database  

== Screenshots ==

1. See which version is winning with revenue, order rate, RPV, RPC and predicted monthly uplift.
2. Choose conversion goals like clicks, form submissions, add to cart or Orders (Revenue).
3. Create tests for pages, posts and WooCommerce products in a guided setup flow.

== Frequently Asked Questions ==

= What page builders are supported? =
abtestkit is designed to work with normal WordPress editing workflows and major builders including Gutenberg, Elementor, Beaver Builder, Bricks, Oxygen, Brizy, Breakdance, and more.
Full-page tests are designed for WordPress pages, posts, builder-created layouts and WooCommerce product pages. Because builders and themes can output content in different ways, heavily customised setups should be checked after launching a test to confirm impressions and conversions are being tracked as expected.

= What themes are supported? =
abtestkit is designed to work with standard WordPress and WooCommerce themes, including themes that use normal templates, product loops, product pages and checkout flows.
WordPress themes can customise product pages, AJAX carts, menus, templates and checkout behaviour in different ways. Some heavily customised setups may need additional testing or cache exclusions. abtestkit is built for real-world WooCommerce sites and is actively improved around compatibility edge cases.

= How are winners decided? =
abtestkit supports both manual and automatic testing. You can review the data yourself or let the plugin declare a winner using Bayesian confidence, then apply the winning version in one click.

= Is it compatible with Advanced Custom Fields? =
Yes, ACF works for WooCommerce product testing, pages and posts.

= Does the Version B shadow product show up in blog lists, shop/catalog pages, or site search as a duplicate? =
No. The Version B shadow product is kept out of front-end listings (latest posts loops, archives, product grids, internal search, etc.) so only one "real" page/product appears to users.

= How are visitors assigned to A vs B? =
Visitors are assigned consistently (sticky) so they keep seeing the same version during the test. Distribution is split evenly by default.

= Does abtestkit use cookies or browser storage? =
Yes. abtestkit uses a small first-party cookie to remember which version of a test a visitor has been assigned to, so they keep seeing a consistent version during the test.

The cookie name starts with `abtestkit_pt_` and stores only the assigned variant, such as `A` or `B`. By default, this lasts for 30 days unless changed by the site owner.

For click conversion tracking, abtestkit may also use sessionStorage to prevent the same click being counted repeatedly in one browsing session.

abtestkit does not store names, email addresses, or raw IP addresses in these cookies or browser storage. Site owners should include abtestkit in their cookie consent setup where applicable.

= Is it compatible with variable products? =
Yes, abtestkit allows you to split test WooCommerce variable products. 

= How does product testing work? =
WooCommerce product tests use a Version B "shadow product" so you can test pricing, images, descriptions, galleries and other product content safely, without creating a second public-facing product in your store.

= Does Version B change SKU, stock, or orders? =
No. SKU, stock and ordering behaviour remain tied to the live WooCommerce product experience, so you can test commercial changes without creating duplicate product confusion or inventory conflicts.

= Do product versions stay consistent across the user experience? =
### Do product versions stay consistent across the user experience?

abtestkit is designed to keep each visitor’s assigned version consistent across the WooCommerce journey, including product listings, product cards, product pages, cart, checkout and order tracking.
Some themes and AJAX cart implementations customise how product data, prices and cart fragments are rendered. abtestkit continues to improve compatibility around these real-world WooCommerce behaviours so product tests remain reliable across more store setups.

= Where is data stored? =
All impression and click events are stored in your WordPress database (`wp_ab_test_events` table).

= Is this plugin free? =
Yes. abtestkit is free to use, with powerful WooCommerce and WordPress testing features available in the plugin with no limits.

= Does abtestkit collect telemetry? =
abtestkit can send anonymous telemetry if enabled. See the Privacy Policy and Terms and Conditions:
https://www.abtestkit.io/privacy-policy/
https://www.abtestkit.io/terms-and-conditions/

== Changelog ==

= 1.5.1 =
* Custom HTML testing added.
* Improved UI.
* Improved analytics for visitor interactions & currency reporting.
* Bug fixes and performance improvements.

= 1.5.0 =
* Custom CSS testing added.
* Improved UI.
* Bug fixes and performance improvements.

= 1.4.7 =
* Scroll depth goal added.
* Destination URL goal added.
* Improved UI.
* Bug fixes and performance improvements.

= 1.4.6 =
* Adds the new Test Health checker.
* Added clearer health states for active tests, including Good, Needs attention, and Broken.
* Improved onboarding.
* Bug fixes and performance improvements.

= 1.4.5 =
* Fixes a critical error that could occur when loading Version B for page/post tests using draft shadow rendering.

= 1.4.4 =
* Improved error logging.
* Bug fixes & performance improvements.

= 1.4.3 =
* AJAX cart & additional theme compatibility improvements.
* General UX improvements & bug fixes.

= 1.4.2 =
* Click targets for Desktop & Mobile on product split testing.
* General UX improvements & bug fixes.

= 1.4.1 =
* Click targets for Desktop & Mobile.
* Improved impression & conversion tracking for ab tests.
* General UX improvements & bug fixes.

= 1.4.0 =
* New Reusable Section Tests added, letting you test shortcodes.
* Improved impression & conversion tracking on A/B tests.
* Improved Version B cleanup when deleting product tests.
* General UX improvements & bug fixes.

= 1.3.7 =
* Test preview bugs fixed across all test types.
* General UX improvements & bug fixes.

= 1.3.6 =
* User Interface improvements and bug fixes.

= 1.3.5 =
* User Interface improvements and bug fixes.

= 1.3.4 =
* Added support for WooCommerce variable product A/B tests.
* Improved Version B previews in the product test wizard.
* General WooCommerce product testing fixes and stability improvements.

= 1.3.3 =
* Improved Dashboard and Test Performance page speed on slower websites.
* Added caching to performance reporting to help reduce server strain and timeout issues.
* Improved event query performance for more reliable reporting.
* Improved HTTP traffic exclusion so insecure visits do not distort A/B test data.
* Refined Version B editing to make the split testing setup flow clearer.
* Added further stability and compatibility fixes across the plugin.

= 1.3.2 =
* Fixed critical tracking reliability issues for test impressions
* Fixed edge cases where non-secure page views could interfere with reporting
* Improved frontend variant consistency and data accuracy
* Improved performance dashboard reliability

= 1.3.1 =
* Bug fixes

= 1.3.0 =
* Major release: Orders (revenue) testing added
* Major release: Test performance dashboard added
* Bot detection and blocking from test metrics
* Bug fixes

= 1.2.1 =
* Bug fixes & stability improvements

= 1.2.0 =
* ACF Compatibility for WooCommerce products
* Improved Post & Page testing
* Improved SEO controls

= 1.1.1 =
* Packaging fixes

= 1.1.0 =
* Major Release: Full WooCommerce Product A/B Testing
* Builder-agnostic support (Elementor, shortcodes, custom templates compatible)
* New product "shadow" architecture for accurate checkout & order tracking
* Improved Test Builder Wizard & Review flow
* Preview and variant handling improvements
* Performance and stability enhancements


= 1.0.10 =
* Bug fixes & stability improvements
* Fixed edge case in test wizard when navigating backwards
* Prevented stale selections persisting across test types
* Improved reliability of test configuration flow

= 1.0.9 =
* Test info Dropdown added to dashboard

= 1.0.8 =
* Manual tests added
* Fast testing added
* UI on Dashboard and Test Creation Wizard improved

= 1.0.7 =
* Previewing version improvements
* UX improvements
* UI on Dashboard and Test Creation Wizard improved
* UI on Pages, Posts & Products added

= 1.0.6 =
* Blog post testing added
* Version B pages & posts title UI improvements

= 1.0.5 =
* Improved WooCommerce Product test creation UI

= 1.0.4 =
* New: WooCommerce Product compatibility
* New: Off page click conversion goals.
* Improved: Create test wizard, onboarding wizard, abtestkit dashboard.

= 1.0.3 =
* Improved compatibility for major builders.  

= 1.0.2 =
* New: Full-page A/B testing with clean, guided wizard flow.  
* New: Compatibility with all major page builders (Gutenberg, Elementor, Beaver Builder, etc).  
* New: Improved caching support for page-based tests.  
* Improved: Faster tracking with privacy-friendly event logging.  
* Minor: UI cleanup and code refinements for a smoother user experience.

= 1.0.1 =
* Fixed: Activation redirect now runs reliably so the onboarding wizard opens immediately after first activation.  
* Improved: Activation logic hardened for multisite and bulk activation contexts.  
* Minor: Internal code clean-up for future stability.

= 1.0.0 =
* Initial public release of abtestkit - simple A/B testing inside the WordPress Core Editor.  
* Supported blocks: buttons, headings, paragraphs, and images.  
* Automatic winner declaration with Bayesian evaluation.  
* Grouped test support.  
* Optional anonymous telemetry (opt-in).  

== Upgrade Notice ==

= 1.5.1 =
Adds Custom HTML testing to pages, posts and products. Improves reporting. Fixes bugs and improves performance.

= 1.5.0 =
Adds Custom CSS testing to pages, posts and products. fixes bugs and improves performance.

= 1.4.7 =
Adds scroll depth & Destination URL goals, fixes bugs and improves performance.

= 1.4.6 =
Adds the new Test Health checker, improves onboarding, fixes bugs and improves performance.

= 1.4.5 =
Fixes a critical error that could occur when loading Version B for page/post tests using draft shadow rendering.

= 1.4.4 =
Bug fixes & performance improvements.

= 1.4.3 =
AJAX cart & additional theme compatibility improvements.

= 1.4.2 =
Click targets for Desktop & Mobile for product ab testing. Big fixes and performance improvements.

= 1.4.1 =
Click targets for Desktop & Mobile. Big fixes and performance improvements.

= 1.4.0 =
Reusable section tests added.

= 1.3.7 =
Test preview bug fixes and User Experience improvements.

= 1.3.6 =
User Interface improvements and bug fixes.

= 1.3.5 =
User Interface improvements and bug fixes.

= 1.3.4 =
WooCommerce variable product compatibility added, WooCommerce product testing bug fixes and stability improvements.

= 1.3.3 =
Recommended update for performance, tracking accuracy, and admin usability improvements, especially on slower sites.

= 1.3.2 =
Fixed critical tracking reliability issues affecting test accuracy

= 1.3.1 =
Bug fixes

= 1.3.0 =
Orders (revenue) testing added & test performance dashboard

= 1.2.1 =
Bug fixes & stability improvements

= 1.2.0 =
ACF Compatibility for WooCommerce products & improved page/post testing

= 1.1.1 =
package fixes

= 1.1.0 =
Major Release: Full WooCommerce Product A/B Testing

= 1.0.10 =
Bug fixes & stability improvements

= 1.0.9 =
Test information Dropdown added to dashboard

= 1.0.8 =
Fast mode & Manual testing mode added

= 1.0.7 =
UX & Previewing version improvements

= 1.0.6 =
Blog post testing added & Version B title UI improvements

= 1.0.5 =
Improved WooCommerce Product test creation UI

= 1.0.4 =
Major update with WooCommerce Product compatibility and off page click conversion goals.


= 1.0.3 =
Improved compatibility for major builders.  
Recommended for all users. 

= 1.0.2 =
Major update introducing full-page testing and builder compatibility.  
Recommended for all users — cleaner UI, caching support, and faster results.

= 1.0.1 =
Fixes onboarding not opening automatically after activation and improves reliability on multisite setups.
Updating is strongly recommended to ensure a smooth first-time setup experience.

= 1.0.0 =
First public release - validate your ideas, grow your website, and reach your goals with A/B testing built directly into the WordPress block editor.