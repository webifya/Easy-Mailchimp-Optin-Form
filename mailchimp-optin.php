<?php
/**
 * Plugin Name: Easy Mailchimp Opt-in
 * Plugin URI: https://wordpress.org/plugins/easy-mailchimp-opt-in/
 * Description: Premium Mailchimp form builder with live preview, responsive templates, AJAX subscriptions, GDPR, spam protection, analytics, Gutenberg and Elementor support.
 * Version: 3.1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Mahfuzar
 * Author URI: https://mahfuzar.info
 * License: GPLv2 or later
 * Text Domain: easy-mailchimp-optin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMO_Premium {
	const VERSION  = '3.1.1';
	const SETTINGS = 'emo_settings';
	const FORMS    = 'emo_forms';
	const STATS    = 'emo_stats';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_ajax_emo_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_emo_audience_schema', array( $this, 'ajax_audience_schema' ) );
		add_action( 'wp_ajax_emo_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_emo_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'admin_post_emo_save_form', array( $this, 'save_form' ) );
		add_action( 'admin_post_emo_delete_form', array( $this, 'delete_form' ) );
		add_action( 'admin_post_emo_export_forms', array( $this, 'export_forms' ) );
		add_action( 'admin_post_emo_import_forms', array( $this, 'import_forms' ) );
		add_shortcode( 'easy_mailchimp_form', array( $this, 'shortcode' ) );
		add_shortcode( 'mailchimp', array( $this, 'legacy_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
	}

	public function register_settings() {
		register_setting(
			'emo_settings_group',
			self::SETTINGS,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $input ) {
		$providers = array( 'honeypot', 'turnstile', 'recaptcha' );
		$provider  = isset( $input['spam_provider'] ) ? $input['spam_provider'] : 'honeypot';
		return array(
			'api_key'                => sanitize_text_field( isset( $input['api_key'] ) ? $input['api_key'] : '' ),
			'spam_provider'          => in_array( $provider, $providers, true ) ? $provider : 'honeypot',
			'turnstile_site_key'     => sanitize_text_field( isset( $input['turnstile_site_key'] ) ? $input['turnstile_site_key'] : '' ),
			'turnstile_secret_key'   => sanitize_text_field( isset( $input['turnstile_secret_key'] ) ? $input['turnstile_secret_key'] : '' ),
			'recaptcha_site_key'     => sanitize_text_field( isset( $input['recaptcha_site_key'] ) ? $input['recaptcha_site_key'] : '' ),
			'recaptcha_secret_key'   => sanitize_text_field( isset( $input['recaptcha_secret_key'] ) ? $input['recaptcha_secret_key'] : '' ),
		);
	}

	private function settings() {
		return wp_parse_args(
			get_option( self::SETTINGS, array() ),
			array(
				'api_key' => '',
				'spam_provider' => 'honeypot',
				'turnstile_site_key' => '',
				'turnstile_secret_key' => '',
				'recaptcha_site_key' => '',
				'recaptcha_secret_key' => '',
			)
		);
	}

	private function forms() {
		$forms = get_option( self::FORMS, array() );
		return is_array( $forms ) ? $forms : array();
	}

	private function templates() {
		return array(
			'classic' => 'Classic',
			'minimal' => 'Minimal',
			'card' => 'Card',
			'dark' => 'Dark',
			'inline' => 'Inline',
			'gradient' => 'Gradient',
			'split' => 'Split',
			'bordered' => 'Bordered',
			'soft' => 'Soft',
			'bold' => 'Bold',
		);
	}

	private function defaults() {
		return array(
			'id' => '', 'name' => '', 'audience_id' => '', 'template' => 'classic',
			'title' => 'Join our newsletter', 'description' => 'Get useful updates in your inbox.',
			'button_text' => 'Subscribe', 'success_message' => 'Thank you for subscribing.',
			'show_first' => 1, 'show_last' => 0, 'show_phone' => 0,
			'gdpr' => 1, 'gdpr_text' => 'I agree to receive email updates.',
			'double_opt_in' => 1, 'tags' => '', 'groups' => array(), 'field_map' => array(),
			'redirect_url' => '', 'accent_color' => '#635bff', 'background_color' => '#ffffff',
			'text_color' => '#172033', 'border_radius' => 14, 'custom_css' => '',
		);
	}

	public function menu() {
		add_menu_page( 'Easy Mailchimp', 'Easy Mailchimp', 'manage_options', 'easy-mailchimp-optin', array( $this, 'settings_page' ), 'dashicons-email-alt2', 58 );
		add_submenu_page( 'easy-mailchimp-optin', 'Forms', 'Forms', 'manage_options', 'easy-mailchimp-forms', array( $this, 'forms_page' ) );
		add_submenu_page( 'easy-mailchimp-optin', 'Analytics', 'Analytics', 'manage_options', 'easy-mailchimp-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( 'easy-mailchimp-optin', 'Import / Export', 'Import / Export', 'manage_options', 'easy-mailchimp-tools', array( $this, 'tools_page' ) );
	}

	public function admin_assets( $hook ) {
		if ( false === strpos( $hook, 'easy-mailchimp' ) ) {
			return;
		}
		wp_enqueue_style( 'emo-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_style( 'emo-forms', plugins_url( 'assets/css/forms.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script( 'emo-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_localize_script( 'emo-admin', 'emoAdmin', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'emo_admin' ) ) );
	}

	public function frontend_assets() {
		wp_register_style( 'emo-forms', plugins_url( 'assets/css/forms.css', __FILE__ ), array(), self::VERSION );
		wp_register_script( 'emo-forms', plugins_url( 'assets/js/forms.js', __FILE__ ), array(), self::VERSION, true );
		wp_localize_script( 'emo-forms', 'emoForms', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
	}

	private function data_center( $api_key ) {
		$parts = explode( '-', trim( $api_key ) );
		return count( $parts ) > 1 ? sanitize_key( end( $parts ) ) : '';
	}

	private function api( $method, $path, $body = null ) {
		$settings = $this->settings();
		$key = trim( $settings['api_key'] );
		$dc  = $this->data_center( $key );
		if ( ! $key || ! $dc ) {
			return new WP_Error( 'emo_api', 'Enter a valid Mailchimp API key.' );
		}
		$args = array(
			'method' => strtoupper( $method ),
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'wordpress:' . $key ),
				'Content-Type' => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( 'https://' . $dc . '.api.mailchimp.com/3.0/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'emo_mailchimp', isset( $data['detail'] ) ? $data['detail'] : 'Mailchimp API request failed.', array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	private function audiences() {
		$result = $this->api( 'GET', 'lists?count=100&fields=lists.id,lists.name,lists.stats.member_count' );
		return is_wp_error( $result ) ? $result : ( isset( $result['lists'] ) ? $result['lists'] : array() );
	}

	public function settings_page() {
		$settings = $this->settings();
		?>
		<div class="wrap emo-wrap">
			<div class="emo-page-head"><div><h1>Easy Mailchimp</h1><p>Connect Mailchimp and configure spam protection.</p></div><button class="button" id="emo-test-connection">Test connection</button></div>
			<div class="emo-card emo-settings-card">
				<form method="post" action="options.php">
					<?php settings_fields( 'emo_settings_group' ); ?>
					<div class="emo-settings-grid">
						<label><span>Mailchimp API key</span><input type="password" name="<?php echo esc_attr( self::SETTINGS ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>"></label>
						<label><span>Spam protection</span><select name="<?php echo esc_attr( self::SETTINGS ); ?>[spam_provider]"><?php foreach ( array( 'honeypot' => 'Honeypot', 'turnstile' => 'Cloudflare Turnstile', 'recaptcha' => 'Google reCAPTCHA v2' ) as $key => $label ) { printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $settings['spam_provider'], $key, false ), esc_html( $label ) ); } ?></select></label>
						<label><span>Turnstile site key</span><input name="<?php echo esc_attr( self::SETTINGS ); ?>[turnstile_site_key]" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>"></label>
						<label><span>Turnstile secret</span><input type="password" name="<?php echo esc_attr( self::SETTINGS ); ?>[turnstile_secret_key]" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>"></label>
						<label><span>reCAPTCHA site key</span><input name="<?php echo esc_attr( self::SETTINGS ); ?>[recaptcha_site_key]" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>"></label>
						<label><span>reCAPTCHA secret</span><input type="password" name="<?php echo esc_attr( self::SETTINGS ); ?>[recaptcha_secret_key]" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>"></label>
					</div>
					<?php submit_button( 'Save settings' ); ?>
				</form>
				<span id="emo-test-result"></span>
			</div>
		</div>
		<?php
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'emo_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$result = $this->api( 'GET', 'ping' );
		is_wp_error( $result ) ? wp_send_json_error( $result->get_error_message() ) : wp_send_json_success( 'Connection successful.' );
	}

	public function ajax_audience_schema() {
		check_ajax_referer( 'emo_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$id = sanitize_text_field( isset( $_POST['audience_id'] ) ? wp_unslash( $_POST['audience_id'] ) : '' );
		if ( ! $id ) {
			wp_send_json_success( array( 'groups' => array(), 'merge_fields' => array() ) );
		}
		$groups = array();
		$categories = $this->api( 'GET', 'lists/' . rawurlencode( $id ) . '/interest-categories?count=100' );
		if ( ! is_wp_error( $categories ) ) {
			foreach ( isset( $categories['categories'] ) ? $categories['categories'] : array() as $category ) {
				$interests = $this->api( 'GET', 'lists/' . rawurlencode( $id ) . '/interest-categories/' . rawurlencode( $category['id'] ) . '/interests?count=100' );
				if ( ! is_wp_error( $interests ) ) {
					$groups[] = array(
						'id' => $category['id'],
						'title' => $category['title'],
						'type' => $category['type'],
						'interests' => array_map( function( $item ) { return array( 'id' => $item['id'], 'name' => $item['name'] ); }, isset( $interests['interests'] ) ? $interests['interests'] : array() ),
					);
				}
			}
		}
		$merge = $this->api( 'GET', 'lists/' . rawurlencode( $id ) . '/merge-fields?count=100' );
		$merge_fields = array();
		if ( ! is_wp_error( $merge ) ) {
			$merge_fields = array_map( function( $item ) { return array( 'tag' => $item['tag'], 'name' => $item['name'], 'type' => $item['type'] ); }, isset( $merge['merge_fields'] ) ? $merge['merge_fields'] : array() );
		}
		wp_send_json_success( array( 'groups' => $groups, 'merge_fields' => $merge_fields ) );
	}

	public function forms_page() {
		$forms = $this->forms();
		$id = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$form = wp_parse_args( isset( $forms[ $id ] ) ? $forms[ $id ] : array(), $this->defaults() );
		$audiences = $this->audiences();
		wp_localize_script( 'emo-admin', 'emoFormData', array( 'savedGroups' => (array) $form['groups'], 'savedMap' => (array) $form['field_map'] ) );
		?>
		<div class="wrap emo-wrap">
			<div class="emo-page-head"><div><h1><?php echo $id ? 'Edit form' : 'Create form'; ?></h1><p>Build and preview your Mailchimp form in real time.</p></div><?php if ( $id ) : ?><code>[easy_mailchimp_form id="<?php echo esc_attr( $id ); ?>"]</code><?php endif; ?></div>
			<div class="emo-builder">
				<form class="emo-builder-panel" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="emo_save_form"><input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>"><?php wp_nonce_field( 'emo_save_form' ); ?>
					<div class="emo-section"><h2>Form</h2><div class="emo-two"><label><span>Form name</span><input required name="name" value="<?php echo esc_attr( $form['name'] ); ?>"></label><label><span>Audience</span><select required id="emo-audience" name="audience_id"><option value="">Select audience</option><?php if ( ! is_wp_error( $audiences ) ) { foreach ( $audiences as $audience ) { printf( '<option value="%1$s" %2$s>%3$s (%4$d)</option>', esc_attr( $audience['id'] ), selected( $form['audience_id'], $audience['id'], false ), esc_html( $audience['name'] ), intval( isset( $audience['stats']['member_count'] ) ? $audience['stats']['member_count'] : 0 ) ); } } ?></select></label></div><div class="emo-two"><label><span>Template</span><select class="emo-live" name="template"><?php foreach ( $this->templates() as $key => $label ) { printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $form['template'], $key, false ), esc_html( $label ) ); } ?></select></label><label><span>Button text</span><input class="emo-live" name="button_text" value="<?php echo esc_attr( $form['button_text'] ); ?>"></label></div><label><span>Title</span><input class="emo-live" name="title" value="<?php echo esc_attr( $form['title'] ); ?>"></label><label><span>Description</span><textarea class="emo-live" name="description" rows="2"><?php echo esc_textarea( $form['description'] ); ?></textarea></label></div>
					<div class="emo-section"><h2>Fields & consent</h2><div class="emo-checks"><label><input class="emo-live" type="checkbox" name="show_first" value="1" <?php checked( ! empty( $form['show_first'] ) ); ?>> First name</label><label><input class="emo-live" type="checkbox" name="show_last" value="1" <?php checked( ! empty( $form['show_last'] ) ); ?>> Last name</label><label><input class="emo-live" type="checkbox" name="show_phone" value="1" <?php checked( ! empty( $form['show_phone'] ) ); ?>> Phone</label><label><input class="emo-live" type="checkbox" name="gdpr" value="1" <?php checked( ! empty( $form['gdpr'] ) ); ?>> GDPR consent</label><label><input type="checkbox" name="double_opt_in" value="1" <?php checked( ! empty( $form['double_opt_in'] ) ); ?>> Double opt-in</label></div><label><span>Consent text</span><input class="emo-live" name="gdpr_text" value="<?php echo esc_attr( $form['gdpr_text'] ); ?>"></label></div>
					<div class="emo-section"><h2>Mailchimp options</h2><label><span>Tags <small>Comma separated</small></span><input name="tags" value="<?php echo esc_attr( $form['tags'] ); ?>"></label><div id="emo-groups"><p class="description">Select an audience to load its interest groups.</p></div><div id="emo-merge-fields"><p class="description">Audience merge fields will appear here.</p></div></div>
					<div class="emo-section"><h2>Style</h2><div class="emo-four"><label><span>Accent</span><input class="emo-live" type="color" name="accent_color" value="<?php echo esc_attr( $form['accent_color'] ); ?>"></label><label><span>Background</span><input class="emo-live" type="color" name="background_color" value="<?php echo esc_attr( $form['background_color'] ); ?>"></label><label><span>Text</span><input class="emo-live" type="color" name="text_color" value="<?php echo esc_attr( $form['text_color'] ); ?>"></label><label><span>Radius</span><input class="emo-live" type="number" min="0" max="60" name="border_radius" value="<?php echo intval( $form['border_radius'] ); ?>"></label></div><label><span>Custom CSS <small>Use <code>{{FORM}}</code> as the form selector.</small></span><textarea name="custom_css" rows="5"><?php echo esc_textarea( $form['custom_css'] ); ?></textarea></label></div>
					<div class="emo-section"><h2>After submission</h2><div class="emo-two"><label><span>Success message</span><input name="success_message" value="<?php echo esc_attr( $form['success_message'] ); ?>"></label><label><span>Redirect URL <small>Optional</small></span><input type="url" name="redirect_url" value="<?php echo esc_attr( $form['redirect_url'] ); ?>"></label></div></div>
					<div class="emo-sticky-actions"><?php submit_button( $id ? 'Update form' : 'Create form', 'primary', 'submit', false ); ?></div>
				</form>
				<aside class="emo-builder-side">
					<div class="emo-preview-card"><div class="emo-preview-head"><strong>Live preview</strong><span>Responsive</span></div><div id="emo-preview" class="emo-form emo-template-<?php echo esc_attr( $form['template'] ); ?>" style="--emo-accent:<?php echo esc_attr( $form['accent_color'] ); ?>;--emo-bg:<?php echo esc_attr( $form['background_color'] ); ?>;--emo-text:<?php echo esc_attr( $form['text_color'] ); ?>;--emo-radius:<?php echo intval( $form['border_radius'] ); ?>px"><form><h3><?php echo esc_html( $form['title'] ); ?></h3><p class="emo-description"><?php echo esc_html( $form['description'] ); ?></p><div class="emo-fields"><label class="preview-first"><span>First name</span><input></label><label class="preview-last"><span>Last name</span><input></label><label class="preview-phone"><span>Phone</span><input></label><label><span>Email</span><input type="email"></label></div><label class="emo-gdpr preview-gdpr"><input type="checkbox"><span><?php echo esc_html( $form['gdpr_text'] ); ?></span></label><button type="button"><span><?php echo esc_html( $form['button_text'] ); ?></span></button></form></div></div>
					<div class="emo-card emo-existing"><h2>Existing forms</h2><?php if ( ! $forms ) { echo '<p>No forms yet.</p>'; } foreach ( $forms as $item ) { echo '<div class="emo-form-row"><div><strong>' . esc_html( $item['name'] ) . '</strong><small>' . esc_html( isset( $this->templates()[ $item['template'] ] ) ? $this->templates()[ $item['template'] ] : $item['template'] ) . '</small></div><code>[easy_mailchimp_form id="' . esc_attr( $item['id'] ) . '"]</code><div><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=easy-mailchimp-forms&edit=' . $item['id'] ) ) . '">Edit</a> <a class="button button-small emo-delete" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=emo_delete_form&id=' . $item['id'] ), 'emo_delete_form' ) ) . '">Delete</a></div></div>'; } ?></div>
				</aside>
			</div>
		</div>
		<?php
	}

	public function save_form() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'emo_save_form' );
		$forms = $this->forms();
		$id = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		if ( ! $id ) { $id = 'form-' . wp_generate_password( 8, false, false ); }
		$groups = array();
		foreach ( isset( $_POST['groups'] ) ? (array) $_POST['groups'] : array() as $group_id => $value ) { $groups[ sanitize_text_field( $group_id ) ] = ! empty( $value ); }
		$map = array();
		foreach ( isset( $_POST['field_map'] ) ? (array) $_POST['field_map'] : array() as $tag => $field ) { $tag = sanitize_key( $tag ); $field = sanitize_key( $field ); if ( $tag && $field ) { $map[ $tag ] = $field; } }
		$css = current_user_can( 'unfiltered_html' ) ? wp_unslash( isset( $_POST['custom_css'] ) ? $_POST['custom_css'] : '' ) : '';
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'classic';
		$forms[ $id ] = array(
			'id' => $id,
			'name' => sanitize_text_field( isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '' ),
			'audience_id' => sanitize_text_field( isset( $_POST['audience_id'] ) ? wp_unslash( $_POST['audience_id'] ) : '' ),
			'template' => array_key_exists( $template, $this->templates() ) ? $template : 'classic',
			'title' => sanitize_text_field( isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '' ),
			'description' => sanitize_textarea_field( isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '' ),
			'button_text' => sanitize_text_field( isset( $_POST['button_text'] ) ? wp_unslash( $_POST['button_text'] ) : 'Subscribe' ),
			'success_message' => sanitize_text_field( isset( $_POST['success_message'] ) ? wp_unslash( $_POST['success_message'] ) : 'Thank you.' ),
			'show_first' => empty( $_POST['show_first'] ) ? 0 : 1,
			'show_last' => empty( $_POST['show_last'] ) ? 0 : 1,
			'show_phone' => empty( $_POST['show_phone'] ) ? 0 : 1,
			'gdpr' => empty( $_POST['gdpr'] ) ? 0 : 1,
			'gdpr_text' => sanitize_text_field( isset( $_POST['gdpr_text'] ) ? wp_unslash( $_POST['gdpr_text'] ) : '' ),
			'double_opt_in' => empty( $_POST['double_opt_in'] ) ? 0 : 1,
			'tags' => sanitize_text_field( isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '' ),
			'groups' => $groups,
			'field_map' => $map,
			'redirect_url' => esc_url_raw( isset( $_POST['redirect_url'] ) ? wp_unslash( $_POST['redirect_url'] ) : '' ),
			'accent_color' => sanitize_hex_color( isset( $_POST['accent_color'] ) ? $_POST['accent_color'] : '' ) ?: '#635bff',
			'background_color' => sanitize_hex_color( isset( $_POST['background_color'] ) ? $_POST['background_color'] : '' ) ?: '#ffffff',
			'text_color' => sanitize_hex_color( isset( $_POST['text_color'] ) ? $_POST['text_color'] : '' ) ?: '#172033',
			'border_radius' => min( 60, max( 0, intval( isset( $_POST['border_radius'] ) ? $_POST['border_radius'] : 14 ) ) ),
			'custom_css' => $css,
		);
		update_option( self::FORMS, $forms, false );
		wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-forms&edit=' . $id . '&saved=1' ) );
		exit;
	}

	public function delete_form() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'emo_delete_form' );
		$forms = $this->forms();
		$id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
		unset( $forms[ $id ] );
		update_option( self::FORMS, $forms, false );
		wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-forms' ) );
		exit;
	}

	private function custom_css( $form, $id ) {
		$css = isset( $form['custom_css'] ) ? (string) $form['custom_css'] : '';
		return $css ? str_replace( '{{FORM}}', '.emo-form[data-form="' . esc_attr( $id ) . '"]', $css ) : '';
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts );
		$id = sanitize_key( $atts['id'] );
		$forms = $this->forms();
		if ( empty( $forms[ $id ] ) ) { return current_user_can( 'manage_options' ) ? '<p>Easy Mailchimp form not found.</p>' : ''; }
		$form = wp_parse_args( $forms[ $id ], $this->defaults() );
		wp_enqueue_style( 'emo-forms' ); wp_enqueue_script( 'emo-forms' ); $this->stat( $id, 'views' );
		$settings = $this->settings();
		$style = '--emo-accent:' . $form['accent_color'] . ';--emo-bg:' . $form['background_color'] . ';--emo-text:' . $form['text_color'] . ';--emo-radius:' . intval( $form['border_radius'] ) . 'px';
		ob_start();
		if ( $form['custom_css'] ) { echo '<style>' . wp_strip_all_tags( $this->custom_css( $form, $id ) ) . '</style>'; }
		?>
		<div class="emo-form emo-template-<?php echo esc_attr( $form['template'] ); ?>" data-form="<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $style ); ?>"><form class="emo-ajax-form"><input type="hidden" name="action" value="emo_subscribe"><input type="hidden" name="form_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'emo_subscribe_' . $id ) ); ?>"><input class="emo-hp" type="text" name="website" tabindex="-1" autocomplete="off"><h3><?php echo esc_html( $form['title'] ); ?></h3><p class="emo-description"><?php echo esc_html( $form['description'] ); ?></p><div class="emo-fields"><?php if ( $form['show_first'] ) : ?><label><span>First name</span><input name="first_name" autocomplete="given-name"></label><?php endif; ?><?php if ( $form['show_last'] ) : ?><label><span>Last name</span><input name="last_name" autocomplete="family-name"></label><?php endif; ?><?php if ( $form['show_phone'] ) : ?><label><span>Phone</span><input name="phone" type="tel" autocomplete="tel"></label><?php endif; ?><label class="emo-email"><span>Email</span><input required name="email" type="email" autocomplete="email"></label></div><?php if ( $form['gdpr'] ) : ?><label class="emo-gdpr"><input required type="checkbox" name="gdpr" value="1"><span><?php echo esc_html( $form['gdpr_text'] ); ?></span></label><?php endif; ?><?php if ( 'turnstile' === $settings['spam_provider'] && $settings['turnstile_site_key'] ) : ?><div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php elseif ( 'recaptcha' === $settings['spam_provider'] && $settings['recaptcha_site_key'] ) : ?><div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>"></div><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?><button class="emo-submit" type="submit"><span><?php echo esc_html( $form['button_text'] ); ?></span></button><div class="emo-message" role="status" aria-live="polite"></div></form></div>
		<?php
		return ob_get_clean();
	}

	public function legacy_shortcode() {
		$forms = $this->forms(); $first = reset( $forms ); return $first ? $this->shortcode( array( 'id' => $first['id'] ) ) : '';
	}

	private function verify_spam() {
		$settings = $this->settings();
		if ( ! empty( $_POST['website'] ) ) { return false; }
		if ( 'honeypot' === $settings['spam_provider'] ) { return true; }
		$is_turnstile = 'turnstile' === $settings['spam_provider'];
		$token = sanitize_text_field( isset( $_POST[ $is_turnstile ? 'cf-turnstile-response' : 'g-recaptcha-response' ] ) ? $_POST[ $is_turnstile ? 'cf-turnstile-response' : 'g-recaptcha-response' ] : '' );
		$secret = $is_turnstile ? $settings['turnstile_secret_key'] : $settings['recaptcha_secret_key'];
		if ( ! $token || ! $secret ) { return false; }
		$url = $is_turnstile ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://www.google.com/recaptcha/api/siteverify';
		$response = wp_remote_post( $url, array( 'timeout' => 15, 'body' => array( 'secret' => $secret, 'response' => $token, 'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) ) );
		if ( is_wp_error( $response ) ) { return false; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $data['success'] );
	}

	public function ajax_subscribe() {
		$id = sanitize_key( isset( $_POST['form_id'] ) ? $_POST['form_id'] : '' );
		$nonce = sanitize_text_field( isset( $_POST['nonce'] ) ? $_POST['nonce'] : '' );
		if ( ! wp_verify_nonce( $nonce, 'emo_subscribe_' . $id ) ) { wp_send_json_error( array( 'message' => 'Security check failed.' ) ); }
		$forms = $this->forms();
		if ( empty( $forms[ $id ] ) ) { wp_send_json_error( array( 'message' => 'Form not found.' ) ); }
		$form = wp_parse_args( $forms[ $id ], $this->defaults() );
		if ( ! $this->verify_spam() ) { wp_send_json_error( array( 'message' => 'Spam verification failed.' ) ); }
		$email = sanitize_email( isset( $_POST['email'] ) ? $_POST['email'] : '' );
		if ( ! is_email( $email ) ) { wp_send_json_error( array( 'message' => 'Enter a valid email address.' ) ); }
		if ( $form['gdpr'] && empty( $_POST['gdpr'] ) ) { wp_send_json_error( array( 'message' => 'Please accept the consent checkbox.' ) ); }
		$merge = array( 'FNAME' => sanitize_text_field( isset( $_POST['first_name'] ) ? $_POST['first_name'] : '' ), 'LNAME' => sanitize_text_field( isset( $_POST['last_name'] ) ? $_POST['last_name'] : '' ) );
		if ( $form['show_phone'] ) { $merge['PHONE'] = sanitize_text_field( isset( $_POST['phone'] ) ? $_POST['phone'] : '' ); }
		$body = array( 'email_address' => $email, 'status_if_new' => $form['double_opt_in'] ? 'pending' : 'subscribed', 'status' => $form['double_opt_in'] ? 'pending' : 'subscribed', 'merge_fields' => array_filter( $merge ) );
		if ( $form['groups'] ) { $body['interests'] = (object) $form['groups']; }
		$result = $this->api( 'PUT', 'lists/' . rawurlencode( $form['audience_id'] ) . '/members/' . md5( strtolower( $email ) ), $body );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
		$tags = array_filter( array_map( 'trim', explode( ',', $form['tags'] ) ) );
		if ( $tags ) { $this->api( 'POST', 'lists/' . rawurlencode( $form['audience_id'] ) . '/members/' . md5( strtolower( $email ) ) . '/tags', array( 'tags' => array_map( function( $tag ) { return array( 'name' => $tag, 'status' => 'active' ); }, $tags ) ) ); }
		$this->stat( $id, 'submissions' );
		wp_send_json_success( array( 'message' => $form['success_message'], 'redirect' => $form['redirect_url'] ) );
	}

	private function stat( $id, $key ) {
		$stats = get_option( self::STATS, array() );
		if ( ! isset( $stats[ $id ] ) ) { $stats[ $id ] = array( 'views' => 0, 'submissions' => 0 ); }
		$stats[ $id ][ $key ] = intval( isset( $stats[ $id ][ $key ] ) ? $stats[ $id ][ $key ] : 0 ) + 1;
		update_option( self::STATS, $stats, false );
	}

	public function analytics_page() {
		$forms = $this->forms(); $stats = get_option( self::STATS, array() );
		echo '<div class="wrap emo-wrap"><h1>Form Analytics</h1><div class="emo-card"><table class="widefat striped"><thead><tr><th>Form</th><th>Views</th><th>Submissions</th><th>Conversion</th></tr></thead><tbody>';
		foreach ( $forms as $id => $form ) { $views = intval( isset( $stats[ $id ]['views'] ) ? $stats[ $id ]['views'] : 0 ); $subs = intval( isset( $stats[ $id ]['submissions'] ) ? $stats[ $id ]['submissions'] : 0 ); echo '<tr><td>' . esc_html( $form['name'] ) . '</td><td>' . $views . '</td><td>' . $subs . '</td><td>' . ( $views ? esc_html( number_format_i18n( ( $subs / $views ) * 100, 2 ) ) . '%' : '0%' ) . '</td></tr>'; }
		echo '</tbody></table></div></div>';
	}

	public function tools_page() {
		?><div class="wrap emo-wrap"><h1>Import / Export</h1><div class="emo-grid"><div class="emo-card"><h2>Export</h2><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=emo_export_forms' ), 'emo_export_forms' ) ); ?>">Download JSON</a></div><div class="emo-card"><h2>Import</h2><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="emo_import_forms"><?php wp_nonce_field( 'emo_import_forms' ); ?><input required type="file" name="import_file" accept="application/json"><?php submit_button( 'Import forms' ); ?></form></div></div></div><?php
	}

	public function export_forms() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'emo_export_forms' ); nocache_headers(); header( 'Content-Type: application/json' ); header( 'Content-Disposition: attachment; filename=easy-mailchimp-forms-' . gmdate( 'Y-m-d' ) . '.json' ); echo wp_json_encode( array( 'version' => self::VERSION, 'forms' => $this->forms() ), JSON_PRETTY_PRINT ); exit;
	}

	public function import_forms() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'emo_import_forms' );
		$tmp = isset( $_FILES['import_file']['tmp_name'] ) ? $_FILES['import_file']['tmp_name'] : '';
		$data = $tmp ? json_decode( file_get_contents( $tmp ), true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['forms'] ) || ! is_array( $data['forms'] ) ) { wp_die( 'Invalid import file.' ); }
		update_option( self::FORMS, $data['forms'], false ); wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-tools&imported=1' ) ); exit;
	}

	public function register_block() {
		wp_register_script( 'emo-block', plugins_url( 'assets/js/block.js', __FILE__ ), array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ), self::VERSION, true );
		$forms = array();
		foreach ( $this->forms() as $form ) { $forms[] = array( 'id' => $form['id'], 'name' => $form['name'] ); }
		wp_localize_script( 'emo-block', 'emoBlockData', array( 'forms' => $forms ) );
		register_block_type( 'easy-mailchimp/form', array( 'editor_script' => 'emo-block', 'render_callback' => array( $this, 'render_block' ), 'attributes' => array( 'formId' => array( 'type' => 'string', 'default' => '' ) ) ) );
	}

	public function render_block( $attributes ) {
		return $this->shortcode( array( 'id' => isset( $attributes['formId'] ) ? $attributes['formId'] : '' ) );
	}

	public function register_elementor_widget( $manager ) {
		if ( class_exists( 'Elementor\\Widget_Base' ) && class_exists( 'EMO_Elementor_Widget' ) ) {
			$manager->register( new EMO_Elementor_Widget() );
		}
	}
}

if ( class_exists( 'Elementor\\Widget_Base' ) && ! class_exists( 'EMO_Elementor_Widget' ) ) {
	class EMO_Elementor_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'easy_mailchimp_form'; }
		public function get_title() { return 'Easy Mailchimp Form'; }
		public function get_icon() { return 'eicon-mail'; }
		public function get_categories() { return array( 'general' ); }
		protected function register_controls() {
			$forms = get_option( EMO_Premium::FORMS, array() );
			$options = array();
			foreach ( is_array( $forms ) ? $forms : array() as $form ) { $options[ $form['id'] ] = $form['name']; }
			$this->start_controls_section( 'content', array( 'label' => 'Form' ) );
			$this->add_control( 'form_id', array( 'label' => 'Form', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $options ) );
			$this->end_controls_section();
		}
		protected function render() {
			$settings = $this->get_settings_for_display();
			echo EMO_Premium::instance()->shortcode( array( 'id' => isset( $settings['form_id'] ) ? $settings['form_id'] : '' ) );
		}
	}
}

EMO_Premium::instance();
