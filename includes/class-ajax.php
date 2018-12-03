<?php
/**
 * Optimizely X: AJAX class
 *
 * @package Optimizely_X
 * @since 1.2.0
 */

namespace Optimizely_X;

/**
 * Parent class for all other AJAX classes
 *
 * @since 1.2.0
 */
abstract class AJAX {

	/**
	 * An instance of the Optimizely API object, used to communicate with the API.
	 *
	 * @since 1.2.0
	 * @access protected
	 * @var API
	 */
	protected $api;

	/**
	 * Registers action and filter hooks and initializes the API object.
	 *
	 * @since 1.2.0
	 * @access private
	 */
	protected function setup() {
		// Initialize the API.
		$this->api = new API;
	}

	/**
	 * Handles sending error responses, given an API response array.
	 *
	 * @param array $response The response array, returned from the API class.
	 *
	 * @since 1.2.0
	 * @access private
	 */
	protected function maybe_send_error_response( $response ) {

		// Handle WP_Errors.
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response );
		}

		// Handle other errors.
		// If the operation was successful, don't send an error.
		if ( ! empty( $response['status'] ) && 'SUCCESS' === $response['status'] ) {
			return;
		}

		// Flatten the error list, if necessary.
		if ( is_array( $response['error'] ) ) {
			$response['error'] = implode( "\n", $response['error'] );
		}

		// Send the error data in the response.
		wp_send_json_error( sanitize_text_field( $response['error'] ) );
	}

	/**
	 * Log API request and response to post meta.
	 * Purpose is debugging response errors and providing diagnostic data to Optimizely.
	 *
	 * @param int $post_id Post ID to save the meta to.
	 * @param string $method The HTTP method.
	 * @param string $operation The operation URL endpoint.
	 * @param array $data Data included in the request.
	 * @param WP_Error\array $response Response from the API request method.
	 *                                 Either an error, object, or array.
	 */
	protected function log_request( $post_id, $method, $operation, $data, $response ) {
		$log_meta_key = '_optimizely_request_log';
		add_post_meta( $post_id, $log_meta_key, array(
			'method' => $method,
			'operation' => $operation,
			'data' => $data,
			'response' => (array) $response,
			'time' => date( 'M j, Y, g:i a T', current_time( 'timestamp' ) ),
		) );
	}
}
