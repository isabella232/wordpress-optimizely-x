<?php
/**
 * Optimizely X: AJAX_Results class
 *
 * @package Optimizely_X
 * @since 1.2.0
 */

namespace Optimizely_X;

/**
 * Defines AJAX endpoints that communicate with
 * the API via the Optimizely X Results page
 *
 * @since 1.2.0
 */
class AJAX_Results extends AJAX {

	use Singleton;

	/**
	 * An AJAX endpoint to update the experiment status
	 * from the results dashboard
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function update_experiment_status() {

		if ( ! current_user_can( Filters::admin_capability() ) ) {
			wp_send_json_error( __(
				'The current user is not authorized.',
				'optimizely-x'
			) );
		}

		// Validate nonce.
		if ( ! check_ajax_referer( 'optimizely-results', 'nonce', false ) ) {
			wp_send_json_error( __(
				'Failed to validate the nonce',
				'optimizely-x'
			) );
		}

		// Check for error condition.
		if ( empty( $_POST['experimentId'] ) || empty( $_POST['status'] ) ) { // Input var okay.
			wp_send_json_error( __(
				'Missing experimentId or status value.',
				'optimizely-x'
			) );
		}

		// Sanitize postdata before proceeding.
		$experiment_id = absint( wp_unslash( $_POST['experimentId'] ) ); // Input var okay.
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ) ); // Input var okay.

		// Ensure we have a post ID before proceeding.
		$query_args = [
			'meta_key'      => 'optimizely_experiment_id', // Potentialy slow query
			'meta_value'    => $experiment_id, // Potentialy slow query
			'post_type'     => 'any',
			'no_found_rows' => true,
			'posts_per_page' => 1,
		];

		$experiment_query = new \WP_Query( $query_args );

		if ( ! $experiment_query->post_count ) {
			wp_send_json_error( __( 'Missing a post attached to this experiment.', 'optimizely-x' ) );
		}

		$post_id = $experiment_query->posts[0]->ID;

		// Build API request URL.
		$operation = '/experiments/' . $experiment_id . '?action=' . $status;

		// Process the request and check for errors.
		$response = $this->api->patch( $operation );

		// Clear the transient for this experiment's endpoint
		$this->api->delete_endpoint_transient( '/experiments/' . $experiment_id );

		$this->maybe_send_error_response( $response );

		// Ensure we got a status in the response.
		if ( empty( $response['json']['status'] ) ) {
			wp_send_json_error( __(
				'No status included in the API response.',
				'optimizely-x'
			) );
		}

		// Update the status in postmeta.
		update_post_meta(
			$post_id,
			'optimizely_experiment_status',
			sanitize_text_field( $response['json']['status'] )
		);

		// Return the status in the AJAX response.
		wp_send_json_success( array(
			'experiment_status' => sanitize_text_field( $response['json']['status'] ),
		) );
	}

	/**
	 * An AJAX endpoint to archive the experiment
	 * from the results dashboard
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function archive_experiment() {

		if ( ! current_user_can( Filters::admin_capability() ) ) {
			wp_send_json_error( __(
				'The current user is not authorized.',
				'optimizely-x'
			) );
		}

		// Validate nonce.
		if ( ! check_ajax_referer( 'optimizely-results', 'nonce', false ) ) {
			wp_send_json_error( __(
				'Failed to validate the nonce',
				'optimizely-x'
			) );
		}

		// Check for error condition.
		if ( empty( $_POST['experimentId'] ) ) { // Input var okay.
			wp_send_json_error( __(
				'Missing experimentId value.',
				'optimizely-x'
			) );
		}

		// Sanitize postdata before proceeding.
		$experiment_id = absint( wp_unslash( $_POST['experimentId'] ) ); // Input var okay.
		$project_id = absint( get_option( 'optimizely_x_project_id' ) );

		// Ensure we have a post ID before proceeding.
		$query_args = [
			'meta_key' => 'optimizely_experiment_id', // Potential slow query
			'meta_value' => $experiment_id, // Potential slow query
			'post_type' => 'any',
			'no_found_rows' => true,
			'posts_per_page' => 1,
		];

		$experiment_query = new \WP_Query( $query_args );

		if ( ! $experiment_query->post_count ) {
			wp_send_json_error( __( 'Missing a post attached to this experiment.', 'optimizely-x' ) );
		}

		$post_id = $experiment_query->posts[0]->ID;

		// Build API request URL.
		$operation = '/experiments/' . $experiment_id;

		// Archiving an experiment requires us to use the DELETE
		// method however this will not actually delete the experiment
		// in Optimizely (it changes its status to archived).
		$response = $this->api->delete( $operation );

		// Clear the transient for the entire experiment list
		$this->api->delete_endpoint_transient( '/experiments', [
			'project_id' => $project_id,
		] );

		// Clear the transient for this experiment's endpoint
		$this->api->delete_endpoint_transient( '/experiments/' . $experiment_id );

		$this->maybe_send_error_response( $response );

		// Update the status in postmeta.
		update_post_meta(
			$post_id,
			'optimizely_experiment_status',
			'archived'
		);

		// Return the status in the AJAX response.
		wp_send_json_success( array(
			'experiment_status' => 'archived',
		) );
	}

	/**
	 * An AJAX endpoint to launch the experiment's variation and archive it
	 * from the results dashboard
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function launch_variation() {

		if ( ! current_user_can( Filters::admin_capability() ) ) {
			wp_send_json_error( __(
				'The current user is not authorized.',
				'optimizely-x'
			) );
		}

		// Validate nonce.
		if ( ! check_ajax_referer( 'optimizely-results', 'nonce', false ) ) {
			wp_send_json_error( __(
				'Failed to validate the nonce',
				'optimizely-x'
			) );
		}

		// Check for error condition.
		if ( empty( $_POST['experimentId'] ) || empty( $_POST['variationText'] ) ) { // Input var okay.
			wp_send_json_error( __(
				'Missing experimentId or variationText value.',
				'optimizely-x'
			) );
		}

		// Sanitize postdata before proceeding.
		$experiment_id = absint( wp_unslash( $_POST['experimentId'] ) ); // Input var okay.
		$variation_text = sanitize_text_field( wp_unslash( $_POST['variationText'] ) ); // Input var okay.

		// Ensure we have a post ID before proceeding.
		$query_args = [
			'meta_key' => 'optimizely_experiment_id', // Potential slow query
			'meta_value' => $experiment_id, // Potential slow query
			'post_type' => 'any',
			'no_found_rows' => true,
			'posts_per_page' => 1,
		];

		$experiment_query = new \WP_Query( $query_args );

		if ( ! $experiment_query->post_count ) {
			wp_send_json_error( __( 'Missing a post attached to this experiment.', 'optimizely-x' ) );
		}

		// Update the post title with the launched title
		$experiment_query->posts[0]->post_title = $variation_text;
		$updated = wp_update_post( $experiment_query->posts[0] );

		if ( ! $updated ) {
			wp_send_json_error( __( 'There was a problem updating the post with the new title.', 'optimizely-x' ) );
		}

		$post_id = $experiment_query->posts[0]->ID;

		// Build API request URL.
		$operation = '/experiments/' . $experiment_id;

		// Archiving an experiment requires us to use the DELETE
		// method however this will not actually delete the experiment
		// in Optimizely (it changes its status to archived).
		$response = $this->api->delete( $operation );

		// Clear the transient for the entire experiment list
		$this->api->delete_endpoint_transient( '/experiments', [
			'project_id' => $project_id,
		] );

		// Clear the transient for this experiment's endpoint
		$this->api->delete_endpoint_transient( '/experiments/' . $experiment_id );

		$this->maybe_send_error_response( $response );

		// Update the status in postmeta.
		update_post_meta(
			$post_id,
			'optimizely_experiment_status',
			'archived'
		);

		// Return the status in the AJAX response.
		wp_send_json_success( array(
			'experiment_status' => 'archived',
		) );
	}

	/**
	 * Registers action and filter hooks and initializes the API object.
	 *
	 * @since 1.2.0
	 * @access protected
	 */
	protected function setup() {
		parent::setup();

		// Register action hooks.
		add_action(
			'wp_ajax_optimizely_x_update_experiment_status',
			array( $this, 'update_experiment_status' )
		);
		add_action(
			'wp_ajax_optimizely_x_archive_experiment',
			array( $this, 'archive_experiment' )
		);
		add_action(
			'wp_ajax_optimizely_x_launch_variation',
			array( $this, 'launch_variation' )
		);
	}

}
