<?php
/**
 * Plugin Name: Easy Mailchimp Opt-in
 * Plugin URI: https://wordpress.org/plugins/easy-mailchimp-opt-in/
 * Description: Connect Mailchimp, create responsive opt-in forms, choose from five templates, and embed forms with shortcodes.
 * Version: 2.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Mahfuzar
 * Author URI: https://mahfuzar.info
 * License: GPLv2 or later
 * Text Domain: easy-mailchimp-optin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Easy_Mailchimp_Optin {
	const VERSION = '2.0.0';
	const SETTINGS_OPTION = 'emo_settings';
	const FORMS_OPTION = 'emo_forms';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_emo_save_form', array( $this, 'save_form' ) );
		add_action( 'admin_post_emo_delete_form', array( $this, 'delete_form' ) );
		add_action( 'admin_post_emo_test_connection', array( $this, 'test_connection' ) );
		add_action( 'admin_post_emo_subscribe', array( $this, 'subscribe' ) );
		add_action( 'admin_post_nopriv_emo_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'easy_mailchimp_form', array( $this, 'shortcode' ) );
		add_shortcode( 'mailchimp', array( $this, 'legacy_shortcode' ) );
	}

	public function register_assets() {
		wp_register_style(
			'easy-mailchimp-optin',
			plugins_url( 'assets/css/forms.css', __FILE__ ),
			array(),
			self::VERSION
		);
	}

	public function register_settings() {
		register_setting(
			'emo_settings_group',
			self::SETTINGS_OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $input ) {
		return array(
			'api_key' => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
		);
	}

	public function admin_menu() {
		add_menu_page(
			__( 'Easy Mailchimp', 'easy-mailchimp-optin' ),
			__( 'Easy Mailchimp', 'easy-mailchimp-optin' ),
			'manage_options',
			'easy-mailchimp-optin',
			array( $this, 'settings_page' ),
			'dashicons-email-alt2'
		);
		add_submenu_page(
			'easy-mailchimp-optin',
			__( 'Forms', 'easy-mailchimp-optin' ),
			__( 'Forms', 'easy-mailchimp-optin' ),
			'manage_options',
			'easy-mailchimp-forms',
			array( $this, 'forms_page' )
		);
	}

	private function settings() {
		return wp_parse_args( get_option( self::SETTINGS_OPTION, array() ), array( 'api_key' => '' ) );
	}

	private function forms() {
		$forms = get_option( self::FORMS_OPTION, array() );
		return is_array( $forms ) ? $forms : array();
	}

	private function data_center( $api_key ) {
		$parts = explode( '-', trim( $api_key ) );
		return count( $parts ) > 1 ? sanitize_key( end( $parts ) ) : '';
	}

	private function api_request( $method, $path, $body = null ) {
		$settings = $this->settings();
		$api_key = trim( $settings['api_key'] );
		$dc = $this->data_center( $api_key );

		if ( ! $api_key || ! $dc ) {
			return new WP_Error( 'emo_missing_api_key', __( 'Enter a valid Mailchimp API key first.', 'easy-mailchimp-optin' ) );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'wordpress:' . $api_key ),
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( 'https://' . $dc . '.api.mailchimp.com/3.0/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = ! empty( $data['detail'] ) ? $data['detail'] : __( 'Mailchimp API request failed.', 'easy-mailchimp-optin' );
			return new WP_Error( 'emo_mailchimp_error', $message, array( 'status' => $status ) );
		}

		return is_array( $data ) ? $data : array();
	}

	private function audiences() {
		$result = $this->api_request( 'GET', 'lists?count=100&fields=lists.id,lists.name' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['lists'] ) && is_array( $result['lists'] ) ? $result['lists'] : array();
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Easy Mailchimp Connection', 'easy-mailchimp-optin' ); ?></h1>
			<?php $this->admin_notice(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'emo_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="emo-api-key"><?php esc_html_e( 'Mailchimp API key', 'easy-mailchimp-optin' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="emo-api-key" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'The data center is detected from the API key suffix, for example -us21.', 'easy-mailchimp-optin' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( $settings['api_key'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="emo_test_connection">
					<?php wp_nonce_field( 'emo_test_connection' ); ?>
					<?php submit_button( __( 'Test connection', 'easy-mailchimp-optin' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function forms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$forms = $this->forms();
		$edit_id = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$form = isset( $forms[ $edit_id ] ) ? $forms[ $edit_id ] : array(
			'id' => '', 'name' => '', 'audience_id' => '', 'template' => 'classic', 'title' => 'Join our newsletter',
			'description' => 'Get updates delivered to your inbox.', 'button_text' => 'Subscribe', 'show_name' => 1,
			'double_opt_in' => 1, 'success_message' => 'Thank you. Please check your inbox.',
		);
		$audiences = $this->audiences();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mailchimp Forms', 'easy-mailchimp-optin' ); ?></h1>
			<?php $this->admin_notice(); ?>
			<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1.3fr);gap:24px;align-items:start;">
				<div class="card" style="max-width:none;">
					<h2><?php echo $edit_id ? esc_html__( 'Edit form', 'easy-mailchimp-optin' ) : esc_html__( 'Create form', 'easy-mailchimp-optin' ); ?></h2>
					<?php if ( is_wp_error( $audiences ) ) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html( $audiences->get_error_message() ); ?></p></div>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="emo_save_form">
						<input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>">
						<?php wp_nonce_field( 'emo_save_form' ); ?>
						<table class="form-table" role="presentation">
							<tr><th><label for="emo-name"><?php esc_html_e( 'Form name', 'easy-mailchimp-optin' ); ?></label></th><td><input required class="regular-text" id="emo-name" name="name" value="<?php echo esc_attr( $form['name'] ); ?>"></td></tr>
							<tr><th><label for="emo-audience"><?php esc_html_e( 'Audience', 'easy-mailchimp-optin' ); ?></label></th><td><select required id="emo-audience" name="audience_id"><option value=""><?php esc_html_e( 'Select audience', 'easy-mailchimp-optin' ); ?></option><?php if ( ! is_wp_error( $audiences ) ) { foreach ( $audiences as $audience ) { printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $audience['id'] ), selected( $form['audience_id'], $audience['id'], false ), esc_html( $audience['name'] ) ); } } ?></select></td></tr>
							<tr><th><label for="emo-template"><?php esc_html_e( 'Template', 'easy-mailchimp-optin' ); ?></label></th><td><select id="emo-template" name="template"><?php foreach ( $this->templates() as $key => $label ) { printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $form['template'], $key, false ), esc_html( $label ) ); } ?></select></td></tr>
							<tr><th><label for="emo-title"><?php esc_html_e( 'Title', 'easy-mailchimp-optin' ); ?></label></th><td><input class="regular-text" id="emo-title" name="title" value="<?php echo esc_attr( $form['title'] ); ?>"></td></tr>
							<tr><th><label for="emo-description"><?php esc_html_e( 'Description', 'easy-mailchimp-optin' ); ?></label></th><td><textarea class="large-text" id="emo-description" name="description" rows="3"><?php echo esc_textarea( $form['description'] ); ?></textarea></td></tr>
							<tr><th><label for="emo-button"><?php esc_html_e( 'Button text', 'easy-mailchimp-optin' ); ?></label></th><td><input id="emo-button" name="button_text" value="<?php echo esc_attr( $form['button_text'] ); ?>"></td></tr>
							<tr><th><?php esc_html_e( 'Options', 'easy-mailchimp-optin' ); ?></th><td><label><input type="checkbox" name="show_name" value="1" <?php checked( ! empty( $form['show_name'] ) ); ?>> <?php esc_html_e( 'Show first and last name', 'easy-mailchimp-optin' ); ?></label><br><label><input type="checkbox" name="double_opt_in" value="1" <?php checked( ! empty( $form['double_opt_in'] ) ); ?>> <?php esc_html_e( 'Require email confirmation', 'easy-mailchimp-optin' ); ?></label></td></tr>
							<tr><th><label for="emo-success"><?php esc_html_e( 'Success message', 'easy-mailchimp-optin' ); ?></label></th><td><input class="regular-text" id="emo-success" name="success_message" value="<?php echo esc_attr( $form['success_message'] ); ?>"></td></tr>
						</table>
						<?php submit_button( $edit_id ? __( 'Update form', 'easy-mailchimp-optin' ) : __( 'Create form', 'easy-mailchimp-optin' ) ); ?>
					</form>
				</div>
				<div>
					<h2><?php esc_html_e( 'Existing forms', 'easy-mailchimp-optin' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'easy-mailchimp-optin' ); ?></th><th><?php esc_html_e( 'Template', 'easy-mailchimp-optin' ); ?></th><th><?php esc_html_e( 'Shortcode', 'easy-mailchimp-optin' ); ?></th><th><?php esc_html_e( 'Actions', 'easy-mailchimp-optin' ); ?></th></tr></thead><tbody>
					<?php if ( ! $forms ) : ?><tr><td colspan="4"><?php esc_html_e( 'No forms created yet.', 'easy-mailchimp-optin' ); ?></td></tr><?php else : foreach ( $forms as $item ) : ?>
					<tr><td><?php echo esc_html( $item['name'] ); ?></td><td><?php echo esc_html( $this->templates()[ $item['template'] ] ?? $item['template'] ); ?></td><td><code>[easy_mailchimp_form id="<?php echo esc_attr( $item['id'] ); ?>"]</code></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-mailchimp-forms&edit=' . $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'easy-mailchimp-optin' ); ?></a> | <a onclick="return confirm('Delete this form?');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=emo_delete_form&form_id=' . $item['id'] ), 'emo_delete_form_' . $item['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'easy-mailchimp-optin' ); ?></a></td></tr>
					<?php endforeach; endif; ?>
					</tbody></table>
				</div>
			</div>
		</div>
		<?php
	}

	private function templates() {
		return array(
			'classic' => __( 'Classic', 'easy-mailchimp-optin' ),
			'minimal' => __( 'Minimal', 'easy-mailchimp-optin' ),
			'card'    => __( 'Card', 'easy-mailchimp-optin' ),
			'dark'    => __( 'Dark', 'easy-mailchimp-optin' ),
			'inline'  => __( 'Inline', 'easy-mailchimp-optin' ),
		);
	}

	public function save_form() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'emo_save_form' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'easy-mailchimp-optin' ) );
		}
		$forms = $this->forms();
		$id = ! empty( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : 'form-' . wp_generate_password( 8, false, false );
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'classic';
		if ( ! isset( $this->templates()[ $template ] ) ) {
			$template = 'classic';
		}
		$forms[ $id ] = array(
			'id' => $id,
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'audience_id' => sanitize_text_field( wp_unslash( $_POST['audience_id'] ?? '' ) ),
			'template' => $template,
			'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'button_text' => sanitize_text_field( wp_unslash( $_POST['button_text'] ?? 'Subscribe' ) ),
			'show_name' => empty( $_POST['show_name'] ) ? 0 : 1,
			'double_opt_in' => empty( $_POST['double_opt_in'] ) ? 0 : 1,
			'success_message' => sanitize_text_field( wp_unslash( $_POST['success_message'] ?? '' ) ),
		);
		update_option( self::FORMS_OPTION, $forms, false );
		wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-forms&emo_notice=saved' ) );
		exit;
	}

	public function delete_form() {
		$id = isset( $_GET['form_id'] ) ? sanitize_key( wp_unslash( $_GET['form_id'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'emo_delete_form_' . $id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'easy-mailchimp-optin' ) );
		}
		$forms = $this->forms();
		unset( $forms[ $id ] );
		update_option( self::FORMS_OPTION, $forms, false );
		wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-forms&emo_notice=deleted' ) );
		exit;
	}

	public function test_connection() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'emo_test_connection' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'easy-mailchimp-optin' ) );
		}
		$result = $this->api_request( 'GET', 'ping' );
		$notice = is_wp_error( $result ) ? 'connection_error&emo_message=' . rawurlencode( $result->get_error_message() ) : 'connected';
		wp_safe_redirect( admin_url( 'admin.php?page=easy-mailchimp-optin&emo_notice=' . $notice ) );
		exit;
	}

	public function subscribe() {
		$form_id = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		$forms = $this->forms();
		$form = $forms[ $form_id ] ?? null;
		$redirect = wp_validate_redirect( wp_unslash( $_POST['redirect'] ?? '' ), home_url( '/' ) );

		if ( ! $form || ! wp_verify_nonce( $_POST['emo_nonce'] ?? '', 'emo_subscribe_' . $form_id ) ) {
			$this->redirect_with_message( $redirect, $form_id, 'error', __( 'The form could not be verified.', 'easy-mailchimp-optin' ) );
		}
		if ( ! empty( $_POST['company'] ) ) {
			$this->redirect_with_message( $redirect, $form_id, 'success', $form['success_message'] );
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			$this->redirect_with_message( $redirect, $form_id, 'error', __( 'Enter a valid email address.', 'easy-mailchimp-optin' ) );
		}

		$body = array(
			'email_address' => $email,
			'status_if_new' => ! empty( $form['double_opt_in'] ) ? 'pending' : 'subscribed',
			'merge_fields' => array(
				'FNAME' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				'LNAME' => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			),
		);
		$hash = md5( strtolower( trim( $email ) ) );
		$result = $this->api_request( 'PUT', 'lists/' . rawurlencode( $form['audience_id'] ) . '/members/' . $hash, $body );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( $redirect, $form_id, 'error', $result->get_error_message() );
		}
		$this->redirect_with_message( $redirect, $form_id, 'success', $form['success_message'] );
	}

	private function redirect_with_message( $redirect, $form_id, $type, $message ) {
		$url = add_query_arg( array( 'emo_form' => $form_id, 'emo_status' => $type, 'emo_message' => rawurlencode( $message ) ), $redirect );
		wp_safe_redirect( $url );
		exit;
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'easy_mailchimp_form' );
		$forms = $this->forms();
		$id = sanitize_key( $atts['id'] );
		if ( ! $id || empty( $forms[ $id ] ) ) {
			return current_user_can( 'manage_options' ) ? '<p>' . esc_html__( 'Easy Mailchimp form not found.', 'easy-mailchimp-optin' ) . '</p>' : '';
		}
		$form = $forms[ $id ];
		wp_enqueue_style( 'easy-mailchimp-optin' );
		$message = '';
		if ( isset( $_GET['emo_form'], $_GET['emo_status'], $_GET['emo_message'] ) && sanitize_key( wp_unslash( $_GET['emo_form'] ) ) === $id ) {
			$status = sanitize_key( wp_unslash( $_GET['emo_status'] ) );
			$message = '<div class="emo-message emo-message-' . esc_attr( $status ) . '">' . esc_html( rawurldecode( wp_unslash( $_GET['emo_message'] ) ) ) . '</div>';
		}
		ob_start();
		?>
		<div class="emo-form-wrap emo-template-<?php echo esc_attr( $form['template'] ); ?>">
			<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $form['title'] ) : ?><h3 class="emo-title"><?php echo esc_html( $form['title'] ); ?></h3><?php endif; ?>
			<?php if ( $form['description'] ) : ?><p class="emo-description"><?php echo esc_html( $form['description'] ); ?></p><?php endif; ?>
			<form class="emo-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="emo_subscribe">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $id ); ?>">
				<input type="hidden" name="redirect" value="<?php echo esc_url( $this->current_url() ); ?>">
				<input type="hidden" name="emo_nonce" value="<?php echo esc_attr( wp_create_nonce( 'emo_subscribe_' . $id ) ); ?>">
				<input class="emo-honeypot" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
				<?php if ( ! empty( $form['show_name'] ) ) : ?>
					<div class="emo-row emo-name-row"><label><span><?php esc_html_e( 'First name', 'easy-mailchimp-optin' ); ?></span><input type="text" name="first_name" autocomplete="given-name"></label><label><span><?php esc_html_e( 'Last name', 'easy-mailchimp-optin' ); ?></span><input type="text" name="last_name" autocomplete="family-name"></label></div>
				<?php endif; ?>
				<div class="emo-row emo-email-row"><label><span><?php esc_html_e( 'Email address', 'easy-mailchimp-optin' ); ?></span><input required type="email" name="email" autocomplete="email" placeholder="name@example.com"></label><button type="submit"><?php echo esc_html( $form['button_text'] ); ?></button></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function legacy_shortcode( $atts ) {
		$forms = $this->forms();
		$first = reset( $forms );
		if ( ! $first ) {
			return '';
		}
		return $this->shortcode( array( 'id' => $first['id'] ) );
	}

	private function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return $scheme . $host . remove_query_arg( array( 'emo_form', 'emo_status', 'emo_message' ), $uri );
	}

	private function admin_notice() {
		$notice = isset( $_GET['emo_notice'] ) ? sanitize_key( wp_unslash( $_GET['emo_notice'] ) ) : '';
		$message = isset( $_GET['emo_message'] ) ? sanitize_text_field( wp_unslash( $_GET['emo_message'] ) ) : '';
		$map = array(
			'saved' => array( 'success', __( 'Form saved.', 'easy-mailchimp-optin' ) ),
			'deleted' => array( 'success', __( 'Form deleted.', 'easy-mailchimp-optin' ) ),
			'connected' => array( 'success', __( 'Mailchimp connection successful.', 'easy-mailchimp-optin' ) ),
			'connection_error' => array( 'error', $message ?: __( 'Mailchimp connection failed.', 'easy-mailchimp-optin' ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $notice ][0] ), esc_html( $map[ $notice ][1] ) );
		}
	}
}

new Easy_Mailchimp_Optin();
