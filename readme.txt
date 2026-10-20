=== Custom Google Reviews Pro ===
Contributors: netgreater
Tags: google reviews, reviews slider, google places api, outscraper, apify
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium Google Reviews plugin with custom database table storage, manual sync, automatic background sync, and native Block Editor integration.

== Description ==

Custom Google Reviews Pro allows you to seamlessly pull, store, and display your Google Reviews on your WordPress website. 

By saving reviews to a custom database table, this plugin ensures your frontend load times remain blazing fast while protecting you from API rate limits. With the recent updates, you can now bypass Google's strict 5-review limit by utilizing third-party scraper APIs or the Google Places Legacy API.

### Key Features
*   **Multiple Data Sources:** Fetch reviews using the Google Places API (New), Google Places API (Legacy), Outscraper API, or Apify Scraper API.
*   **Local Database Storage:** Reviews are saved locally in a custom table, completely eliminating frontend API lag.
*   **Manual Review Creation:** Easily add manual or custom reviews directly from the dashboard to display alongside your Google reviews.
*   **Automatic Background Sync:** Set your reviews to sync automatically every 12 hours, 24 hours, 7 days, 15 days, or 30 days via a lightweight background cron job.
*   **Hide Blank Reviews:** Toggle off rating-only reviews that do not contain written text.
*   **Three Layout Styles:** Choose between a Minimal Text Carousel, a Boxed Card Carousel, or a Static Grid.
*   **Deep Customization:** Control star colors, text weights, badge colors, card radius, and background colors directly from the settings matrix.
*   **Native Gutenberg Block:** Drop the reviews directly into your pages using the native block editor or via a simple `[custom_google_reviews]` shortcode[cite: 5, 8].
*   **Live Admin Preview:** See exactly how your reviews will look on the frontend in real-time as you tweak the typography and color settings.

== Installation ==

1. Upload the `custom-google-reviews` folder to the `/wp-content/plugins/` directory, or install the ZIP file via the WordPress plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the **Google Reviews** menu in your dashboard.
4. Under **Data Source Connection**, select your preferred API provider (Google, Outscraper, or Apify) and enter the required API Key/Token.
5. Click **Save Configurations Matrix**, then click **Sync Reviews Now** to populate your local database.
6. Configure your display settings, colors, and typography.
7. Use the Gutenberg block "Google Reviews Pro" or the shortcode `[custom_google_reviews]` to display the slider on your site.

== Frequently Asked Questions ==

= Why are only 5 reviews showing when using the Google API? =
The Google Places API (New) limits responses to 5 relevant reviews. To bypass this and fetch all chronological reviews, switch your API Provider to **Outscraper API** or **Apify** in the settings.

= How do I hide reviews that have no text? =
Go to the Behavior & Display Rules section and check the "Hide Blank Reviews" box. 

= Can I add a review manually? =
Yes. Scroll to the bottom of the settings page and use the "Add Manual Review" form to inject a custom review into the database. It will immediately appear in your slider.

= Are API keys secure? =
Yes, API keys are masked by default in the admin panel to prevent shoulder-surfing. You can toggle their visibility using the eye icon.

== Changelog ==

= 2.4 =
* Feature: Added support for Outscraper API to bypass Google's 5-review limit.
* Feature: Added support for Apify Scraper API integration.
* Feature: Added support for Google Places API (Legacy) endpoint.
* Feature: Added "Add Manual Review" form to inject custom reviews into the database table.
* Feature: Added option to hide blank/rating-only reviews.
* Feature: Added backend pagination for the "All Stored Reviews" database table (displays 5 per page).
* Feature: Added 15-day and 30-day intervals for automatic background syncing.
* Enhancement: Masked API Key input fields for better security with a visibility toggle.
* Enhancement: Reorganized dashboard layout to move database tables and manual entry forms below the Live Preview Engine.
* Fix: Handled dynamic API field rendering based on the selected provider.

= 2.3 =
* Initial release of the Pro version with custom database storage, WP Cron sync, block editor integration, and real-time live preview engine[cite: 5].