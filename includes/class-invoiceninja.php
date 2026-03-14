<?php
/**
 * Invoice Ninja integration helper.
 *
 * Fetches invoices from an Invoice Ninja instance and maps them to the
 * ProcessFlow order data format so they can be imported as orders.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_InvoiceNinja
 */
class ProcessFlow_InvoiceNinja {

	/**
	 * Fetch all invoices from Invoice Ninja and map to order-data arrays.
	 *
	 * @param string $url   Base URL of the Invoice Ninja instance (e.g. https://app.invoiceninja.com).
	 * @param string $token X-Api-Token for the Invoice Ninja API.
	 * @return array|WP_Error Array of order-data arrays or WP_Error on failure.
	 */
	public function sync_invoices( string $url, string $token ) {
		if ( empty( $url ) || empty( $token ) ) {
			return new WP_Error( 'missing_config', __( 'Invoice Ninja URL and API token are required.', 'processflow-manager' ) );
		}

		$endpoint = trailingslashit( esc_url_raw( $url ) ) . 'api/v1/invoices?per_page=100&include=client';

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'X-Api-Token'  => $token,
					'X-Requested-With' => 'XMLHttpRequest',
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'connection_error',
				sprintf(
					/* translators: %s = original error message */
					__( 'Could not connect to Invoice Ninja: %s', 'processflow-manager' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error( 'unauthorized', __( 'Invalid Invoice Ninja API token.', 'processflow-manager' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: %d = HTTP status code */
					__( 'Invoice Ninja API returned HTTP %d.', 'processflow-manager' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error( 'parse_error', __( 'Unexpected response format from Invoice Ninja.', 'processflow-manager' ) );
		}

		$orders = array();

		foreach ( $data['data'] as $invoice ) {
			$client       = isset( $invoice['client'] ) && is_array( $invoice['client'] ) ? $invoice['client'] : array();
			$client_name  = $client['name'] ?? ( $client['display_name'] ?? __( 'Unknown', 'processflow-manager' ) );
			$company_name = ! empty( $client['company_name'] ) ? $client['company_name'] : $client_name;

			// Extract best phone/WhatsApp contact from client contacts array.
			$whatsapp = '';
			if ( ! empty( $client['contacts'] ) && is_array( $client['contacts'] ) ) {
				foreach ( $client['contacts'] as $contact ) {
					if ( ! empty( $contact['phone'] ) ) {
						$whatsapp = $contact['phone'];
						break;
					}
				}
			}
			if ( empty( $whatsapp ) && ! empty( $client['phone'] ) ) {
				$whatsapp = $client['phone'];
			}

			$invoice_number = $invoice['number'] ?? ( $invoice['invoice_number'] ?? '' );
			$amount         = isset( $invoice['amount'] ) ? number_format( (float) $invoice['amount'], 2 ) : '';
			$due_date       = $invoice['due_date'] ?? '';
			// Invoice Ninja v5 status IDs (https://invoice-ninja.readthedocs.io/en/latest/api.html).
			$status_map = array( 1 => 'Draft', 2 => 'Sent', 3 => 'Partial', 4 => 'Paid', 5 => 'Overdue', 6 => 'Cancelled' );
			$status_id      = isset( $invoice['status_id'] ) ? (int) $invoice['status_id'] : 0;
			$status_label   = $status_map[ $status_id ] ?? 'Unknown';

			$job_details = sprintf(
				/* translators: 1: invoice number, 2: amount, 3: status, 4: due date */
				__( 'Invoice #%1$s | Amount: %2$s | Status: %3$s | Due: %4$s', 'processflow-manager' ),
				$invoice_number,
				$amount,
				$status_label,
				$due_date
			);

			$orders[] = array(
				'customer_name' => $client_name,
				'business_name' => $company_name,
				'whatsapp'      => $whatsapp,
				'job_details'   => $job_details,
				'invoice_id'    => $invoice['id'] ?? '',
			);
		}

		return $orders;
	}
}
