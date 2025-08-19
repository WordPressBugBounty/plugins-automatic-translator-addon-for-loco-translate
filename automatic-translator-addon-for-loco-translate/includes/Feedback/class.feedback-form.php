<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'ATLT_FeedbackForm' ) ) {

	class ATLT_FeedbackForm {

		private $plugin_url     = ATLT_URL;
		private $plugin_version = ATLT_VERSION;
		private $plugin_name    = 'LocoAI – Auto Translate for Loco Translate';
		private $plugin_slug    = 'atlt';
		private $feedback_url   = ATLT_FEEDBACK_API.'wp-json/coolplugins-feedback/v1/feedback';

		/*
		|-----------------------------------------------------------------|
		|   Use this constructor to fire all actions and filters          |
		|-----------------------------------------------------------------|
		*/
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_feedback_scripts' ) );
			add_action( 'admin_head', array( $this, 'show_deactivate_feedback_popup' ) );
			add_action( 'wp_ajax_' . $this->plugin_slug . '_submit_deactivation_response', array( $this, 'submit_deactivation_response' ) );
		}

		/*
		|-----------------------------------------------------------------|
		|   Enqueue all scripts and styles to required page only          |
		|-----------------------------------------------------------------|
		*/
		function enqueue_feedback_scripts() {
			$screen = get_current_screen();
			if ( isset( $screen ) && $screen->id == 'plugins' ) {
				wp_enqueue_script( 'atlt-free-feedback-script', $this->plugin_url . 'includes/Feedback/js/admin-feedback.js', array( 'jquery' ), $this->plugin_version );
				wp_enqueue_style( 'cool-plugins-feedback-style', $this->plugin_url . 'includes/Feedback/css/admin-feedback.css', null, $this->plugin_version );
			}
		}

		/*
		|-----------------------------------------------------------------|
		|   HTML for creating feedback popup form                         |
		|-----------------------------------------------------------------|
		*/
		public function show_deactivate_feedback_popup() {
			$screen = get_current_screen();
			if ( ! isset( $screen ) || $screen->id != 'plugins' ) {
				return;
			}

			$deactivate_reasons = array(
				'didnt_work_as_expected'         => array(
					'title'             => __( 'The plugin didn\'t work as expected', 'cool-plugins' ),
					'input_placeholder' => 'What did you expect?',
				),
				'found_a_better_plugin'          => array(
					'title'             => __( 'I found a better plugin', 'cool-plugins' ),
					'input_placeholder' => __( 'Please share which plugin', 'cool-plugins' ),
				),
				'couldnt_get_the_plugin_to_work' => array(
					'title'             => __( 'The plugin is not working', 'cool-plugins' ),
					'input_placeholder' => 'Please share your issue. So we can fix that for other users.',
				),
				'temporary_deactivation'         => array(
					'title'             => __( 'It\'s a temporary deactivation', 'cool-plugins' ),
					'input_placeholder' => '',
				),
				'other'                          => array(
					'title'             => __( 'Other', 'cool-plugins' ),
					'input_placeholder' => __( 'Please share the reason', 'cool-plugins' ),
				),
			);

			?>
		<div id="cool-plugins-deactivate-feedback-dialog-wrapper" class="hide-feedback-popup" data-slug="<?php echo esc_attr( $this->plugin_slug ); ?>">
						
			<div class="cool-plugins-deactivation-response">
			<div id="cool-plugins-deactivate-feedback-dialog-header">
				<span id="cool-plugins-feedback-form-title"><?php echo esc_html__( 'Quick Feedback', 'cool-plugins' ); ?></span>
			</div>
			<div id="cool-plugins-loader-wrapper">
				<div class="cool-plugins-loader-container">
					<img class="cool-plugins-preloader" src="<?php echo esc_url( $this->plugin_url . 'includes/Feedback/images/cool-plugins-preloader.gif' ); ?>">
				</div>
			</div>
			<div id="cool-plugins-form-wrapper" class="cool-plugins-form-wrapper-cls">
			<form id="cool-plugins-deactivate-feedback-dialog-form" method="post">
				<?php
				wp_nonce_field( '_cool-plugins_deactivate_feedback_nonce' );
				?>
				<input type="hidden" name="action" value="cool-plugins_deactivate_feedback" />
				<div id="cool-plugins-deactivate-feedback-dialog-form-caption"><?php echo esc_html__( 'If you have a moment, please share why you are deactivating this plugin.', 'cool-plugins' ); ?></div>
				<div id="cool-plugins-deactivate-feedback-dialog-form-body">
					<?php foreach ( $deactivate_reasons as $reason_key => $reason ) : ?>
						<div class="cool-plugins-deactivate-feedback-dialog-input-wrapper">
							<input id="cool-plugins-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="cool-plugins-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="<?php echo esc_attr( $reason_key ); ?>" />
							<label for="cool-plugins-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="cool-plugins-deactivate-feedback-dialog-label"><?php echo esc_html( $reason['title'] ); ?></label>
							<?php if ( ! empty( $reason['input_placeholder'] ) ) : ?>
								<textarea class="cool-plugins-feedback-text" type="textarea" name="reason_<?php echo esc_attr( $reason_key ); ?>" placeholder="<?php echo esc_attr( $reason['input_placeholder'] ); ?>"></textarea>
							<?php endif; ?>
							<?php if ( ! empty( $reason['alert'] ) ) : ?>
								<div class="cool-plugins-feedback-text"><?php echo esc_html( $reason['alert'] ); ?></div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<input class="cool-plugins-GDPR-data-notice" id="cool-plugins-GDPR-data-notice-<?php echo esc_attr( $this->plugin_slug ); ?>" type="checkbox"><label for="cool-plugins-GDPR-data-notice-<?php echo esc_attr( $this->plugin_slug ); ?>"><?php echo esc_html__( 'I agree to share anonymous usage data and basic site details (such as server, PHP, and WordPress versions) to support LocoAI – Auto Translate for Loco Translate improvement efforts. Additionally, I allow Cool Plugins to store all information provided through this form and to respond to my inquiry', 'cool-plugins' ); ?></label>
				</div>
				<div class="cool-plugin-popup-button-wrapper">
					<a class="cool-plugins-button button-deactivate" id="atlt-cool-plugin-submitNdeactivate">Submit and Deactivate</a>
					<a class="cool-plugins-button" id="atlt-cool-plugin-skipNdeactivate">Skip and Deactivate</a>
				</div>
			</form>
			</div>
		   </div>
		</div>
			<?php
		}


		function submit_deactivation_response() {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], '_cool-plugins_deactivate_feedback_nonce' ) ) {
				wp_send_json_error();
			} else {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
				}

				$reason             = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
				$deactivate_reasons = array(
					'didnt_work_as_expected'         => array(
						'title'             => __( 'The plugin didn\'t work as expected', 'cool-plugins' ),
						'input_placeholder' => 'What did you expect?',
					),
					'found_a_better_plugin'          => array(
						'title'             => __( 'I found a better plugin', 'cool-plugins' ),
						'input_placeholder' => __( 'Please share which plugin', 'cool-plugins' ),
					),
					'couldnt_get_the_plugin_to_work' => array(
						'title'             => __( 'The plugin is not working', 'cool-plugins' ),
						'input_placeholder' => 'Please share your issue. So we can fix that for other users.',
					),
					'temporary_deactivation'         => array(
						'title'             => __( 'It\'s a temporary deactivation', 'cool-plugins' ),
						'input_placeholder' => '',
					),
					'other'                          => array(
						'title'             => __( 'Other', 'cool-plugins' ),
						'input_placeholder' => __( 'Please share the reason', 'cool-plugins' ),
					),
				);

				$plugin_initial     = get_option( 'atlt_initial_save_version' );
				$deativation_reason = array_key_exists( $reason, $deactivate_reasons ) ? $reason : 'other';

				$raw_message       = isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
				$sanitized_message = $raw_message === '' ? 'N/A' : sanitize_text_field( $raw_message );
				$admin_email       = sanitize_email( get_option( 'admin_email' ) );
				$site_url          = get_site_url();
				$install_date      = get_option( 'atlt-install-date' );
				$unique_key        = '8';  // Ensure this key is unique per plugin to prevent collisions when site URL and install date are the same across plugins
				$site_id           = $site_url . '-' . $install_date . '-' . $unique_key;
				$site_url          = esc_url( site_url() );

				$body = array(
					'site_id'        => md5( $site_id ),
					'plugin_initial' => isset( $plugin_initial ) ? sanitize_text_field( $plugin_initial ) : 'N/A',
					'plugin_version' => $this->plugin_version,
					'plugin_name'    => $this->plugin_name,
					'reason'         => $deativation_reason,
					'review'         => $sanitized_message,
					'server_info' => serialize(LocoAutoTranslateAddon::atlt_get_user_info()['server_info']),
						'extra_details' => serialize(LocoAutoTranslateAddon::atlt_get_user_info()['extra_details']),
					'domain'         => $site_url,
					'email' => $admin_email,
				);

				$response = wp_remote_post(
					$this->feedback_url,
					array(
						'timeout' => 30,
						'body'    => $body,
					)
				);

				$status_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
				wp_send_json_success( array( 'status' => $status_code ) );
			}

		}
	}

}
