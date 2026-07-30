<?php
/**
 * Plugin Name: Easy Mailchimp Opt-in
 * Plugin URI: https://wordpress.org/plugins/easy-mailchimp-opt-in/
 * Description: Premium-grade Mailchimp form builder with responsive templates, AJAX subscriptions, GDPR, spam protection, analytics, Gutenberg, Elementor, and import/export.
 * Version: 3.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Mahfuzar
 * Author URI: https://mahfuzar.info
 * License: GPLv2 or later
 * Text Domain: easy-mailchimp-optin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class EMO_Premium {
	const VERSION = '3.0.0';
	const SETTINGS = 'emo_settings';
	const FORMS = 'emo_forms';
	const STATS = 'emo_stats';
	private static $instance;

	public static function instance() {
		return self::$instance ?: ( self::$instance = new self() );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_ajax_emo_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_emo_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_emo_test_connection', array( $this, 'ajax_test_connection' ) );
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
		register_setting( 'emo_settings_group', self::SETTINGS, array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );
	}

	public function sanitize_settings( $in ) {
		return array(
			'api_key' => sanitize_text_field( $in['api_key'] ?? '' ),
			'turnstile_site_key' => sanitize_text_field( $in['turnstile_site_key'] ?? '' ),
			'turnstile_secret_key' => sanitize_text_field( $in['turnstile_secret_key'] ?? '' ),
			'recaptcha_site_key' => sanitize_text_field( $in['recaptcha_site_key'] ?? '' ),
			'recaptcha_secret_key' => sanitize_text_field( $in['recaptcha_secret_key'] ?? '' ),
			'spam_provider' => in_array( $in['spam_provider'] ?? 'honeypot', array( 'honeypot','turnstile','recaptcha' ), true ) ? $in['spam_provider'] : 'honeypot',
		);
	}

	private function settings() {
		return wp_parse_args( get_option( self::SETTINGS, array() ), array(
			'api_key'=>'','spam_provider'=>'honeypot','turnstile_site_key'=>'','turnstile_secret_key'=>'','recaptcha_site_key'=>'','recaptcha_secret_key'=>''
		) );
	}

	private function forms() {
		$f = get_option( self::FORMS, array() );
		return is_array( $f ) ? $f : array();
	}

	private function templates() {
		return array( 'classic'=>'Classic','minimal'=>'Minimal','card'=>'Card','dark'=>'Dark','inline'=>'Inline','gradient'=>'Gradient','split'=>'Split','bordered'=>'Bordered','soft'=>'Soft','bold'=>'Bold' );
	}

	public function menu() {
		add_menu_page( 'Easy Mailchimp', 'Easy Mailchimp', 'manage_options', 'easy-mailchimp-optin', array( $this, 'settings_page' ), 'dashicons-email-alt2', 58 );
		add_submenu_page( 'easy-mailchimp-optin', 'Forms', 'Forms', 'manage_options', 'easy-mailchimp-forms', array( $this, 'forms_page' ) );
		add_submenu_page( 'easy-mailchimp-optin', 'Analytics', 'Analytics', 'manage_options', 'easy-mailchimp-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( 'easy-mailchimp-optin', 'Import / Export', 'Import / Export', 'manage_options', 'easy-mailchimp-tools', array( $this, 'tools_page' ) );
	}

	public function admin_assets( $hook ) {
		if ( strpos( $hook, 'easy-mailchimp' ) === false ) return;
		wp_enqueue_style( 'emo-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script( 'emo-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_localize_script( 'emo-admin', 'emoAdmin', array( 'ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('emo_admin') ) );
	}

	public function frontend_assets() {
		wp_register_style( 'emo-forms', plugins_url( 'assets/css/forms.css', __FILE__ ), array(), self::VERSION );
		wp_register_script( 'emo-forms', plugins_url( 'assets/js/forms.js', __FILE__ ), array(), self::VERSION, true );
		wp_localize_script( 'emo-forms', 'emoForms', array( 'ajax'=>admin_url('admin-ajax.php') ) );
	}

	private function dc( $key ) {
		$p = explode( '-', trim( $key ) );
		return count( $p ) > 1 ? sanitize_key( end( $p ) ) : '';
	}

	private function api( $method, $path, $body = null ) {
		$s = $this->settings(); $key = trim( $s['api_key'] ); $dc = $this->dc( $key );
		if ( ! $key || ! $dc ) return new WP_Error( 'emo_api', 'Enter a valid Mailchimp API key.' );
		$args = array( 'method'=>strtoupper($method),'timeout'=>25,'headers'=>array('Authorization'=>'Basic '.base64_encode('wordpress:'.$key),'Content-Type'=>'application/json') );
		if ( null !== $body ) $args['body'] = wp_json_encode( $body );
		$r = wp_remote_request( 'https://'.$dc.'.api.mailchimp.com/3.0/'.ltrim($path,'/'), $args );
		if ( is_wp_error( $r ) ) return $r;
		$code = wp_remote_retrieve_response_code( $r ); $data = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $code < 200 || $code >= 300 ) return new WP_Error( 'emo_mailchimp', $data['detail'] ?? 'Mailchimp API request failed.', array('status'=>$code) );
		return is_array( $data ) ? $data : array();
	}

	private function audiences() {
		$r = $this->api( 'GET', 'lists?count=100&fields=lists.id,lists.name,lists.stats.member_count' );
		return is_wp_error($r) ? $r : ( $r['lists'] ?? array() );
	}

	public function settings_page() {
		$s = $this->settings(); ?>
		<div class="wrap emo-wrap"><h1>Easy Mailchimp Settings</h1><div class="emo-card"><form method="post" action="options.php">
		<?php settings_fields('emo_settings_group'); ?>
		<table class="form-table"><tr><th>Mailchimp API key</th><td><input type="password" class="regular-text" name="<?php echo esc_attr(self::SETTINGS); ?>[api_key]" value="<?php echo esc_attr($s['api_key']); ?>" autocomplete="new-password"><p class="description">Mailchimp data center is detected from the key suffix.</p></td></tr>
		<tr><th>Spam protection</th><td><select name="<?php echo esc_attr(self::SETTINGS); ?>[spam_provider]"><?php foreach(array('honeypot'=>'Honeypot','turnstile'=>'Cloudflare Turnstile','recaptcha'=>'Google reCAPTCHA v2') as $k=>$v) printf('<option value="%s" %s>%s</option>',esc_attr($k),selected($s['spam_provider'],$k,false),esc_html($v)); ?></select></td></tr>
		<tr><th>Turnstile keys</th><td><input class="regular-text" placeholder="Site key" name="<?php echo esc_attr(self::SETTINGS); ?>[turnstile_site_key]" value="<?php echo esc_attr($s['turnstile_site_key']); ?>"><br><input class="regular-text" placeholder="Secret key" type="password" name="<?php echo esc_attr(self::SETTINGS); ?>[turnstile_secret_key]" value="<?php echo esc_attr($s['turnstile_secret_key']); ?>"></td></tr>
		<tr><th>reCAPTCHA keys</th><td><input class="regular-text" placeholder="Site key" name="<?php echo esc_attr(self::SETTINGS); ?>[recaptcha_site_key]" value="<?php echo esc_attr($s['recaptcha_site_key']); ?>"><br><input class="regular-text" placeholder="Secret key" type="password" name="<?php echo esc_attr(self::SETTINGS); ?>[recaptcha_secret_key]" value="<?php echo esc_attr($s['recaptcha_secret_key']); ?>"></td></tr></table>
		<?php submit_button(); ?></form><button class="button" id="emo-test-connection">Test connection</button><span id="emo-test-result"></span></div></div><?php
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'emo_admin', 'nonce' ); if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
		$r = $this->api('GET','ping'); is_wp_error($r) ? wp_send_json_error($r->get_error_message()) : wp_send_json_success('Connection successful.');
	}

	public function forms_page() {
		$forms=$this->forms(); $id=sanitize_key($_GET['edit']??'');
		$f=$forms[$id]??array('id'=>'','name'=>'','audience_id'=>'','template'=>'classic','title'=>'Join our newsletter','description'=>'Get useful updates in your inbox.','button_text'=>'Subscribe','success_message'=>'Thank you for subscribing.','show_first'=>1,'show_last'=>0,'show_phone'=>0,'gdpr'=>1,'gdpr_text'=>'I agree to receive email updates.','double_opt_in'=>1,'tags'=>'','groups'=>'','field_map'=>'','redirect_url'=>'');
		$aud=$this->audiences(); ?>
		<div class="wrap emo-wrap"><h1>Mailchimp Forms</h1><div class="emo-grid"><div class="emo-card"><h2><?php echo $id?'Edit form':'Create form'; ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="emo_save_form"><input type="hidden" name="form_id" value="<?php echo esc_attr($f['id']); ?>"><?php wp_nonce_field('emo_save_form'); ?>
		<p><label>Form name</label><input required class="widefat" name="name" value="<?php echo esc_attr($f['name']); ?>"></p>
		<p><label>Audience</label><select required class="widefat" name="audience_id"><option value="">Select audience</option><?php if(!is_wp_error($aud)) foreach($aud as $a) printf('<option value="%s" %s>%s (%d)</option>',esc_attr($a['id']),selected($f['audience_id'],$a['id'],false),esc_html($a['name']),intval($a['stats']['member_count']??0)); ?></select></p>
		<p><label>Template</label><select class="widefat emo-live" name="template"><?php foreach($this->templates() as $k=>$v) printf('<option value="%s" %s>%s</option>',esc_attr($k),selected($f['template'],$k,false),esc_html($v)); ?></select></p>
		<p><label>Title</label><input class="widefat emo-live" name="title" value="<?php echo esc_attr($f['title']); ?>"></p><p><label>Description</label><textarea class="widefat emo-live" name="description"><?php echo esc_textarea($f['description']); ?></textarea></p><p><label>Button text</label><input class="widefat emo-live" name="button_text" value="<?php echo esc_attr($f['button_text']); ?>"></p>
		<p><label><input type="checkbox" name="show_first" value="1" <?php checked(!empty($f['show_first'])); ?>> First name</label> <label><input type="checkbox" name="show_last" value="1" <?php checked(!empty($f['show_last'])); ?>> Last name</label> <label><input type="checkbox" name="show_phone" value="1" <?php checked(!empty($f['show_phone'])); ?>> Phone</label></p>
		<p><label><input type="checkbox" name="gdpr" value="1" <?php checked(!empty($f['gdpr'])); ?>> GDPR consent required</label><input class="widefat" name="gdpr_text" value="<?php echo esc_attr($f['gdpr_text']); ?>"></p>
		<p><label><input type="checkbox" name="double_opt_in" value="1" <?php checked(!empty($f['double_opt_in'])); ?>> Double opt-in</label></p>
		<p><label>Tags (comma separated)</label><input class="widefat" name="tags" value="<?php echo esc_attr($f['tags']); ?>"></p><p><label>Interest groups JSON</label><textarea class="widefat" name="groups" placeholder='{"interest-id":true}'><?php echo esc_textarea($f['groups']); ?></textarea></p><p><label>Custom merge-field map JSON</label><textarea class="widefat" name="field_map" placeholder='{"PHONE":"phone"}'><?php echo esc_textarea($f['field_map']); ?></textarea></p>
		<p><label>Success message</label><input class="widefat" name="success_message" value="<?php echo esc_attr($f['success_message']); ?>"></p><p><label>Optional redirect URL</label><input type="url" class="widefat" name="redirect_url" value="<?php echo esc_attr($f['redirect_url']); ?>"></p><?php submit_button($id?'Update form':'Create form'); ?></form></div>
		<div><div class="emo-card"><h2>Live preview</h2><div id="emo-preview" class="emo-form emo-template-<?php echo esc_attr($f['template']); ?>"><h3><?php echo esc_html($f['title']); ?></h3><p><?php echo esc_html($f['description']); ?></p><input placeholder="Email address"><button><?php echo esc_html($f['button_text']); ?></button></div></div><div class="emo-card"><h2>Existing forms</h2><?php if(!$forms) echo '<p>No forms yet.</p>'; foreach($forms as $x){ echo '<div class="emo-form-row"><strong>'.esc_html($x['name']).'</strong><code>[easy_mailchimp_form id="'.esc_attr($x['id']).'"]</code><a href="'.esc_url(admin_url('admin.php?page=easy-mailchimp-forms&edit='.$x['id'])).'">Edit</a> <a class="emo-delete" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=emo_delete_form&id='.$x['id']),'emo_delete_form')).'">Delete</a></div>'; } ?></div></div></div></div><?php
	}

	public function save_form() {
		if(!current_user_can('manage_options')) wp_die('Unauthorized'); check_admin_referer('emo_save_form'); $forms=$this->forms(); $id=sanitize_key($_POST['form_id']??''); if(!$id) $id='form-'.wp_generate_password(8,false,false);
		$forms[$id]=array('id'=>$id,'name'=>sanitize_text_field($_POST['name']??''),'audience_id'=>sanitize_text_field($_POST['audience_id']??''),'template'=>array_key_exists($_POST['template']??'', $this->templates())?sanitize_key($_POST['template']):'classic','title'=>sanitize_text_field($_POST['title']??''),'description'=>sanitize_textarea_field($_POST['description']??''),'button_text'=>sanitize_text_field($_POST['button_text']??'Subscribe'),'success_message'=>sanitize_text_field($_POST['success_message']??'Thank you.'),'show_first'=>empty($_POST['show_first'])?0:1,'show_last'=>empty($_POST['show_last'])?0:1,'show_phone'=>empty($_POST['show_phone'])?0:1,'gdpr'=>empty($_POST['gdpr'])?0:1,'gdpr_text'=>sanitize_text_field($_POST['gdpr_text']??''),'double_opt_in'=>empty($_POST['double_opt_in'])?0:1,'tags'=>sanitize_text_field($_POST['tags']??''),'groups'=>sanitize_textarea_field($_POST['groups']??''),'field_map'=>sanitize_textarea_field($_POST['field_map']??''),'redirect_url'=>esc_url_raw($_POST['redirect_url']??''));
		update_option(self::FORMS,$forms,false); wp_safe_redirect(admin_url('admin.php?page=easy-mailchimp-forms&edit='.$id.'&saved=1')); exit;
	}

	public function delete_form(){ if(!current_user_can('manage_options')) wp_die('Unauthorized'); check_admin_referer('emo_delete_form'); $f=$this->forms(); unset($f[sanitize_key($_GET['id']??'')]); update_option(self::FORMS,$f,false); wp_safe_redirect(admin_url('admin.php?page=easy-mailchimp-forms')); exit; }

	public function shortcode( $atts ) {
		$id=sanitize_key(shortcode_atts(array('id'=>''),$atts)['id']); $forms=$this->forms(); if(empty($forms[$id])) return current_user_can('manage_options')?'<p>Easy Mailchimp form not found.</p>':''; $f=$forms[$id];
		wp_enqueue_style('emo-forms'); wp_enqueue_script('emo-forms'); $this->stat($id,'views'); $s=$this->settings();
		ob_start(); ?><div class="emo-form emo-template-<?php echo esc_attr($f['template']); ?>" data-form="<?php echo esc_attr($id); ?>"><form class="emo-ajax-form"><input type="hidden" name="action" value="emo_subscribe"><input type="hidden" name="form_id" value="<?php echo esc_attr($id); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('emo_subscribe_'.$id)); ?>"><input class="emo-hp" type="text" name="website" tabindex="-1" autocomplete="off"><h3><?php echo esc_html($f['title']); ?></h3><p class="emo-description"><?php echo esc_html($f['description']); ?></p><div class="emo-fields">
		<?php if(!empty($f['show_first'])):?><label><span>First name</span><input name="first_name" autocomplete="given-name"></label><?php endif; if(!empty($f['show_last'])):?><label><span>Last name</span><input name="last_name" autocomplete="family-name"></label><?php endif; if(!empty($f['show_phone'])):?><label><span>Phone</span><input name="phone" type="tel" autocomplete="tel"></label><?php endif; ?><label class="emo-email"><span>Email</span><input required name="email" type="email" autocomplete="email"></label></div>
		<?php if(!empty($f['gdpr'])):?><label class="emo-gdpr"><input required type="checkbox" name="gdpr" value="1"> <span><?php echo esc_html($f['gdpr_text']); ?></span></label><?php endif; ?>
		<?php if($s['spam_provider']==='turnstile' && $s['turnstile_site_key']): ?><div class="cf-turnstile" data-sitekey="<?php echo esc_attr($s['turnstile_site_key']); ?>"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php elseif($s['spam_provider']==='recaptcha' && $s['recaptcha_site_key']): ?><div class="g-recaptcha" data-sitekey="<?php echo esc_attr($s['recaptcha_site_key']); ?>"></div><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
		<button type="submit"><span><?php echo esc_html($f['button_text']); ?></span></button><div class="emo-message" role="status" aria-live="polite"></div></form></div><?php return ob_get_clean();
	}

	public function legacy_shortcode($atts){ $forms=$this->forms(); $first=reset($forms); return $first?$this->shortcode(array('id'=>$first['id'])):''; }

	private function verify_spam(){ $s=$this->settings(); if(!empty($_POST['website'])) return false; if($s['spam_provider']==='honeypot') return true; $token=$s['spam_provider']==='turnstile'?sanitize_text_field($_POST['cf-turnstile-response']??''):sanitize_text_field($_POST['g-recaptcha-response']??''); $secret=$s['spam_provider']==='turnstile'?$s['turnstile_secret_key']:$s['recaptcha_secret_key']; if(!$token||!$secret) return false; $url=$s['spam_provider']==='turnstile'?'https://challenges.cloudflare.com/turnstile/v0/siteverify':'https://www.google.com/recaptcha/api/siteverify'; $r=wp_remote_post($url,array('timeout'=>15,'body'=>array('secret'=>$secret,'response'=>$token,'remoteip'=>$_SERVER['REMOTE_ADDR']??''))); if(is_wp_error($r)) return false; $d=json_decode(wp_remote_retrieve_body($r),true); return !empty($d['success']); }

	public function ajax_subscribe(){ $id=sanitize_key($_POST['form_id']??''); if(!wp_verify_nonce(sanitize_text_field($_POST['nonce']??''),'emo_subscribe_'.$id)) wp_send_json_error(array('message'=>'Security check failed.')); $forms=$this->forms(); if(empty($forms[$id])) wp_send_json_error(array('message'=>'Form not found.')); $f=$forms[$id]; if(!$this->verify_spam()) wp_send_json_error(array('message'=>'Spam verification failed.')); $email=sanitize_email($_POST['email']??''); if(!is_email($email)) wp_send_json_error(array('message'=>'Enter a valid email address.')); if(!empty($f['gdpr'])&&empty($_POST['gdpr'])) wp_send_json_error(array('message'=>'Please accept the consent checkbox.'));
		$merge=array('FNAME'=>sanitize_text_field($_POST['first_name']??''),'LNAME'=>sanitize_text_field($_POST['last_name']??'')); if(!empty($f['show_phone'])) $merge['PHONE']=sanitize_text_field($_POST['phone']??''); $map=json_decode($f['field_map']??'',true); if(is_array($map)) foreach($map as $tag=>$field) if(isset($_POST[$field])) $merge[sanitize_key($tag)]=sanitize_text_field(wp_unslash($_POST[$field])); $body=array('email_address'=>$email,'status_if_new'=>!empty($f['double_opt_in'])?'pending':'subscribed','status'=>!empty($f['double_opt_in'])?'pending':'subscribed','merge_fields'=>array_filter($merge,function($v){return $v!=='';})); $groups=json_decode($f['groups']??'',true); if(is_array($groups)) $body['interests']=$groups; $r=$this->api('PUT','lists/'.rawurlencode($f['audience_id']).'/members/'.md5(strtolower($email)),$body); if(is_wp_error($r)) wp_send_json_error(array('message'=>$r->get_error_message()));
		$tags=array_filter(array_map('trim',explode(',',$f['tags']??''))); if($tags) $this->api('POST','lists/'.rawurlencode($f['audience_id']).'/members/'.md5(strtolower($email)).'/tags',array('tags'=>array_map(function($t){return array('name'=>$t,'status'=>'active');},$tags))); $this->stat($id,'submissions'); wp_send_json_success(array('message'=>$f['success_message'],'redirect'=>$f['redirect_url'])); }

	private function stat($id,$key){ $s=get_option(self::STATS,array()); if(!isset($s[$id]))$s[$id]=array('views'=>0,'submissions'=>0); $s[$id][$key]=intval($s[$id][$key]??0)+1; update_option(self::STATS,$s,false); }
	public function analytics_page(){ $forms=$this->forms(); $s=get_option(self::STATS,array()); echo '<div class="wrap emo-wrap"><h1>Form Analytics</h1><table class="widefat striped"><thead><tr><th>Form</th><th>Views</th><th>Submissions</th><th>Conversion</th></tr></thead><tbody>'; foreach($forms as $id=>$f){$v=intval($s[$id]['views']??0);$n=intval($s[$id]['submissions']??0);echo '<tr><td>'.esc_html($f['name']).'</td><td>'.$v.'</td><td>'.$n.'</td><td>'.($v?esc_html(number_format_i18n(($n/$v)*100,2)).'%':'0%').'</td></tr>';} echo '</tbody></table></div>'; }
	public function tools_page(){ ?><div class="wrap emo-wrap"><h1>Import / Export</h1><div class="emo-grid"><div class="emo-card"><h2>Export</h2><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=emo_export_forms'),'emo_export_forms')); ?>">Download JSON</a></div><div class="emo-card"><h2>Import</h2><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="emo_import_forms"><?php wp_nonce_field('emo_import_forms'); ?><input required type="file" name="import_file" accept="application/json"><?php submit_button('Import forms'); ?></form></div></div></div><?php }
	public function export_forms(){ if(!current_user_can('manage_options'))wp_die('Unauthorized');check_admin_referer('emo_export_forms');nocache_headers();header('Content-Type: application/json');header('Content-Disposition: attachment; filename=easy-mailchimp-forms-'.gmdate('Y-m-d').'.json');echo wp_json_encode(array('version'=>self::VERSION,'forms'=>$this->forms()),JSON_PRETTY_PRINT);exit; }
	public function import_forms(){ if(!current_user_can('manage_options'))wp_die('Unauthorized');check_admin_referer('emo_import_forms');$tmp=$_FILES['import_file']['tmp_name']??'';$d=$tmp?json_decode(file_get_contents($tmp),true):null;if(!is_array($d)||!is_array($d['forms']??null))wp_die('Invalid import file.');update_option(self::FORMS,$d['forms'],false);wp_safe_redirect(admin_url('admin.php?page=easy-mailchimp-tools&imported=1'));exit; }

	public function register_block(){ wp_register_script('emo-block',plugins_url('assets/js/block.js',__FILE__),array('wp-blocks','wp-element','wp-components','wp-block-editor'),self::VERSION,true); wp_localize_script('emo-block','emoBlockData',array('forms'=>array_values(array_map(function($f){return array('id'=>$f['id'],'name'=>$f['name']);},$this->forms())))); register_block_type('easy-mailchimp/form',array('editor_script'=>'emo-block','render_callback'=>function($a){return $this->shortcode(array('id'=>$a['formId']??''));},'attributes'=>array('formId'=>array('type'=>'string','default'=>'')))); }
	public function register_elementor_widget($manager){ if(!class_exists('Elementor\\Widget_Base'))return; $plugin=$this; $forms=$this->forms(); $class=new class($plugin,$forms) extends \Elementor\Widget_Base { private $plugin;private $forms;public function __construct($p,$f,$data=array(),$args=null){$this->plugin=$p;$this->forms=$f;parent::__construct($data,$args);}public function get_name(){return'easy_mailchimp_form';}public function get_title(){return'Easy Mailchimp Form';}public function get_icon(){return'eicon-mail';}public function get_categories(){return array('general');}protected function register_controls(){$o=array();foreach($this->forms as $f)$o[$f['id']]=$f['name'];$this->start_controls_section('content');$this->add_control('form_id',array('label'=>'Form','type'=>\Elementor\Controls_Manager::SELECT,'options'=>$o));$this->end_controls_section();}protected function render(){echo $this->plugin->shortcode(array('id'=>$this->get_settings_for_display('form_id')));}}; $manager->register($class); }
}
EMO_Premium::instance();
