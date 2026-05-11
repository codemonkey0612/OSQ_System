<?php
/**
 * CSV row validation rules.
 *
 * @package OSQ
 */

namespace OSQ\Import;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImportValidator
 *
 * Validates individual CSV rows against field-level rules.
 */
class ImportValidator {

	/**
	 * Validate a single CSV row.
	 *
	 * @param array $row Associative array of column => value.
	 * @param int   $row_number Row number for error messages.
	 * @return array List of error strings (empty = valid).
	 */
	public function validate_row( $row, $row_number ) {
		$errors = array();

		$payload_error = $this->detect_malicious_payload( $row, $row_number );
		if ( ! empty( $payload_error ) ) {
			$errors[] = $payload_error;
			return $errors;
		}

		// Employee number — required.
		if ( empty( $row['employee_number'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Employee number is required.', 'osq-stress-check' ),
				$row_number
			);
		}

		// Date of birth — yyyy/mm/dd, yyyy-mm-dd, dd/mm/yyyy, dd-mm-yyyy, mm/dd/yyyy, or mm-dd-yyyy.
		if ( ! empty( $row['date_of_birth'] ) && ! $this->validate_date( $row['date_of_birth'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Invalid date of birth format. Use yyyy/mm/dd, yyyy-mm-dd, dd/mm/yyyy, dd-mm-yyyy, mm/dd/yyyy, or mm-dd-yyyy.', 'osq-stress-check' ),
				$row_number
			);
		}

		// Gender — 1 (male) or 2 (female).
		if ( ! empty( $row['gender'] ) && ! in_array( (int) $row['gender'], array( 1, 2 ), true ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Gender must be 1 (male) or 2 (female).', 'osq-stress-check' ),
				$row_number
			);
		}

		// Job type — 1-5.
		if ( ! empty( $row['job_type'] ) ) {
			$jt = (int) $row['job_type'];
			if ( $jt < 1 || $jt > 5 ) {
				$errors[] = sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: Job type must be between 1 and 5.', 'osq-stress-check' ),
					$row_number
				);
			}
		}

		// Position — 1 or 2.
		if ( ! empty( $row['position'] ) && ! in_array( (int) $row['position'], array( 1, 2 ), true ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Position must be 1 or 2.', 'osq-stress-check' ),
				$row_number
			);
		}

		// Employment type — 1 or 2.
		if ( ! empty( $row['employment_type'] ) && ! in_array( (int) $row['employment_type'], array( 1, 2 ), true ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Employment type must be 1 or 2.', 'osq-stress-check' ),
				$row_number
			);
		}

		// Email — basic format check.
		if ( ! empty( $row['email'] ) && ! is_email( $row['email'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Invalid email format.', 'osq-stress-check' ),
				$row_number
			);
		}

		// Industry type — integer 1–15.
		if ( ! empty( $row['industry_type'] ) ) {
			$it = (int) $row['industry_type'];
			if ( $it < 1 || $it > 15 ) {
				$errors[] = sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: Industry type must be between 1 and 15.', 'osq-stress-check' ),
					$row_number
				);
			}
		}

		// Hire date — same multi-format date validation as date_of_birth.
		if ( ! empty( $row['hire_date'] ) && ! $this->validate_date( $row['hire_date'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: Invalid hire date format. Use yyyy/mm/dd, yyyy-mm-dd, dd/mm/yyyy, dd-mm-yyyy, mm/dd/yyyy, or mm-dd-yyyy.', 'osq-stress-check' ),
				$row_number
			);
		}

		return $errors;
	}

	/**
	 * Detect suspicious CSV payloads (formula injection / script payloads).
	 *
	 * @param array $row
	 * @param int   $row_number
	 * @return string Empty string if safe.
	 */
	private function detect_malicious_payload( $row, $row_number ) {
		foreach ( $row as $value ) {
			$text = trim( (string) $value );
			if ( '' === $text ) {
				continue;
			}

			// Formula injection patterns used in spreadsheet-based attacks.
			if ( preg_match( '/^[=+\-@]/', $text ) ) {
				return sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: Suspicious spreadsheet formula detected. Import blocked for security.', 'osq-stress-check' ),
					$row_number
				);
			}

			// Script/HTML event payload detection.
			if ( preg_match( '/<\s*script|javascript:|on[a-z]+\s*=|<\s*iframe|<\s*object|data:\s*text\/html/i', $text ) ) {
				return sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: Potential malicious code detected. Import blocked for security.', 'osq-stress-check' ),
					$row_number
				);
			}
		}

		return '';
	}

	/**
	 * Validate date in yyyy/mm/dd or yyyy-mm-dd format.
	 *
	 * @param string $date
	 * @return bool
	 */
	private function validate_date( $date ) {
		$normalized = trim( (string) $date );
		$normalized = str_replace( array( '年', '月', '日' ), array( '-', '-', '' ), $normalized );
		$normalized = str_replace( '/', '-', $normalized );
		$normalized = preg_replace( '/\s+/', '', $normalized );
		$normalized = trim( $normalized, '-' );
		$parts = explode( '-', $normalized );

		if ( count( $parts ) !== 3 ) {
			return false;
		}

		$part_a = (int) $parts[0];
		$part_b = (int) $parts[1];
		$part_c = (int) $parts[2];

		// yyyy-mm-dd
		if ( $part_a > 1900 && $part_b >= 1 && $part_b <= 12 ) {
			return checkdate( $part_b, $part_c, $part_a );
		}

		// dd-mm-yyyy
		if ( $part_c > 1900 && $part_b >= 1 && $part_b <= 12 ) {
			return checkdate( $part_b, $part_a, $part_c );
		}

		// mm-dd-yyyy (fallback when ambiguous)
		if ( $part_c > 1900 && $part_a >= 1 && $part_a <= 12 ) {
			return checkdate( $part_a, $part_b, $part_c );
		}

		return false;
	}
}
