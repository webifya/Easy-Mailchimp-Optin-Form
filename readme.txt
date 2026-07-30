=== Easy Mailchimp Optin Form ===
Contributors: mahfuzar
Tags: mailchimp, email, newsletter, signup form, optin form, responsive form
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Mailchimp, create responsive subscription forms, choose from five templates, and embed forms using shortcodes.

== Description ==

Easy Mailchimp Optin Form provides a simple WordPress-native form builder for Mailchimp audiences.

Features:

* Connect with a Mailchimp Marketing API key.
* Automatically load Mailchimp audiences.
* Create and manage multiple opt-in forms.
* Choose from five responsive templates: Classic, Minimal, Card, Dark, and Inline.
* Optional first-name and last-name fields.
* Optional double opt-in confirmation.
* Custom title, description, button text, and success message.
* Secure form submissions using WordPress nonces and a honeypot field.
* Add or update subscribers through Mailchimp Marketing API v3.
* Embed each form with its generated shortcode.
* Backward-compatible `[mailchimp]` shortcode that displays the first available form.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from WordPress.org.
2. Activate **Easy Mailchimp Opt-in**.
3. Go to **Easy Mailchimp > Easy Mailchimp**.
4. Enter and save your Mailchimp API key.
5. Click **Test connection**.
6. Go to **Easy Mailchimp > Forms**.
7. Create a form and select a Mailchimp audience and template.
8. Copy the generated shortcode into a page, post, or widget.

Example:

`[easy_mailchimp_form id="form-ab12cd34"]`

== Frequently Asked Questions ==

= Where do I find my Mailchimp API key? =

Create an API key from your Mailchimp account's API key settings. The plugin detects the Mailchimp data center from the key suffix, such as `-us21`.

= Does the plugin support double opt-in? =

Yes. Enable **Require email confirmation** when creating or editing a form.

= Are the forms responsive? =

Yes. All five included templates adapt to mobile, tablet, and desktop widths.

= Can I create multiple forms? =

Yes. Every form has its own settings, audience, template, and shortcode.

= What happens to the old shortcode? =

The legacy `[mailchimp]` shortcode remains available and displays the first form created in the new form manager.

== Changelog ==

= 2.0.0 =
* Rebuilt the plugin around Mailchimp Marketing API v3.
* Added Mailchimp API connection testing and audience loading.
* Added a multiple-form management interface.
* Added five responsive form templates.
* Added generated per-form shortcodes.
* Added optional names, double opt-in, custom content, secure nonces, and honeypot protection.
* Removed reliance on the obsolete bundled MCAPI integration and remote 2014 design assets.

= 1.5 =
* Maintenance release.

= 1.3 =
* Fixed input field width issue.

= 1.1 =
* Fixed name and email icons.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
This is a major upgrade. After updating, connect your Mailchimp API key and create at least one form under Easy Mailchimp > Forms.
