<?php
/**
 * CSV batch import processor.
 *
 * @package OSQ
 */

namespace OSQ\Import;

use OSQ\Auth\RoleManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CsvImporter
 *
 * Parses CSV files, validates rows, creates employee records and WP user accounts.
 */
class CsvImporter {

	/**
	 * Expected CSV column headers (order-independent).
	 */
	const REQUIRED_COLUMNS = array( 'employee_number' );

	const ALL_COLUMNS = array(
		'employee_number',
		'name',
		'email',
		'gender',
		'date_of_birth',
		'organization_1',
		'organization_2',
		'organization_3',
		'organization_4',
		'organization_5',
		'job_type',
		'position',
		'employment_type',
		'industry_type',
		'hire_date',
	);

	/**
	 * Supported header aliases (English + Japanese) mapped to canonical keys.
	 *
	 * @var array
	 */
	const HEADER_ALIASES = array(
		'employee_number' => array(
			'employee_number',
			'employee number',
			'employee no',
			'employee_no',
			'employee id',
			'employee_id',
			'社員番号',
			'従業員番号',
		),
		'name' => array(
			'name',
			'employee name',
			'氏名',
			'名前',
			'従業員名',
		),
		'email' => array(
			'email',
			'email_address',
			'email address',
			'email address or paper',
			'email or paper',
			'email_address_or_paper',
			'メールアドレス or 紙',
			'メールアドレス',
			'メールアドレス又は紙',
			'メールアドレスまたは紙',
		),
		'gender' => array(
			'gender',
			'性別',
		),
		'date_of_birth' => array(
			'date_of_birth',
			'date of birth',
			'birth date',
			'生年月日',
			'誕生日',
		),
		'organization_1' => array(
			'organization_1',
			'organization 1',
			'organization1',
			'organization level 1',
			'組織1',
			'組織レベル1',
			'部署',
			'部門',
		),
		'organization_2' => array(
			'organization_2',
			'organization 2',
			'organization2',
			'organization level 2',
			'組織2',
			'組織レベル2',
		),
		'organization_3' => array(
			'organization_3',
			'organization 3',
			'organization3',
			'organization level 3',
			'組織3',
			'組織レベル3',
		),
		'organization_4' => array(
			'organization_4',
			'organization 4',
			'organization4',
			'organization level 4',
			'組織4',
			'組織レベル4',
		),
		'organization_5' => array(
			'organization_5',
			'organization 5',
			'organization5',
			'organization level 5',
			'組織5',
			'組織レベル5',
		),
		'job_type' => array(
			'job_type',
			'job type',
			'職種',
		),
		'position' => array(
			'position',
			'job position',
			'役職',
			'職位',
		),
		'employment_type' => array(
			'employment_type',
			'employment type',
			'雇用形態',
		),
		'industry_type' => array(
			'industry_type',
			'industry type',
			'industry code',
			'業種',
			'業種コード',
			'業種タイプ',
		),
		'hire_date' => array(
			'hire_date',
			'hire date',
			'joining date',
			'入社日',
			'入社年月日',
			'採用日',
		),
	);

	/**
	 * @var ImportValidator
	 */
	private $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new ImportValidator();
	}

	/**
	 * Import employees from a CSV file.
	 *
	 * @param string $file_path Absolute path to the uploaded CSV.
	 * @return array Import summary with successes, errors, and skipped counts.
	 */
	public function import( $file_path ) {
		$result = array(
			'total'    => 0,
			'success'  => 0,
			'skipped'  => 0,
			'errors'   => array(),
			'added'    => array(), // Captures credentials for display.
		);

		// Read and convert encoding.
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			$result['errors'][] = __( 'Could not read the CSV file.', 'osq-stress-check' );
			return $result;
		}

		$content = $this->normalize_encoding( $content );
		$lines   = $this->parse_csv_string( $content );

		if ( count( $lines ) < 2 ) {
			$result['errors'][] = __( 'CSV file must contain a header row and at least one data row.', 'osq-stress-check' );
			return $result;
		}

		// Detect header row (supports templates with a lead instruction row).
		$header_row_index = $this->detect_header_row_index( $lines );
		$raw_header_row   = $lines[ $header_row_index ];
		$header           = $this->normalize_headers( $raw_header_row );

		// Capture raw org header text → save as dynamic labels for this tenant.
		$raw_org_headers = array();
		foreach ( $header as $col_index => $canonical ) {
			for ( $level = 1; $level <= 5; $level++ ) {
				if ( 'organization_' . $level === $canonical ) {
					$raw_text = trim( $raw_header_row[ $col_index ] ?? '' );
					if ( '' !== $raw_text ) {
						$raw_org_headers[ $level ] = $raw_text;
					}
				}
			}
		}
		if ( ! empty( $raw_org_headers ) ) {
			\OSQ\Services\OrgLabelService::save_from_headers(
				\OSQ\Database\DbManager::current_company_id(),
				$raw_org_headers
			);
		}

		// Validate required columns exist.
		foreach ( self::REQUIRED_COLUMNS as $col ) {
			if ( ! in_array( $col, $header, true ) ) {
				$result['errors'][] = sprintf(
					/* translators: %s: column name. */
					__( 'Required column "%s" not found in CSV header.', 'osq-stress-check' ),
					$col
				);
				return $result;
			}
		}

		if ( ( $header_row_index + 1 ) >= count( $lines ) ) {
			$result['errors'][] = __( 'CSV file must contain a header row and at least one data row.', 'osq-stress-check' );
			return $result;
		}

		// Process data rows.
		$db = \OSQ\Plugin::get_instance()->db();

		for ( $i = $header_row_index + 1; $i < count( $lines ); $i++ ) {
			$result['total']++;
			$row_number = $i + 1; // Human-readable (header = row 1).

			// Map columns to values.
			$row = $this->map_row( $header, $lines[ $i ] );

			// Validate.
			$errors = $this->validator->validate_row( $row, $row_number );
			if ( ! empty( $errors ) ) {
				$result['errors'] = array_merge( $result['errors'], $errors );
				$result['skipped']++;
				continue;
			}

			// Check for duplicate employee number — update instead of skip.
			$existing = $db->get_employee_by_number( $row['employee_number'] );
			if ( $existing ) {
				$this->update_employee( $db, $existing, $row );
				$result['success']++;
				continue;
			}

			// Create WordPress user account.
			$password   = wp_generate_password( 12, true, false );
			$wp_user_id = $this->create_wp_user( $row, $password );
			if ( is_wp_error( $wp_user_id ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: row number, 2: error message. */
					__( 'Row %1$d: Failed to create user — %2$s', 'osq-stress-check' ),
					$row_number,
					$wp_user_id->get_error_message()
				);
				$result['skipped']++;
				continue;
			}

			// Insert employee record.
			$this->insert_employee( $db, $row, $wp_user_id );

			$result['success']++;
			$result['added'][] = array(
				'number'   => $row['employee_number'],
				'name'     => $row['name'] ?? '',
				'password' => $password,
			);
		}

		return $result;
	}

	/**
	 * Detect and normalize encoding to UTF-8.
	 *
	 * @param string $content Raw file content.
	 * @return string UTF-8 content.
	 */
	private function normalize_encoding( $content ) {
		// Strip UTF-8 BOM.
		if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$content = substr( $content, 3 );
		}

		// Detect Shift-JIS and convert.
		$encoding = mb_detect_encoding( $content, array( 'UTF-8', 'SJIS', 'SJIS-win', 'EUC-JP' ), true );
		if ( $encoding && strtoupper( $encoding ) !== 'UTF-8' ) {
			$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
		}

		return $content;
	}

	/**
	 * Parse CSV string into array of rows.
	 *
	 * @param string $content
	 * @return array
	 */
	private function parse_csv_string( $content ) {
		$lines  = array();
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $content );
		rewind( $handle );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			// Skip completely empty rows.
			if ( count( $row ) === 1 && empty( $row[0] ) ) {
				continue;
			}
			$lines[] = $row;
		}

		fclose( $handle );
		return $lines;
	}

	/**
	 * Map header columns to row values.
	 *
	 * @param array $header
	 * @param array $values
	 * @return array Associative.
	 */
	private function map_row( $header, $values ) {
		$row = array();
		foreach ( $header as $index => $col ) {
			$row[ $col ] = isset( $values[ $index ] ) ? trim( $values[ $index ] ) : '';
		}
		return $row;
	}

	/**
	 * Normalize incoming header row to canonical keys.
	 *
	 * @param array $header_row
	 * @return array
	 */
	private function normalize_headers( $header_row ) {
		$normalized = array();
		$alias_map  = $this->get_alias_map();

		foreach ( $header_row as $raw_header ) {
			$candidates = $this->build_header_candidates( $raw_header );
			$canonical  = null;

			foreach ( $candidates as $candidate ) {
				if ( isset( $alias_map[ $candidate ] ) ) {
					$canonical = $alias_map[ $candidate ];
					break;
				}
			}

			if ( null === $canonical ) {
				$canonical = $this->normalize_header_key( $raw_header );
			}

			$normalized[] = $canonical;
		}

		return $normalized;
	}

	/**
	 * Build normalized header candidates for flexible matching.
	 *
	 * Supports multiline template headers and parenthetical notes.
	 *
	 * @param string $raw_header Original header cell text.
	 * @return array
	 */
	private function build_header_candidates( $raw_header ) {
		$candidates = array();
		$raw        = (string) $raw_header;
		$lines      = preg_split( '/\r\n|\r|\n/u', $raw );
		$first_line = ! empty( $lines[0] ) ? $lines[0] : $raw;

		$variants = array(
			$raw,
			$first_line,
			$this->strip_bracketed_notes( $raw ),
			$this->strip_bracketed_notes( $first_line ),
		);

		foreach ( $variants as $variant ) {
			$key = $this->normalize_header_key( $variant );
			if ( '' !== $key ) {
				$candidates[] = $key;
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Strip bracketed helper text from header labels.
	 *
	 * @param string $text Header text.
	 * @return string
	 */
	private function strip_bracketed_notes( $text ) {
		return (string) preg_replace( '/[（(][^）)]*[）)]/u', '', (string) $text );
	}

	/**
	 * Detect which row is the actual header row.
	 *
	 * Scans first few rows to support template sheets with guide text above headers.
	 *
	 * @param array $lines Parsed CSV rows.
	 * @return int
	 */
	private function detect_header_row_index( $lines ) {
		$scan_limit = min( count( $lines ), 3 );

		for ( $i = 0; $i < $scan_limit; $i++ ) {
			$header = $this->normalize_headers( $lines[ $i ] );
			if ( $this->has_required_columns( $header ) ) {
				return $i;
			}
		}

		return 0;
	}

	/**
	 * Check if all required columns are present in header row.
	 *
	 * @param array $header Normalized header row.
	 * @return bool
	 */
	private function has_required_columns( $header ) {
		foreach ( self::REQUIRED_COLUMNS as $col ) {
			if ( ! in_array( $col, $header, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert alias table to lookup map.
	 *
	 * @return array alias => canonical
	 */
	private function get_alias_map() {
		$map = array();
		foreach ( self::HEADER_ALIASES as $canonical => $aliases ) {
			foreach ( $aliases as $alias ) {
				$map[ $this->normalize_header_key( $alias ) ] = $canonical;
			}
		}
		return $map;
	}

	/**
	 * Header-key normalizer for English/Japanese columns.
	 *
	 * @param string $header
	 * @return string
	 */
	private function normalize_header_key( $header ) {
		$key = trim( (string) $header );
		$key = str_replace( "\xEF\xBB\xBF", '', $key ); // UTF-8 BOM.
		$key = preg_replace( '/\s+/u', ' ', $key );
		$key = strtolower( $key );
		$key = str_replace( array( '-', ' ', '.', '/', '\\' ), '_', $key );
		$key = preg_replace( '/_+/', '_', $key );
		return trim( $key, '_' );
	}

	/**
	 * Create a WordPress user for the employee.
	 *
	 * @param array  $row
	 * @param string $password
	 * @return int|\WP_Error User ID or error.
	 */
	private function create_wp_user( $row, $password ) {
		$email    = ! empty( $row['email'] ) ? $row['email'] : $row['employee_number'] . '@osq.local';

		$user_id = wp_insert_user( array(
			'user_login'   => sanitize_user( $row['employee_number'] ),
			'user_pass'    => $password,
			'user_email'   => sanitize_email( $email ),
			'display_name' => ! empty( $row['name'] ) ? sanitize_text_field( $row['name'] ) : $row['employee_number'],
			'role'         => RoleManager::EMPLOYEE,
		) );

		if ( ! is_wp_error( $user_id ) ) {
			// Flag for first-login password change.
			update_user_meta( $user_id, 'osq_must_change_password', true );
			update_user_meta( $user_id, 'osq_initial_password', $password );
		}

		return $user_id;
	}

	/**
	 * Insert employee record into the database.
	 *
	 * @param \OSQ\Database\DbManager $db
	 * @param array                    $row
	 * @param int                      $wp_user_id
	 * @return void
	 */
	private function insert_employee( $db, $row, $wp_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;

		$dob = ! empty( $row['date_of_birth'] ) ? str_replace( '/', '-', $row['date_of_birth'] ) : null;

		$company_id = \OSQ\Database\DbManager::current_company_id();

		$wpdb->insert( $table, array(
			'company_id'      => $company_id,
			'wp_user_id'      => $wp_user_id,
			'employee_number' => sanitize_text_field( $row['employee_number'] ),
			'name'            => sanitize_text_field( $row['name'] ?? '' ),
			'email'           => sanitize_email( $row['email'] ?? '' ),
			'gender'          => ! empty( $row['gender'] ) ? absint( $row['gender'] ) : null,
			'date_of_birth'   => $dob,
			'organization_1'  => sanitize_text_field( $row['organization_1'] ?? '' ),
			'organization_2'  => sanitize_text_field( $row['organization_2'] ?? '' ),
			'organization_3'  => sanitize_text_field( $row['organization_3'] ?? '' ),
			'organization_4'  => sanitize_text_field( $row['organization_4'] ?? '' ),
			'organization_5'  => sanitize_text_field( $row['organization_5'] ?? '' ),
			'job_type'        => ! empty( $row['job_type'] ) ? absint( $row['job_type'] ) : null,
			'position'        => ! empty( $row['position'] ) ? absint( $row['position'] ) : null,
			'employment_type' => ! empty( $row['employment_type'] ) ? absint( $row['employment_type'] ) : null,
			'industry_type'   => ! empty( $row['industry_type'] ) ? absint( $row['industry_type'] ) : null,
			'hire_date'       => ! empty( $row['hire_date'] ) ? str_replace( '/', '-', $row['hire_date'] ) : null,
		) );

		// Tag the new WP user with the same company_id so future logins are tenant-correct.
		if ( $wp_user_id ) {
			update_user_meta( $wp_user_id, \OSQ\Database\DbManager::COMPANY_USER_META_KEY, $company_id );
		}
	}

	/**
	 * Update an existing employee with non-empty fields from the CSV row.
	 *
	 * @param \OSQ\Database\DbManager $db
	 * @param object                   $existing  Row from osq_employees.
	 * @param array                    $row       Mapped CSV row.
	 * @return void
	 */
	private function update_employee( $db, $existing, $row ) {
		global $wpdb;
		$table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$update = array( 'updated_at' => current_time( 'mysql' ) );

		$text_fields = array( 'name', 'email', 'organization_1', 'organization_2', 'organization_3', 'organization_4', 'organization_5' );
		foreach ( $text_fields as $field ) {
			if ( ! empty( $row[ $field ] ) ) {
				$update[ $field ] = 'email' === $field
					? sanitize_email( $row[ $field ] )
					: sanitize_text_field( $row[ $field ] );
			}
		}

		$int_fields = array( 'gender', 'job_type', 'position', 'employment_type', 'industry_type' );
		foreach ( $int_fields as $field ) {
			if ( isset( $row[ $field ] ) && '' !== $row[ $field ] ) {
				$update[ $field ] = absint( $row[ $field ] );
			}
		}

		if ( ! empty( $row['date_of_birth'] ) ) {
			$update['date_of_birth'] = str_replace( '/', '-', $row['date_of_birth'] );
		}
		if ( ! empty( $row['hire_date'] ) ) {
			$update['hire_date'] = str_replace( '/', '-', $row['hire_date'] );
		}

		$wpdb->update( $table, $update, array( 'employee_id' => $existing->employee_id ) );
	}
}
