=== NGT Custom Google Reviews Pro ===
Contributors: netgreater
Tags: google reviews, reviews slider, google places api, outscraper, apify
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium Google Reviews plugin with custom database table storage, manual sync, automatic background sync, custom popup modal styling, complete typography control, precise word cutoffs, and native Block Editor integration.

== Description ==

**NGT Custom Google Reviews Pro** is a zero-bloat review aggregation suite engineered specifically for seamlessly integrating Google Places data into WordPress. Instead of relying on heavy third-party iframes, this plugin natively queries and caches data locally in a dedicated custom table, maximizing frontend loading speeds while giving you complete structural control over the client viewing experience.

By saving reviews to a custom database table, this plugin ensures your frontend load times remain blazing fast while protecting you from API rate limits. With advanced multi-provider support, you can now bypass Google's strict 5-review limit by utilizing third-party scraper APIs (Outscraper or Apify) or the Google Places Legacy API.

### Key Features
*   **Real-Time Live Preview Engine:** Fine-tune your layout, colors, and typography instantly from a streamlined, professional administrative dashboard featuring a responsive live visual node canvas.
*   **Complete Typography Control:** Set specific Font Sizes, Colors, and Font Weights (300-700) for every individual text element (Review Text, Customer Name, Verified Badge, and Google Review Text).
*   **Multi-Layout Architecture:** Choose between minimal text carousels, boxed card sliders, or static grid columns designed to look flawless across all mobile breakpoints.
*   **Multiple Data Sources:** Fetch reviews using the Google Places API (New), Google Places API (Legacy), Outscraper API, or Apify Scraper API. Helpful documentation links are embedded directly in the dashboard to assist with key retrieval.
*   **Local Database Storage & Caching:** Reviews are saved locally in a custom table, completely eliminating frontend API lag and preventing rate limits.
*   **Manual Review Creation & Deletion:** Easily add manual or custom reviews directly from the dashboard, or remove unwanted ones, managed via a clean 10-item paginated database table interface featuring a direct "Delete" action button.
*   **Flexible Automatic Background Sync:** Set your reviews to sync automatically every 12 hours, 24 hours, 7 days, 15 days, or 30 days via a lightweight background cron job.
*   **Decoupled "Read More" Logic:** Independently choose the Position (Below or Inline) and Click Behavior (Popup Modal, Scrollable Box, or Expand Inline). Includes dedicated color, size, and font-weight controls specifically for the trigger link.
*   **Customizable Popup Modal:** Open full reviews in a sleek popup modal (preventing section height stretching) with fully customizable modal background colors, text colors, and corner radius.
*   **Exact Word Cutoffs & Minimums:** Precisely truncate review text by custom word counts, or use the Minimum Word Count filter to automatically exclude overly short reviews.
*   **Hide Blank Reviews:** Toggle off rating-only reviews that do not contain written text.
*   **Custom Slider Controls:** Easily adjust the exact pixel sizes and colors for slider navigation arrows and dots.
*   **Native Gutenberg Block:** Drop the reviews directly into your pages using the native block editor or via a simple `[custom_google_reviews]` shortcode.
*   **Secure API Key Masking:** API keys and tokens are securely masked by default in the admin panel with a built-in visibility toggle.
*   **Strict Security & Internationalization:** Fully sanitized outputs block XSS vulnerabilities, while direct .pot file support ensures your reviews can be instantly translated.

== Installation ==

1. Upload the `custom-google-reviews` folder to the `/wp-content/plugins/` directory, or install the ZIP file via the WordPress plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the **Google Reviews** menu in your dashboard.
4. Under **Data Source Connection**, select your preferred API provider (Google New, Google Legacy, Outscraper, or Apify) and enter your API Key/Token and Place ID or Maps URL. Use the embedded help links if you need to locate your credentials.
5. Click **Save Settings**, then click **Sync Reviews Now** to populate your local database.
6. Configure your display settings, character cutoffs, Read More behaviors, colors, and typography.
7. Use the Gutenberg block "Google Reviews Pro" or the shortcode `[custom_google_reviews]` to display the slider on your site.

== Frequently Asked Questions ==

= Why are only 5 reviews showing when using the Google API? =
The Google Places API (New) limits responses to 5 relevant reviews. To bypass this and fetch all chronological reviews, switch your API Provider to **Outscraper API** or **Apify** in the settings.

= How do I hide reviews that have no text or are too short? =
Go to the Behavior & Display Rules section in your plugin settings and check the "Hide rating-only reviews" box. You can also enter a number into the "Min Character Length" field to automatically filter out reviews that do not meet your required length.

= Can I add or remove a review manually? =
Yes. Use the "Add Manual Review" form to inject a custom review into the database. To remove a review, scroll down to the "All Stored Reviews" table and click the red "Delete" button next to any entry.

= How do I customize the Popup Modal colors? =
When "Open Popup Modal" is selected under the Read More Click Behavior dropdown, a **Popup Modal Styling** row automatically appears allowing you to style its background color, text color, and corner radius.

= Are API keys secure? =
Yes, API keys are masked by default in the admin panel to prevent shoulder-surfing. You can toggle their visibility using the built-in eye icon.

== Changelog ==

= 2.7 =
* Feature: Upgraded review truncation engine to calculate by Word Count rather than Character Count for cleaner, more natural "Read More" breaks.
* Feature: Changed Minimum Length filter to evaluate Minimum Word Count instead of characters.
* Security: Hardened backend options processing by adding strict `sanitize_text_field` and `esc_url_raw` callbacks to the Settings API implementation.
* Security: Upgraded output escaping by deploying strict `esc_url()` and `esc_attr()` functions directly within HTML attributes on the frontend and admin dashboard.
* Fix: Minified dynamic inline CSS custom properties within the frontend wrapper to prevent strict caching plugins, server firewalls, or rigid themes from stripping typography styles (resolving the issue where Font Weights failed to apply).
* Fix: Fortified boolean evaluation logic for frontend display toggles (Google Logo, Verified Badge, Dates, Avatars) to guarantee consistent rendering across different server environments.

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