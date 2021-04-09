<?php

namespace Automattic\VIP\Security;

add_action( 'user_profile_update_errors', __NAMESPACE__ . '\validate_current_password', 1, 3 );

function validate_current_password( $errors, $update, $user ) {
	check_admin_referer( 'update-user_' . $user->ID );

	if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) || ! $update ) {
		return;
	}

	if ( ! isset( $_POST['current_pass'] ) || empty( $_POST['current_pass'] ) ) {
		$errors->add( 'empty_current_password', __( '<strong>Error</strong>: Please enter your current password.' ), array( 'form-field' => 'current_pass' ) );
		return;
	}

	$error = wp_authenticate( $user->user_login, $_POST['current_pass'] );
	if ( is_wp_error( $error ) ) {
		$errors->add( 'wrong_current_password', __( '<strong>Error</strong>: The entered current password is not correct.' ), array( 'form-field' => 'current_pass' ) );
	}
}

add_action( 'show_user_profile', __NAMESPACE__ . '\add_current_password_field' );

function add_current_password_field() { ?>
	<table id="nojs-current-pass" class="form-table" role="presentation">
		<tr>
			<th><label for="current_pass"><?php _e( 'Current Password' ); ?></label></th>
			<td>
				<div id="current-password-confirm">
					<input type="password" name="current_pass" id="current_pass" class="regular-text" value="" autocomplete="off" />
					<p class="description">
						<?php _e( 'Please type your <strong>current password</strong> to update it' ); ?>.
					</p>
				</div>

			</td>
		</tr>
	</table>
	<?php 
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\reposition_current_password_field' );

/**
 * Custom JS to reposition the current password field in admin
 *
 * @return null
 */
function reposition_current_password_field() {
	$screen = get_current_screen();
	if ( 'profile' === $screen->id ) {
		wp_enqueue_script( 'vip-security-password-script', plugins_url( '/js/password.js', __FILE__ ), array( 'jquery' ), '3.0', true );
	}
}
