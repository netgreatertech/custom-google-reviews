=== NGT Custom Google Reviews Pro ===
Contributors: netgreater
Tags: google reviews, reviews slider, google places api, outscraper, apify
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-bloat review aggregation suite engineered specifically for seamlessly integrating Google Places data into WordPress.

== Description ==

**NGT Custom Google Reviews Pro** is a zero-bloat review aggregation suite engineered specifically for seamlessly integrating Google Places data into WordPress. Instead of relying on heavy third-party iframes, this plugin natively queries and caches data locally in a dedicated custom database table, maximizing frontend loading speeds while giving you complete structural control over the client viewing experience.

By saving reviews locally, your site is completely protected from API rate limits and external latency. With advanced multi-provider support, you can effortlessly bypass Google's strict 5-review API limitation by connecting scraping integrations like Outscraper or Apify, or using the Google Places Legacy API.

### Key Features
*   **Multiple Data Sources:** Fetch reviews using the Google Places API (New), Google Places API (Legacy), Outscraper API, or Apify Scraper API.
*   **Real-Time Live Preview Engine:** Fine-tune your layout, colors, and typography instantly from a streamlined administrative dashboard featuring a responsive live visual node canvas.
*   **Complete Typography Control:** Set specific Font Sizes, Colors, and Font Weights (300-700) for every individual text element, including the "Read More" links.
*   **Customizable Popup Modal & Behaviors:** Choose how your "Read More" links behave—open a clean Popup Modal (preventing any section height stretching), use a scrollable box inside the card, or expand inline. Fully customize popup background colors, text colors, and corner radius.
*   **Exact Character Cutoff & Minimums:** Precisely truncate review text by character count, or completely hide reviews that do not meet a minimum character length.
*   **Local Database Storage:** Reviews are saved locally in a custom table, completely eliminating frontend API lag.
*   **Manual Review Creation & Deletion:** Easily add manual reviews directly from the dashboard, or delete unwanted reviews straight from the database table.
*   **Flexible Automatic Background Sync:** Set your reviews to sync automatically every 12 hours, 24 hours, 7 days, 15 days, or 30 days via a lightweight background cron job.
*   **Hide Blank Reviews:** Toggle off rating-only reviews that do not contain written text.
*   **Secure API Key Masking:** API keys and tokens are securely masked by default in the admin panel with a built-in visibility toggle.
*   **Three Layout Styles:** Choose between a Minimal Text Carousel, a Boxed Card Carousel, or a Static Grid.
*   **Native Gutenberg Block & Shortcode:** Drop your review matrix directly into any page using the native block editor or the `[custom_google_reviews]` shortcode.

== Installation ==

1. Upload the `custom-google-reviews` folder to the `/wp-content/plugins/` directory, or install the ZIP file via the WordPress plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the **Google Reviews** menu in your dashboard.
4. Under **Data Source Connection**, select your preferred API provider (Google New, Google Legacy, Outscraper, or Apify) and enter your API Key/Token. Helpful links are provided to locate your credentials.
5. Click **Save Settings**, then click **Sync Reviews Now** to populate your local database.
6. Configure your display settings, character cutoffs, Read More behaviors, colors, and typography.
7. Use the Gutenberg block "Google Reviews Pro" or the shortcode `[custom_google_reviews]` to display the slider on your site.

== Frequently Asked Questions ==

= Why are only 5 reviews showing when using the Google API? =
The Google Places API (New) limits responses to 5 relevant reviews. To bypass this and fetch all chronological reviews, switch your API Provider to **Outscraper API** or **Apify** in the settings.

= How do I hide reviews that have no text? =
Go to the **Behavior & Display Rules** section and check the "Hide rating-only reviews" box. You can also set a "Min Character Length" to hide overly short reviews.

= Can I add or remove a review manually? =
Yes. Use the "Add Manual Review" form to inject a custom review into the database. To remove a review, scroll down to the "All Stored Reviews" table and click the red "Delete" button next to any entry.

= How do I customize the Popup Modal appearance? =
When "Open Popup Modal" is selected under Read More Click Behavior, a **Popup Modal Styling** row will appear allowing you to customize its background color, text color, and corner radius.

= Are API keys secure? =
Yes, API keys are masked by default in the admin panel to prevent shoulder-surfing. You can toggle their visibility using the eye icon.

== Changelog ==

= 2.6 =
* Feature: Completely overhauled and modernized the admin settings dashboard with a clean, professional flexbox UI and inline typography matrices.
* Feature: Added comprehensive Font Weight controls (300-700) for all text elements (Review Text, Customer Name, Verified Badge, Google Review Text).
* Feature: Added dedicated Color, Size, and Weight controls specifically for the "Read More" link.
* Feature: Added Slider Arrow and Dot size controls in the backend.
* Feature: Added a Minimum Review Character Length filter to automatically exclude short reviews.
* Feature: Added a "Delete" action button directly inside the "All Stored Reviews" database table for easy review management.
* Enhancement: Decoupled "Read More" logic – independently choose the Position (Below or Inline) and Click Behavior (Modal, Scrollable Box, Expand).
* Enhancement: Added helpful documentation links directly under API key/token inputs to easily find credentials.
* Fix: Resolved an issue where boolean display elements (Google Review Text, Verified Badge) would occasionally disappear on the frontend.
* Fix: Corrected UI alignment for the API Key/Token visibility toggle (eye icon).
* Fix: Fixed a CSS clipping issue with large Star Ratings inside the backend Live Preview engine.

= 2.5 =
* Feature: Added customizable Popup Modal styling settings (Background Color, Text Color, and Corner Radius).
* Feature: Added conditional admin UI row to hide/show modal styling options based on Read More behavior selection.
* Enhancement: Upgraded character cutoff handling to precise PHP string truncation (`mb_substr`) with real-time live preview responsiveness.
* Enhancement: Added autoplay pause handling when popup modals are opened on the frontend.

= 2.4 =
* Feature: Added support for Outscraper API and Apify Scraper API integration.
* Feature: Added support for Google Places API (Legacy) endpoint.
* Feature: Added "Add Manual Review" form to inject custom reviews into the database table.
* Feature: Added option to hide blank/rating-only reviews.
* Feature: Added backend pagination for the "All Stored Reviews" database table (displays 5 per page).
* Feature: Added 15-day and 30-day intervals for automatic background syncing.
* Enhancement: Masked API Key input fields for better security with a visibility toggle.
* Enhancement: Reorganized dashboard layout to move database tables and manual entry forms below the Live Preview Engine.

= 2.3 =
* Initial release of the Pro version with custom database storage, WP Cron sync, block editor integration, and real-time live preview engine.