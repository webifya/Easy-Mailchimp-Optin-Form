=== Easy Mailchimp Optin Form ===
Contributors: mahfuzar
Tags: mailchimp, newsletter, signup form, optin form, email marketing
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build responsive Mailchimp opt-in forms with AJAX, templates, GDPR, spam protection, analytics, Gutenberg, Elementor, tags, groups, and import/export.

== Description ==

Easy Mailchimp Optin Form is a modern WordPress form suite for Mailchimp Marketing API v3, developed by Mahfuzar Rahman of Web Ninja LLC.

Plugin website: https://webninjallc.com

Features:

* Connect and test a Mailchimp Marketing API key.
* Automatically load Mailchimp audiences and member counts.
* Create and manage multiple forms.
* Ten responsive templates: Classic, Minimal, Card, Dark, Inline, Gradient, Split, Bordered, Soft, and Bold.
* Professional compact form-builder interface with live preview.
* AJAX submissions without page reloads.
* First name, last name, phone, GDPR consent, custom success message, and optional redirect.
* Single or double opt-in.
* Mailchimp tags and visual interest-group selection.
* Visual Mailchimp merge-field mapping controls.
* Per-form colors, border radius, and scoped custom CSS.
* Honeypot, Cloudflare Turnstile, or Google reCAPTCHA protection.
* Per-form views, submissions, and conversion analytics.
* Gutenberg block.
* Elementor widget when Elementor is active.
* JSON form import and export.
* Generated shortcode for every form.
* Backward-compatible `[mailchimp]` shortcode.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from WordPress.org.
2. Activate **Easy Mailchimp Opt-in**.
3. Open **Easy Mailchimp > Easy Mailchimp**.
4. Enter your Mailchimp API key and test the connection.
5. Configure optional Turnstile or reCAPTCHA keys.
6. Open **Easy Mailchimp > Forms** and create a form.
7. Add the generated shortcode, Gutenberg block, or Elementor widget to your page.

Example shortcode:

`[easy_mailchimp_form id="form-ab12cd34"]`

== Frequently Asked Questions ==

= Does it support double opt-in? =

Yes. Double opt-in can be enabled separately for each form.

= Can I assign tags and interest groups? =

Yes. Add comma-separated tags and select available Mailchimp interest groups directly in the form editor.

= Can I map Mailchimp merge fields? =

Yes. Supported audience merge fields are loaded automatically and can be mapped with dropdown controls.

= Can I add custom CSS? =

Yes. Each form includes scoped custom CSS. Use `{{FORM}}` as the selector placeholder so styles only affect that form.

= Does it work with Elementor? =

Yes. An Easy Mailchimp Form widget is registered when Elementor is active.

= Is a Gutenberg block included? =

Yes. Search for **Easy Mailchimp Form** in the block inserter.

= Which spam protection methods are supported? =

Built-in honeypot, Cloudflare Turnstile, and Google reCAPTCHA v2.

= Are forms responsive? =

Yes. All included templates adapt to mobile, tablet, and desktop layouts.

== Changelog ==

= 3.1.2 =
* Updated author name to Mahfuzar Rahman and website to Web Ninja LLC.
* Aligned WordPress.org stable-tag metadata with the release version.
* Hardened the GitHub deployment workflow for tagged releases.

= 3.1.1 =
* Fixed the PHP parse error in the premium form builder.
* Improved PHP 7.4 compatibility for Gutenberg and Elementor integration.

= 3.1.0 =
* Added a professional compact form-builder interface.
* Fixed and expanded the live form preview.
* Replaced JSON interest-group and merge-field inputs with visual controls.
* Added per-form colors, radius controls, and scoped custom CSS.

= 3.0.0 =
* Rebuilt the plugin as a premium-grade Mailchimp form suite.
* Added ten responsive templates and live admin preview.
* Added AJAX subscriptions, GDPR consent, optional redirect, phone field, tags, groups, and custom merge-field mapping.
* Added Honeypot, Cloudflare Turnstile, and Google reCAPTCHA protection.
* Added form analytics, Gutenberg block, Elementor widget, and JSON import/export.
* Improved API handling, validation, accessibility, and mobile responsiveness.

= 2.0.0 =
* Migrated from the obsolete MCAPI library to Mailchimp Marketing API v3.
* Added multiple forms, five responsive templates, and generated shortcodes.

= 1.5 =
* Maintenance release.

= 1.3 =
* Fixed input field width issue.

= 1.1 =
* Fixed name and email icons.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 3.1.2 =
Use this release for the corrected author metadata and synchronized WordPress.org deployment metadata.
