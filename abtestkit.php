<?php
/**
 * Plugin Name:       abtestkit
 * Plugin URI:        https://www.abtestkit.io/
 * Description:       Increase WooCommerce Revenue with A/B Testing. Track Real Sales, Not Just Clicks.
 * Version:           1.5.1
 * Author:            abtestkit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.3
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Text Domain:       abtestkit
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the custom events table as a constant so PHPCS doesn't flag variables in SQL.
if ( ! defined( 'ABTESTKIT_EVENTS_TABLE' ) ) {
    global $wpdb;
    define( 'ABTESTKIT_EVENTS_TABLE', $wpdb->prefix . 'abtestkit_events' );
}


//ini_set('log_errors', 1);
//ini_set('error_log', __DIR__ . '/my-abtestkit-debug.log');

// ─────────────────────────────────────────────────────────────────────────────
// Telemetry (anonymous, opt-in, one-shot milestones) – DISABLED
// ─────────────────────────────────────────────────────────────────────────────
if ( ! defined( 'ABTESTKIT_TELEMETRY_ENDPOINT' ) ) {
    // Telemetry has been disabled – constant defined but empty
    define( 'ABTESTKIT_TELEMETRY_ENDPOINT', '' );
}

// ─────────────────────────────────────────────────────────────
// Email capture (post-first-launch) – DISABLED
// ─────────────────────────────────────────────────────────────
if ( ! defined( 'ABTESTKIT_EMAIL_CAPTURE_ENABLED' ) ) {
    define( 'ABTESTKIT_EMAIL_CAPTURE_ENABLED', false ); // telemetry/email capture disabled globally
}

// Defined (empty) so any code reading it doesn't break.
if ( ! defined( 'ABTESTKIT_EMAIL_APPS_SCRIPT' ) ) {
    define( 'ABTESTKIT_EMAIL_APPS_SCRIPT', '' );
}


// Re-use telemetry endpoint by default (can be a different Apps Script if you like)
if ( ! defined( 'ABTESTKIT_EMAIL_APPS_SCRIPT' ) ) {
    define( 'ABTESTKIT_EMAIL_APPS_SCRIPT', ABTESTKIT_TELEMETRY_ENDPOINT );
}

define( 'ABTESTKIT_TELEMETRY_OPTIN_OPTION',   'abtestkit_telemetry_opted_in' );
define( 'ABTESTKIT_TELEMETRY_FLAGS_OPTION',   'abtestkit_telemetry_flags' );
define( 'ABTESTKIT_TELEMETRY_INSTALL_OPTION', 'abtestkit_telemetry_installed_at' );

function abtestkit_get_telemetry_decision(): string {
    $value = get_option( ABTESTKIT_TELEMETRY_OPTIN_OPTION, '' );

    if ( $value === true || $value === 1 || $value === '1' || $value === 'yes' ) {
        return 'yes';
    }

    if ( $value === false || $value === 0 || $value === '0' || $value === 'no' ) {
        return 'no';
    }

    return '';
}

function abtestkit_is_telemetry_opted_in(): bool {
    return abtestkit_get_telemetry_decision() === 'yes';
}

function abtestkit_set_telemetry_optin( bool $yes ) {
    update_option( ABTESTKIT_TELEMETRY_OPTIN_OPTION, $yes ? 'yes' : 'no' );

    // Ensure heartbeat schedule exists (sender itself is opt-in gated).
    if ( function_exists( 'abtestkit_telemetry_schedule_heartbeat' ) ) {
        abtestkit_telemetry_schedule_heartbeat();
    }

    if ( ! $yes ) {
        return;
    }

    $flags = get_option( ABTESTKIT_TELEMETRY_FLAGS_OPTION, [] );
    $flags = is_array( $flags ) ? $flags : [];

    if ( empty( $flags['opted_in_sent'] ) ) {
        abtestkit_send_telemetry( 'telemetry_opted_in', [ 'value' => 1 ] );
        $flags['opted_in_sent'] = true;
    }

    if ( empty( $flags['installed_sent'] ) ) {
        abtestkit_send_telemetry( 'plugin_installed', [
            'installed_at' => (int) get_option( ABTESTKIT_TELEMETRY_INSTALL_OPTION, time() ),
        ] );
        $flags['installed_sent'] = true;
    }

    update_option( ABTESTKIT_TELEMETRY_FLAGS_OPTION, $flags );
}
function abtestkit_get_flags(): array {
    $f = get_option( ABTESTKIT_TELEMETRY_FLAGS_OPTION, [] );
    return is_array( $f ) ? $f : [];
}
function abtestkit_mark_flag( string $key ) {
    $f = abtestkit_get_flags();
    $f[ $key ] = true;
    update_option( ABTESTKIT_TELEMETRY_FLAGS_OPTION, $f );
}
function abtestkit_flag_is_set( string $key ): bool {
    $f = abtestkit_get_flags();
    return ! empty( $f[ $key ] );
}
function abtestkit_build_telemetry_base(): array {
    return [
        'plugin'   => 'abtestkit',
        'version'  => '1.5.1',
        'site'     => md5( home_url() ), // anonymous hash
        'wp'       => get_bloginfo( 'version' ),
        'php'      => PHP_VERSION,
        'env'      => ( wp_get_environment_type() ?: 'production' ),
    ];
}
/**
 * Telemetry sender (anonymous, opt-in).
 *
 * Set ABTESTKIT_TELEMETRY_ENDPOINT
 *   add_filter('abtestkit_telemetry_endpoint')
 */
if ( ! defined( 'ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK' ) ) {
    define( 'ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK', 'abtestkit_telemetry_heartbeat' );
}
if ( ! defined( 'ABTESTKIT_TELEMETRY_TESTS_CREATED_OPTION' ) ) {
    define( 'ABTESTKIT_TELEMETRY_TESTS_CREATED_OPTION', 'abtestkit_telemetry_tests_created' );
}

if ( ! defined( 'ABTESTKIT_REVIEW_PROMPT_OPTION' ) ) {
    define( 'ABTESTKIT_REVIEW_PROMPT_OPTION', 'abtestkit_review_prompt_state' );
}

if ( ! defined( 'ABTESTKIT_COMPATIBILITY_PROMPT_OPTION' ) ) {
    define( 'ABTESTKIT_COMPATIBILITY_PROMPT_OPTION', 'abtestkit_compatibility_prompt_state' );
}

if ( ! defined( 'ABTESTKIT_PREVIEW_HEALTH_OPTION' ) ) {
    define( 'ABTESTKIT_PREVIEW_HEALTH_OPTION', 'abtestkit_preview_health_failures' );
}

if ( ! defined( 'ABTESTKIT_HTML_RUNTIME_HEALTH_OPTION' ) ) {
    define( 'ABTESTKIT_HTML_RUNTIME_HEALTH_OPTION', 'abtestkit_html_runtime_health' );
}

if ( ! defined( 'ABTESTKIT_REVIEW_URL' ) ) {
    define( 'ABTESTKIT_REVIEW_URL', 'https://wordpress.org/support/plugin/abtestkit/reviews/' );
}

function abtestkit_review_prompt_get_state(): array {
    $stored = get_option( ABTESTKIT_REVIEW_PROMPT_OPTION, null );

    $default_tests_created = 0;
    if ( function_exists( 'abtestkit_pt_all' ) ) {
        $tests = abtestkit_pt_all();
        if ( is_array( $tests ) ) {
            $default_tests_created = count( $tests );
        }
    }

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    return wp_parse_args(
        $stored,
        [
            'tests_created' => $default_tests_created,
            'snooze_until'  => 0,
            'dismissed'     => 0,
        ]
    );
}

function abtestkit_review_prompt_save_state( array $state ): void {
    $clean = [
        'tests_created' => max( 0, (int) ( $state['tests_created'] ?? 0 ) ),
        'snooze_until'  => max( 0, (int) ( $state['snooze_until'] ?? 0 ) ),
        'dismissed'     => ! empty( $state['dismissed'] ) ? 1 : 0,
    ];

    update_option( ABTESTKIT_REVIEW_PROMPT_OPTION, $clean, false );
}

function abtestkit_review_prompt_note_test_created(): void {
    $state = abtestkit_review_prompt_get_state();
    $state['tests_created'] = max( 0, (int) $state['tests_created'] ) + 1;

    abtestkit_review_prompt_save_state( $state );
}

function abtestkit_compatibility_prompt_get_state(): array {
    $stored = get_option( ABTESTKIT_COMPATIBILITY_PROMPT_OPTION, [] );

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    return wp_parse_args(
        $stored,
        [
            'first_test_created' => 0,
            'snooze_until'       => 0,
            'dismissed'          => 0,
        ]
    );
}

function abtestkit_compatibility_prompt_save_state( array $state ): void {
    $clean = [
        'first_test_created' => max( 0, (int) ( $state['first_test_created'] ?? 0 ) ),
        'snooze_until'       => max( 0, (int) ( $state['snooze_until'] ?? 0 ) ),
        'dismissed'          => ! empty( $state['dismissed'] ) ? 1 : 0,
    ];

    update_option( ABTESTKIT_COMPATIBILITY_PROMPT_OPTION, $clean, false );
}

function abtestkit_preview_health_get_failures(): array {
    $stored = get_option( ABTESTKIT_PREVIEW_HEALTH_OPTION, [] );

    return is_array( $stored ) ? $stored : [];
}

function abtestkit_preview_health_record_failure( int $post_id, string $url, string $label = '', string $context = '' ): void {
    $post_id = absint( $post_id );
    $url     = esc_url_raw( trim( $url ) );

    if ( $post_id <= 0 && $url === '' ) {
        return;
    }

    $label   = substr( sanitize_text_field( $label ), 0, 120 );
    $context = sanitize_key( $context );
    $now     = time();
    $key     = md5( $post_id . '|' . $url . '|' . $context );

    $failures = abtestkit_preview_health_get_failures();

    $failures[ $key ] = [
        'post_id'         => $post_id,
        'url'             => $url,
        'label'           => $label,
        'context'         => $context,
        'last_failed_at'  => $now,
        'last_failed_gmt' => gmdate( 'Y-m-d H:i:s', $now ),
    ];

    uasort(
        $failures,
        static function( $a, $b ) {
            return (int) ( $b['last_failed_at'] ?? 0 ) <=> (int) ( $a['last_failed_at'] ?? 0 );
        }
    );

    $failures = array_slice( $failures, 0, 50, true );

    update_option( ABTESTKIT_PREVIEW_HEALTH_OPTION, $failures, false );
}

function abtestkit_preview_health_latest_for_test( array $test ): array {
    $ids = [];

    foreach ( [ 'control_id', 'variant_id' ] as $key ) {
        if ( ! empty( $test[ $key ] ) ) {
            $ids[] = (int) $test[ $key ];
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

    if ( empty( $ids ) ) {
        return [];
    }

    $cutoff   = time() - ( 7 * DAY_IN_SECONDS );
    $latest   = [];
    $failures = abtestkit_preview_health_get_failures();

    foreach ( $failures as $failure ) {
        if ( ! is_array( $failure ) ) {
            continue;
        }

        $post_id        = isset( $failure['post_id'] ) ? (int) $failure['post_id'] : 0;
        $last_failed_at = isset( $failure['last_failed_at'] ) ? (int) $failure['last_failed_at'] : 0;

        if ( ! in_array( $post_id, $ids, true ) || $last_failed_at < $cutoff ) {
            continue;
        }

        if ( empty( $latest ) || $last_failed_at > (int) ( $latest['last_failed_at'] ?? 0 ) ) {
            $latest = $failure;
        }
    }

    return is_array( $latest ) ? $latest : [];
}

function abtestkit_html_runtime_health_get_all(): array {
    $stored = get_option( ABTESTKIT_HTML_RUNTIME_HEALTH_OPTION, [] );

    return is_array( $stored ) ? $stored : [];
}

function abtestkit_html_runtime_health_get( string $test_id ): array {
    $test_id = abtestkit_sanitize_test_id( $test_id );

    if ( $test_id === '' ) {
        return [];
    }

    $stored = abtestkit_html_runtime_health_get_all();
    $record = isset( $stored[ $test_id ] ) && is_array( $stored[ $test_id ] )
        ? $stored[ $test_id ]
        : [];

    return $record;
}

function abtestkit_html_runtime_health_delete( string $test_id ): void {
    $test_id = abtestkit_sanitize_test_id( $test_id );

    if ( $test_id === '' ) {
        return;
    }

    $stored = abtestkit_html_runtime_health_get_all();

    if ( ! isset( $stored[ $test_id ] ) ) {
        return;
    }

    unset( $stored[ $test_id ] );
    update_option( ABTESTKIT_HTML_RUNTIME_HEALTH_OPTION, $stored, false );
}

function abtestkit_html_runtime_health_record( array $test, array $counts ): array {
    $test_id    = isset( $test['id'] ) ? abtestkit_sanitize_test_id( $test['id'] ) : '';
    $control_id = isset( $test['control_id'] ) ? absint( $test['control_id'] ) : 0;
    $changes    = isset( $test['html_changes'] )
        ? abtestkit_sanitize_custom_html_changes( $test['html_changes'] )
        : [];
    $total      = count( $changes );

    if ( $test_id === '' || $control_id <= 0 || $total <= 0 ) {
        return [];
    }

    $matched = isset( $counts['matched'] ) ? max( 0, (int) $counts['matched'] ) : 0;
    $missing = isset( $counts['missing'] ) ? max( 0, (int) $counts['missing'] ) : 0;
    $invalid = isset( $counts['invalid'] ) ? max( 0, (int) $counts['invalid'] ) : 0;

    if ( $matched + $missing + $invalid !== $total ) {
        return [];
    }

    $stored   = abtestkit_html_runtime_health_get_all();
    $previous = isset( $stored[ $test_id ] ) && is_array( $stored[ $test_id ] )
        ? $stored[ $test_id ]
        : [];
    $now      = time();

    $invalid_streak = $invalid > 0
        ? max( 0, (int) ( $previous['invalid_streak'] ?? 0 ) ) + 1
        : 0;

    $status = 'good';
    if ( $invalid_streak >= 3 ) {
        $status = 'broken';
    } elseif ( $missing > 0 || $invalid > 0 ) {
        $status = 'attention';
    }

    $record = [
        'test_id'            => $test_id,
        'control_id'         => $control_id,
        'status'             => $status,
        'matched'            => $matched,
        'missing'            => $missing,
        'invalid'            => $invalid,
        'total'              => $total,
        'reports'            => max( 0, (int) ( $previous['reports'] ?? 0 ) ) + 1,
        'invalid_streak'     => $invalid_streak,
        'last_reported_at'   => $now,
        'last_reported_gmt'  => gmdate( 'Y-m-d H:i:s', $now ),
    ];

    $stored[ $test_id ] = $record;

    uasort(
        $stored,
        static function( $a, $b ) {
            return (int) ( $b['last_reported_at'] ?? 0 ) <=> (int) ( $a['last_reported_at'] ?? 0 );
        }
    );

    $stored = array_slice( $stored, 0, 100, true );
    update_option( ABTESTKIT_HTML_RUNTIME_HEALTH_OPTION, $stored, false );

    return $record;
}

function abtestkit_compatibility_prompt_note_first_test_created(): void {
    if ( abtestkit_get_telemetry_decision() !== '' ) {
        return;
    }

    $state = abtestkit_compatibility_prompt_get_state();

    if ( empty( $state['first_test_created'] ) ) {
        $state['first_test_created'] = time();
        abtestkit_compatibility_prompt_save_state( $state );
    }
}

function abtestkit_compatibility_prompt_should_show(): bool {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    if ( abtestkit_get_telemetry_decision() !== '' ) {
        return false;
    }

    $state = abtestkit_compatibility_prompt_get_state();

    if ( ! empty( $state['dismissed'] ) ) {
        return false;
    }

    if ( empty( $state['first_test_created'] ) ) {
        return false;
    }

    $snooze_until = isset( $state['snooze_until'] ) ? (int) $state['snooze_until'] : 0;
    if ( $snooze_until > time() ) {
        return false;
    }

    return true;
}

function abtestkit_compatibility_prompt_is_target_screen(): bool {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen ) {
        return false;
    }

    return in_array(
        (string) $screen->id,
        [
            'toplevel_page_abtestkit-dashboard',
            'abtestkit-dashboard_page_abtestkit-dashboard',
            'abtestkit_page_abtestkit-dashboard',
            'admin_page_abtestkit-dashboard',
            'abtestkit_page_abtestkit-test-performance',
            'admin_page_abtestkit-test-performance',
        ],
        true
    );
}

function abtestkit_admin_return_url_from_request(): string {
    $dashboard_url = admin_url( 'admin.php?page=abtestkit-dashboard' );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only current admin screen routing.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( $page === 'abtestkit-test-performance' ) {
        $url = admin_url( 'admin.php?page=abtestkit-test-performance' );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only return URL preservation.
        if ( isset( $_GET['test_id'] ) ) {
            $request_test_id = sanitize_text_field( wp_unslash( $_GET['test_id'] ) );

            if ( $request_test_id !== '' ) {
                $url = add_query_arg(
                    'test_id',
                    $request_test_id,
                    $url
                );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return $url;
    }

    if ( $page === 'abtestkit-dashboard' ) {
        return $dashboard_url;
    }

    return $dashboard_url;
}

function abtestkit_admin_get_safe_return_url( string $fallback = '' ): string {
    $fallback = $fallback !== '' ? $fallback : admin_url( 'admin.php?page=abtestkit-dashboard' );
    $redirect = '';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- admin-post callers verify their nonce before this helper runs; value is validated with wp_validate_redirect before use.
    if ( isset( $_REQUEST['return_url'] ) && $_REQUEST['return_url'] !== '' ) {
        $redirect = esc_url_raw( wp_unslash( $_REQUEST['return_url'] ) );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $redirect === '' ) {
        $redirect = wp_get_referer() ?: '';
    }

    $redirect = wp_validate_redirect( $redirect, $fallback );

    return remove_query_arg(
        [ 'action', 'do', 'v', '_wpnonce', 'return_url' ],
        $redirect
    );
}

function abtestkit_review_prompt_should_show(): bool {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    $state = abtestkit_review_prompt_get_state();

    if ( ! empty( $state['dismissed'] ) ) {
        return false;
    }

    if ( (int) $state['tests_created'] < 2 ) {
        return false;
    }

    $snooze_until = isset( $state['snooze_until'] ) ? (int) $state['snooze_until'] : 0;
    if ( $snooze_until > time() ) {
        return false;
    }

    return true;
}

function abtestkit_review_prompt_is_dashboard_screen(): bool {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen ) {
        return false;
    }

    return in_array(
        (string) $screen->id,
        [
            'toplevel_page_abtestkit-dashboard',
            'abtestkit-dashboard_page_abtestkit-dashboard',
        ],
        true
    );
}

function abtestkit_telemetry_endpoint(): string {
    // If the constant is defined but empty (your current default), fall back.
    $endpoint = defined( 'ABTESTKIT_TELEMETRY_ENDPOINT' ) ? (string) ABTESTKIT_TELEMETRY_ENDPOINT : '';
    $endpoint = trim( $endpoint );

    if ( $endpoint === '' ) {
        // Default collector
        $endpoint = 'https://www.abtestkit.io/wp-json/abtestkit-telemetry/v1/collect';
    }

    $endpoint = (string) apply_filters( 'abtestkit_telemetry_endpoint', $endpoint );
    $endpoint = esc_url_raw( trim( $endpoint ) );

    return $endpoint;
}

/**
 * Internal sender:
 * - $force=false => normal telemetry (requires full opt-in)
 * - $force=true  => explicit user-action events only (e.g. deactivate feedback)
 */
function abtestkit_send_telemetry_raw(
    string $event,
    array $data = [],
    bool $force = false,
    bool $blocking = false,
    int $timeout = 2
): void {

    // Normal telemetry is hard opt-in gated.
    if ( ! $force && ! abtestkit_is_telemetry_opted_in() ) {
        return;
    }

    $endpoint = abtestkit_telemetry_endpoint();
    if ( $endpoint === '' ) {
        return;
    }

    $event = sanitize_key( $event );

    // Keep payload small + anonymous.
    $payload = array_merge(
        abtestkit_build_telemetry_base(),
        [
            'event' => $event,
            't'     => time(),
            'data'  => is_array( $data ) ? $data : [],
        ]
    );

    $args = [
        'timeout'  => max( 1, (int) $timeout ),
        'blocking' => (bool) $blocking,
        'headers'  => [
            'Content-Type' => 'application/json; charset=utf-8',
        ],
        'body'     => wp_json_encode( $payload ),
    ];

    // Best-effort; never break UX.
    try {
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_post_wp_remote_post
        wp_remote_post( $endpoint, $args );
    } catch ( \Throwable $e ) {
        // no-op
    }
}

/**
 * Public sender: full telemetry only (opt-in gated).
 */
function abtestkit_send_telemetry( string $event, array $data = [] ) {
    abtestkit_send_telemetry_raw( $event, $data, false, false, 2 );
}

/**
 * Forced sender: ONLY for explicit user actions where submitting implies opt-in for that one event.
 * Keep this whitelist tight.
 */
function abtestkit_send_telemetry_forced( string $event, array $data = [], bool $blocking = true ): void {
    $event = sanitize_key( $event );

    // Tight allow-list to prevent accidental “forced” telemetry expansion.
    $allowed = [ 'plugin_deactivated', 'compatibility_help_request' ];
    if ( ! in_array( $event, $allowed, true ) ) {
        return;
    }

    // For deactivation feedback, use blocking=true so it actually leaves the site
    // before the user completes deactivation.
    abtestkit_send_telemetry_raw( $event, $data, true, $blocking, 3 );
}

/**
 * Heartbeat: scheduled daily via WP-Cron.
 * (Schedule exists regardless; sender is opt-in gated.)
 */
function abtestkit_telemetry_schedule_heartbeat(): void {
    if ( ! wp_next_scheduled( ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK );
    }
}

function abtestkit_telemetry_unschedule_heartbeat(): void {
    $ts = wp_next_scheduled( ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK );
    while ( $ts ) {
        wp_unschedule_event( $ts, ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK );
        $ts = wp_next_scheduled( ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK );
    }
}

add_action( ABTESTKIT_TELEMETRY_HEARTBEAT_HOOK, function() {
    if ( ! abtestkit_is_telemetry_opted_in() ) return;

    $tests_total   = 0;
    $tests_running = 0;
    $all = abtestkit_pt_all();

    if ( is_array( $all ) ) {
        $tests_total = count( $all );
        foreach ( $all as $t ) {
            if ( is_array( $t ) && ( $t['status'] ?? '' ) === 'running' ) {
                $tests_running++;
            }
        }
    }

    abtestkit_send_telemetry( 'heartbeat', [
        'tests_total'   => (int) $tests_total,
        'tests_running' => (int) $tests_running,
        'tests_created' => (int) get_option( ABTESTKIT_TELEMETRY_TESTS_CREATED_OPTION, 0 ),
    ] );
} );

/**
 * Privacy-safe hashing helpers (never send raw selectors/URLs).
 */
function abtestkit_telemetry_hash_targets( array $targets ): array {
    $out = [];
    foreach ( $targets as $t ) {
        $t = trim( (string) $t );
        if ( $t === '' ) continue;
        $out[] = substr( md5( $t ), 0, 12 );
        if ( count( $out ) >= 5 ) break;
    }
    return $out;
}

function abtestkit_telemetry_guess_target_kind( array $targets ): string {
    foreach ( $targets as $t ) {
        $t = ltrim( (string) $t );
        if ( $t === '' ) continue;
        if ( strpos( $t, '://' ) !== false ) return 'url';
        if ( preg_match( '/^[.#\\[]/', $t ) ) return 'selector';
        if ( preg_match( '/\\s|>/', $t ) ) return 'selector';
    }
    return '';
}

function abtestkit_telemetry_tests_created_count(): int {
    return (int) get_option( ABTESTKIT_TELEMETRY_TESTS_CREATED_OPTION, 0 );
}

function abtestkit_telemetry_inc_tests_created(): int {
    $n = abtestkit_telemetry_tests_created_count();
    $n++;
    update_option( ABTESTKIT_TELEMETRY_TESTS_CREATED_OPTION, $n, false );
    return $n;
}

function abtestkit_telemetry_send_once( string $flag_key, string $event, array $data = [] ): void {
    if ( abtestkit_flag_is_set( $flag_key ) ) return;
    abtestkit_send_telemetry( $event, $data );
    abtestkit_mark_flag( $flag_key );
}

/**
 * Build a small, anonymous technical snapshot for deactivation feedback.
 * No URLs, site name, user details, order details, post titles or content are sent.
 */
function abtestkit_telemetry_detect_plugin_state( array $plugin_files ): array {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : [];
    $state       = [
        'installed'      => 0,
        'active'         => 0,
        'network_active' => 0,
        'version'        => '',
    ];

    foreach ( $plugin_files as $plugin_file ) {
        $plugin_file = plugin_basename( (string) $plugin_file );

        if ( isset( $all_plugins[ $plugin_file ] ) ) {
            $state['installed'] = 1;

            if ( empty( $state['version'] ) && ! empty( $all_plugins[ $plugin_file ]['Version'] ) ) {
                $state['version'] = sanitize_text_field( (string) $all_plugins[ $plugin_file ]['Version'] );
            }
        }

        if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
            $state['active'] = 1;
        }

        if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_file ) ) {
            $state['active']         = 1;
            $state['network_active'] = 1;
        }
    }

    return $state;
}

function abtestkit_telemetry_count_post_type( string $post_type ): array {
    if ( ! post_type_exists( $post_type ) ) {
        return [
            'publish' => 0,
            'draft'   => 0,
            'private' => 0,
            'total'   => 0,
        ];
    }

    $counts  = wp_count_posts( $post_type );
    $publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
    $draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
    $private = isset( $counts->private ) ? (int) $counts->private : 0;
    $total   = 0;

    foreach ( (array) $counts as $count ) {
        $total += (int) $count;
    }

    return [
        'publish' => $publish,
        'draft'   => $draft,
        'private' => $private,
        'total'   => $total,
    ];
}

function abtestkit_telemetry_theme_family( string $stylesheet, string $template, string $name ): string {
    $haystack = strtolower( $stylesheet . ' ' . $template . ' ' . $name );

    $families = [
        'hello-elementor' => 'hello_elementor',
        'astra'           => 'astra',
        'generatepress'   => 'generatepress',
        'kadence'         => 'kadence',
        'blocksy'         => 'blocksy',
        'oceanwp'         => 'oceanwp',
        'storefront'      => 'storefront',
        'flatsome'        => 'flatsome',
        'woodmart'        => 'woodmart',
        'divi'            => 'divi',
        'bricks'          => 'bricks',
        'avada'           => 'avada',
        'betheme'         => 'betheme',
        'enfold'          => 'enfold',
    ];

    foreach ( $families as $needle => $family ) {
        if ( strpos( $haystack, $needle ) !== false ) {
            return $family;
        }
    }

    return 'other_or_custom';
}

/**
 * Return broad target categories without transmitting selectors or URLs.
 */
function abtestkit_telemetry_target_types( array $targets ): array {
    $types = [];

    foreach ( $targets as $target ) {
        $target = trim( (string) $target );

        if ( $target === '' ) {
            continue;
        }

        if ( preg_match( '#^https?://#i', $target ) || strpos( $target, '/' ) === 0 || strpos( $target, '?' ) === 0 ) {
            $types[] = 'url';
        } elseif ( strpos( $target, '#' ) === 0 ) {
            $types[] = 'id_selector';
        } elseif ( strpos( $target, '.' ) !== false ) {
            $types[] = 'class_selector';
        } else {
            $types[] = 'selector';
        }
    }

    return array_values( array_unique( $types ) );
}

function abtestkit_telemetry_product_type( int $post_id ): string {
    if ( $post_id <= 0 || get_post_type( $post_id ) !== 'product' || ! taxonomy_exists( 'product_type' ) ) {
        return '';
    }

    $types = wp_get_object_terms(
        $post_id,
        'product_type',
        [ 'fields' => 'slugs' ]
    );

    if ( is_wp_error( $types ) || empty( $types ) ) {
        return '';
    }

    return sanitize_key( (string) reset( $types ) );
}

/**
 * Summarise saved tests for an explicitly submitted deactivation report.
 *
 * Deliberately omit titles, post IDs, selectors, URLs, custom code/content,
 * event rows, order/customer data and exact timestamps.
 */
function abtestkit_build_deactivation_tests_snapshot(): array {
    if ( ! function_exists( 'abtestkit_pt_all' ) ) {
        return [
            'included' => 0,
            'omitted'  => 0,
            'items'    => [],
        ];
    }

    $tests = array_values(
        array_filter(
            abtestkit_pt_all(),
            static function( $test ): bool {
                return is_array( $test );
            }
        )
    );

    $total = count( $tests );
    $tests = array_slice( array_reverse( $tests ), 0, 25 );
    $items = [];
    $now   = time();

    foreach ( $tests as $test ) {
        $test_id    = isset( $test['id'] ) ? (string) $test['id'] : '';
        $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
        $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        $targets    = ! empty( $test['links'] ) && is_array( $test['links'] )
            ? array_values( $test['links'] )
            : [];

        $kind   = isset( $test['kind'] ) ? sanitize_key( (string) $test['kind'] ) : '';
        $status = isset( $test['status'] ) ? sanitize_key( (string) $test['status'] ) : '';

        $version_b_mode = '';
        if ( in_array( $kind, [ 'custom_css', 'custom_html' ], true ) ) {
            $version_b_mode = $kind;
        } elseif ( $variant_id > 0 ) {
            $is_owned_shadow = (
                function_exists( 'abtestkit_is_shadow_product' )
                && abtestkit_is_shadow_product( $variant_id )
            ) || (
                function_exists( 'abtestkit_is_shadow_variant' )
                && abtestkit_is_shadow_variant( $variant_id )
            );
            $version_b_mode = $is_owned_shadow ? 'abtestkit_shadow' : 'existing_content';
        }

        $html_operations = [];
        foreach ( (array) ( $test['html_changes'] ?? [] ) as $change ) {
            if ( ! is_array( $change ) ) {
                continue;
            }

            $operation = sanitize_key( (string) ( $change['operation'] ?? '' ) );
            if ( in_array( $operation, [ 'replace_contents', 'insert_before', 'insert_after', 'prepend_inside', 'append_inside' ], true ) ) {
                $html_operations[] = $operation;
            }
        }

        $started_at  = max( 0, (int) ( $test['started_at'] ?? 0 ) );
        $finished_at = max( 0, (int) ( $test['finished_at'] ?? 0 ) );

        $items[] = [
            'ref'                    => $test_id !== '' ? substr( hash_hmac( 'sha256', $test_id, wp_salt( 'auth' ) ), 0, 12 ) : '',
            'kind'                   => $kind,
            'status'                 => $status,
            'goal'                   => isset( $test['goal'] ) ? sanitize_key( (string) $test['goal'] ) : '',
            'decision_mode'          => isset( $test['decision_mode'] ) ? sanitize_key( (string) $test['decision_mode'] ) : '',
            'decision_rule'          => isset( $test['decision_rule'] ) ? sanitize_key( (string) $test['decision_rule'] ) : '',
            'split'                  => max( 0, min( 100, (int) ( $test['split'] ?? 50 ) ) ),
            'min_impressions'        => max( 0, (int) ( $test['min_impressions'] ?? 0 ) ),
            'min_conversions'        => max( 0, (int) ( $test['min_conversions'] ?? 0 ) ),
            'control_type'           => $control_id > 0 ? sanitize_key( (string) get_post_type( $control_id ) ) : '',
            'control_product_type'   => abtestkit_telemetry_product_type( $control_id ),
            'control_exists'         => $control_id > 0 && get_post( $control_id ) ? 1 : 0,
            'variant_type'           => $variant_id > 0 ? sanitize_key( (string) get_post_type( $variant_id ) ) : '',
            'variant_product_type'   => abtestkit_telemetry_product_type( $variant_id ),
            'variant_exists'         => $variant_id > 0 && get_post( $variant_id ) ? 1 : 0,
            'version_b_mode'         => $version_b_mode,
            'target_count'           => count( $targets ),
            'target_types'           => abtestkit_telemetry_target_types( $targets ),
            'scroll_depth'           => isset( $test['scroll_depth'] ) ? max( 0, min( 100, (int) $test['scroll_depth'] ) ) : 0,
            'css_scope'              => isset( $test['css_scope'] ) ? sanitize_key( (string) $test['css_scope'] ) : '',
            'custom_css_length'      => isset( $test['custom_css'] ) ? strlen( (string) $test['custom_css'] ) : 0,
            'css_marker_count'       => isset( $test['css_markers'] ) && is_array( $test['css_markers'] ) ? count( $test['css_markers'] ) : 0,
            'html_scope'             => isset( $test['html_scope'] ) ? sanitize_key( (string) $test['html_scope'] ) : '',
            'html_change_count'      => isset( $test['html_changes'] ) && is_array( $test['html_changes'] ) ? count( $test['html_changes'] ) : 0,
            'html_operation_types'   => array_values( array_unique( $html_operations ) ),
            'started'                => $started_at > 0 ? 1 : 0,
            'started_age_days'       => $started_at > 0 ? min( 36500, (int) floor( max( 0, $now - $started_at ) / DAY_IN_SECONDS ) ) : 0,
            'finished'               => $finished_at > 0 ? 1 : 0,
            'finished_age_days'      => $finished_at > 0 ? min( 36500, (int) floor( max( 0, $now - $finished_at ) / DAY_IN_SECONDS ) ) : 0,
            'auto_paused_broken'     => ! empty( $test['auto_paused_broken'] ) ? 1 : 0,
            'has_final_stats'        => ! empty( $test['final_stats'] ) && is_array( $test['final_stats'] ) ? 1 : 0,
            'has_completed_snapshot' => ! empty( $test['snapshot_versions'] ) && is_array( $test['snapshot_versions'] ) ? 1 : 0,
        ];
    }

    return [
        'included' => count( $items ),
        'omitted'  => max( 0, $total - count( $items ) ),
        'items'    => $items,
    ];
}

/**
 * Accept only non-content wizard progress fields supplied by the admin browser.
 */
function abtestkit_sanitize_deactivation_wizard_snapshot( $snapshot ): array {
    if ( ! is_array( $snapshot ) ) {
        return [];
    }

    $out = [];

    $allowed_strings = [
        'ui'               => [ 'pt-wizard-1.2.0' ],
        'step'             => [ 'select_type', 'select_custom_code_type', 'select_control', 'version_b_source', 'review_versions', 'choose_conversion_type', 'set_destination_url', 'set_scroll_depth', 'select_click_targets', 'summary' ],
        'furthest_step'    => [ 'select_type', 'select_custom_code_type', 'select_control', 'version_b_source', 'review_versions', 'choose_conversion_type', 'set_destination_url', 'set_scroll_depth', 'select_click_targets', 'summary' ],
        'kind'             => [ 'page', 'post', 'product', 'reusable_section', 'custom_code' ],
        'custom_code_type' => [ 'custom_css', 'custom_html' ],
        'scope'            => [ 'page', 'post', 'product' ],
        'b_mode'           => [ 'duplicate', 'existing', 'custom_css', 'custom_html' ],
        'goal'             => [ 'clicks', 'form', 'add_to_cart', 'purchase', 'destination_url', 'scroll_depth' ],
        'click_scope'      => [ 'on_test_pages', 'other_page' ],
        'decision_mode'    => [ 'auto', 'manual' ],
        'decision_rule'    => [ 'fast', 'balanced', 'precise', 'manual' ],
        'result'           => [ 'completed', 'abandoned' ],
    ];

    foreach ( $allowed_strings as $key => $allowed_values ) {
        $value = $key === 'ui'
            ? sanitize_text_field( (string) ( $snapshot[ $key ] ?? '' ) )
            : sanitize_key( (string) ( $snapshot[ $key ] ?? '' ) );
        if ( in_array( $value, $allowed_values, true ) ) {
            $out[ $key ] = $value;
        }
    }

    $bounded_ints = [
        'step_index'          => [ 0, 50 ],
        'furthest_step_index' => [ 0, 50 ],
        'ms'                  => [ 0, DAY_IN_SECONDS * 1000 ],
        'age_seconds'         => [ 0, YEAR_IN_SECONDS ],
        'links_count'         => [ 0, 100 ],
        'scroll_depth'        => [ 0, 100 ],
        'custom_css_length'   => [ 0, 1000000 ],
        'css_marker_count'    => [ 0, 1000 ],
        'html_change_count'   => [ 0, 1000 ],
    ];

    foreach ( $bounded_ints as $key => $bounds ) {
        if ( ! array_key_exists( $key, $snapshot ) || ! is_numeric( $snapshot[ $key ] ) ) {
            continue;
        }

        $out[ $key ] = max( $bounds[0], min( $bounds[1], (int) $snapshot[ $key ] ) );
    }

    $boolean_fields = [
        'completed',
        'has_control',
        'has_variant',
        'has_temp_variant',
        'edited_variant',
        'seo_safe_existing_b',
        'conversion_chosen',
        'has_error',
        'product_title_changed',
        'product_price_changed',
        'product_sale_price_changed',
        'product_short_description_changed',
        'product_long_description_changed',
        'product_image_changed',
        'product_gallery_changed',
    ];

    foreach ( $boolean_fields as $key ) {
        if ( array_key_exists( $key, $snapshot ) ) {
            $out[ $key ] = in_array( $snapshot[ $key ], [ 1, '1', true ], true ) ? 1 : 0;
        }
    }

    return $out;
}

function abtestkit_build_deactivation_snapshot( array $wizard_snapshot = [], bool $include_test_state = true ): array {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins            = function_exists( 'get_plugins' ) ? get_plugins() : [];
    $active_plugins         = (array) get_option( 'active_plugins', [] );
    $network_active_plugins = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) : [];
    $active_plugins         = array_values( array_unique( array_merge( $active_plugins, $network_active_plugins ) ) );

    $theme      = wp_get_theme();
    $stylesheet = sanitize_key( (string) $theme->get_stylesheet() );
    $template   = sanitize_key( (string) $theme->get_template() );
    $theme_name = sanitize_text_field( (string) $theme->get( 'Name' ) );

    $builders = [
        'elementor'        => abtestkit_telemetry_detect_plugin_state( [ 'elementor/elementor.php' ] ),
        'elementor_pro'    => abtestkit_telemetry_detect_plugin_state( [ 'elementor-pro/elementor-pro.php' ] ),
        'beaver_builder'   => abtestkit_telemetry_detect_plugin_state( [ 'bb-plugin/fl-builder.php' ] ),
        'divi_builder'     => abtestkit_telemetry_detect_plugin_state( [ 'divi-builder/divi-builder.php' ] ),
        'oxygen'           => abtestkit_telemetry_detect_plugin_state( [ 'oxygen/functions.php' ] ),
        'brizy'            => abtestkit_telemetry_detect_plugin_state( [ 'brizy/brizy.php' ] ),
        'breakdance'       => abtestkit_telemetry_detect_plugin_state( [ 'breakdance/plugin.php' ] ),
        'wpbakery'         => abtestkit_telemetry_detect_plugin_state( [ 'js_composer/js_composer.php' ] ),
        'visual_composer'  => abtestkit_telemetry_detect_plugin_state( [ 'visualcomposer/plugin-wordpress.php', 'visualcomposer/visualcomposer.php' ] ),
        'siteorigin'       => abtestkit_telemetry_detect_plugin_state( [ 'siteorigin-panels/siteorigin-panels.php' ] ),
        'thrive_architect' => abtestkit_telemetry_detect_plugin_state( [ 'thrive-visual-editor/thrive-visual-editor.php' ] ),
        'avada_builder'    => abtestkit_telemetry_detect_plugin_state( [ 'fusion-builder/fusion-builder.php' ] ),
        'kadence_blocks'   => abtestkit_telemetry_detect_plugin_state( [ 'kadence-blocks/kadence-blocks.php' ] ),
        'spectra'          => abtestkit_telemetry_detect_plugin_state( [ 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php' ] ),
        'generateblocks'   => abtestkit_telemetry_detect_plugin_state( [ 'generateblocks/plugin.php' ] ),
        'stackable'        => abtestkit_telemetry_detect_plugin_state( [ 'stackable-ultimate-gutenberg-blocks/plugin.php' ] ),
    ];

    // Bricks is commonly a theme, not a plugin.
    $builders['bricks_theme'] = [
        'installed'      => ( strpos( $stylesheet . ' ' . $template, 'bricks' ) !== false ) ? 1 : 0,
        'active'         => ( strpos( $stylesheet . ' ' . $template, 'bricks' ) !== false ) ? 1 : 0,
        'network_active' => 0,
        'version'        => '',
    ];

    $tests_total   = 0;
    $tests_running = 0;

    if ( function_exists( 'abtestkit_pt_all' ) ) {
        $tests = abtestkit_pt_all();
        if ( is_array( $tests ) ) {
            $tests_total = count( $tests );
            foreach ( $tests as $test ) {
                if ( is_array( $test ) && ( $test['status'] ?? '' ) === 'running' ) {
                    $tests_running++;
                }
            }
        }
    }

    $tests_created = $tests_total;
    if ( function_exists( 'abtestkit_review_prompt_get_state' ) ) {
        $review_prompt_state = abtestkit_review_prompt_get_state();
        $tests_created       = max(
            $tests_created,
            (int) ( $review_prompt_state['tests_created'] ?? 0 )
        );
    }

    $snapshot = [
        'snapshot_version'     => $include_test_state ? 2 : 1,
        'wp_version'           => get_bloginfo( 'version' ),
        'php_version'          => PHP_VERSION,
        'environment'          => ( wp_get_environment_type() ?: 'production' ),
        'is_multisite'         => is_multisite() ? 1 : 0,
        'site_language'        => sanitize_text_field( (string) get_locale() ),
        'memory_limit'         => sanitize_text_field( (string) WP_MEMORY_LIMIT ),
        'wp_debug'             => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 1 : 0,
        'theme'                => [
            'name'        => $theme_name,
            'stylesheet'  => $stylesheet,
            'template'    => $template,
            'family'      => abtestkit_telemetry_theme_family( $stylesheet, $template, $theme_name ),
            'version'     => sanitize_text_field( (string) $theme->get( 'Version' ) ),
            'is_child'    => ( $stylesheet !== $template ) ? 1 : 0,
        ],
        'plugins'              => [
            'installed_count' => count( $all_plugins ),
            'active_count'    => count( $active_plugins ),
            'woocommerce'     => abtestkit_telemetry_detect_plugin_state( [ 'woocommerce/woocommerce.php' ] ),
            'subscriptions'   => abtestkit_telemetry_detect_plugin_state( [ 'woocommerce-subscriptions/woocommerce-subscriptions.php' ] ),
            'acf'             => abtestkit_telemetry_detect_plugin_state( [ 'advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php' ] ),
        ],
        'builders'             => $builders,
        'content_counts'       => [
            'page'              => abtestkit_telemetry_count_post_type( 'page' ),
            'post'              => abtestkit_telemetry_count_post_type( 'post' ),
            'product'           => abtestkit_telemetry_count_post_type( 'product' ),
            'product_variation' => abtestkit_telemetry_count_post_type( 'product_variation' ),
        ],
        'abtestkit_usage'      => [
            'tests_total'   => (int) $tests_total,
            'tests_running' => (int) $tests_running,
            'tests_created' => (int) $tests_created,
        ],
    ];

    if ( $include_test_state ) {
        $snapshot['tests']  = abtestkit_build_deactivation_tests_snapshot();
        $snapshot['wizard'] = abtestkit_sanitize_deactivation_wizard_snapshot( $wizard_snapshot );
    }

    return $snapshot;
}

/**
 * Event: plugin deactivated feedback from plugins.php.
 * This is NOT full telemetry opt-in.
 * We only send when the user explicitly submits feedback (not skip/empty).
 */
function abtestkit_telemetry_track_plugin_delete_reason( string $reason, string $detail = '', string $detail_tag = '', string $area = '', array $wizard_snapshot = [] ): void {
    $reason     = sanitize_key( (string) $reason );
    $detail     = substr( sanitize_textarea_field( (string) $detail ), 0, 1000 );
    $detail_tag = sanitize_key( (string) $detail_tag );
    $area       = sanitize_key( (string) $area );

    // Only treat "Send feedback" as opt-in for this one event.
    if ( $reason === '' || $reason === 'skip' ) {
        return;
    }

    abtestkit_send_telemetry_forced(
        'plugin_deactivated',
        [
            'kind'       => 'deactivate',
            'reason'     => $reason,
            'detail_tag' => $detail_tag,
            'area'       => $area,
            'detail'     => $detail,
            'snapshot'   => abtestkit_build_deactivation_snapshot( $wizard_snapshot ),
        ],
        true
    );
}

function abtestkit_build_compatibility_help_snapshot(): array {
    $snapshot = function_exists( 'abtestkit_build_deactivation_snapshot' )
        ? abtestkit_build_deactivation_snapshot( [], false )
        : [];

    $snapshot['cache_plugins'] = [
        'litespeed_cache'  => abtestkit_telemetry_detect_plugin_state( [ 'litespeed-cache/litespeed-cache.php' ] ),
        'wp_rocket'        => abtestkit_telemetry_detect_plugin_state( [ 'wp-rocket/wp-rocket.php' ] ),
        'w3_total_cache'   => abtestkit_telemetry_detect_plugin_state( [ 'w3-total-cache/w3-total-cache.php' ] ),
        'wp_super_cache'   => abtestkit_telemetry_detect_plugin_state( [ 'wp-super-cache/wp-cache.php' ] ),
        'autoptimize'      => abtestkit_telemetry_detect_plugin_state( [ 'autoptimize/autoptimize.php' ] ),
        'perfmatters'      => abtestkit_telemetry_detect_plugin_state( [ 'perfmatters/perfmatters.php' ] ),
        'sg_optimizer'     => abtestkit_telemetry_detect_plugin_state( [ 'sg-cachepress/sg-cachepress.php' ] ),
        'breeze'           => abtestkit_telemetry_detect_plugin_state( [ 'breeze/breeze.php' ] ),
        'cache_enabler'    => abtestkit_telemetry_detect_plugin_state( [ 'cache-enabler/cache-enabler.php' ] ),
        'wp_fastest_cache' => abtestkit_telemetry_detect_plugin_state( [ 'wp-fastest-cache/wpFastestCache.php' ] ),
        'nitropack'        => abtestkit_telemetry_detect_plugin_state( [ 'nitropack/main.php' ] ),
    ];

    return $snapshot;
}

function abtestkit_telemetry_track_compatibility_help_request( string $test_id, string $message = '', string $source = 'performance_page' ): bool {
    $test_id = sanitize_text_field( (string) $test_id );
    $message = substr( sanitize_textarea_field( (string) $message ), 0, 1500 );
    $source  = sanitize_key( (string) $source );

    if ( $source === '' ) {
        $source = 'performance_page';
    }

    if ( $test_id === '' || ! function_exists( 'abtestkit_pt_get' ) ) {
        return false;
    }

    $test = abtestkit_pt_get( $test_id );

    if ( ! is_array( $test ) ) {
        return false;
    }

    $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
    $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
    $targets    = function_exists( 'abtestkit_pt_click_targets_for_test' ) ? abtestkit_pt_click_targets_for_test( $test ) : [];
    $stats      = function_exists( 'abtestkit_pt_stats_for_snapshot' ) ? abtestkit_pt_stats_for_snapshot( $test_id ) : [];

    $target_types = [];
    foreach ( (array) $targets as $target ) {
        $target = (string) $target;

        if ( preg_match( '#^https?://#i', $target ) || strpos( $target, '/' ) === 0 || strpos( $target, '?' ) === 0 ) {
            $target_types[] = 'url';
        } elseif ( strpos( $target, '#' ) === 0 ) {
            $target_types[] = 'id_selector';
        } elseif ( strpos( $target, '.' ) !== false ) {
            $target_types[] = 'class_selector';
        } else {
            $target_types[] = 'selector';
        }
    }

    $target_types = array_values( array_unique( $target_types ) );

    abtestkit_send_telemetry_forced(
        'compatibility_help_request',
        [
            'kind'    => 'compatibility_help',
            'source'  => $source,
            'message' => $message,
            'test'    => [
                'id'                 => $test_id,
                'kind'               => isset( $test['kind'] ) ? sanitize_key( (string) $test['kind'] ) : '',
                'status'             => isset( $test['status'] ) ? sanitize_key( (string) $test['status'] ) : '',
                'goal'               => isset( $test['goal'] ) ? sanitize_key( (string) $test['goal'] ) : '',
                'decision_mode'      => isset( $test['decision_mode'] ) ? sanitize_key( (string) $test['decision_mode'] ) : '',
                'decision_rule'      => isset( $test['decision_rule'] ) ? sanitize_key( (string) $test['decision_rule'] ) : '',
                'split'              => isset( $test['split'] ) ? (int) $test['split'] : 50,
                'control_post_type'  => $control_id > 0 ? sanitize_key( (string) get_post_type( $control_id ) ) : '',
                'variant_post_type'  => $variant_id > 0 ? sanitize_key( (string) get_post_type( $variant_id ) ) : '',
                'has_control'        => $control_id > 0 ? 1 : 0,
                'has_variant'        => $variant_id > 0 ? 1 : 0,
                'click_target_count' => count( (array) $targets ),
                'click_target_types' => $target_types,
                'stats'              => $stats,
            ],
            'snapshot' => abtestkit_build_compatibility_help_snapshot(),
        ],
        true
    );

    return true;
}

/**
 * Milestone: first time opening the Create Test wizard (and first time opening AFTER first test created).
 */
function abtestkit_telemetry_track_pt_wizard_opened(): void {
    if ( ! abtestkit_is_telemetry_opted_in() ) return;

    $created = abtestkit_telemetry_tests_created_count();

    abtestkit_telemetry_send_once(
        'pt_wizard_opened_first',
        'pt_wizard_opened',
        [ 'tests_created' => (int) $created ]
    );

    if ( $created > 0 ) {
        abtestkit_telemetry_send_once(
            'pt_wizard_opened_after_first',
            'pt_wizard_opened_after_first',
            [ 'tests_created' => (int) $created ]
        );
    }
}

/**
 * Event: successful PT test creation (fires every time, plus 1st/2nd milestones).
 */
function abtestkit_telemetry_track_test_created( array $test, array $context = [] ): void {
    if ( ! abtestkit_is_telemetry_opted_in() ) return;

    $kind = isset( $test['kind'] ) ? sanitize_key( (string) $test['kind'] ) : '';
    $goal = isset( $test['goal'] ) ? sanitize_key( (string) $test['goal'] ) : '';
    $mode = isset( $test['decision_mode'] ) ? sanitize_key( (string) $test['decision_mode'] ) : '';
    $rule = isset( $test['decision_rule'] ) ? sanitize_key( (string) $test['decision_rule'] ) : '';

    $links = [];
    if ( ! empty( $test['links'] ) && is_array( $test['links'] ) ) {
        $links = array_values( array_map( 'strval', $test['links'] ) );
    }

    $count = abtestkit_telemetry_inc_tests_created();

    $data = [
        'tests_created' => (int) $count,
        'kind'          => $kind,
        'goal'          => $goal,
        'decision_mode' => $mode,
        'decision_rule' => $rule,
        'started'       => ( ( $test['status'] ?? '' ) === 'running' ) ? 1 : 0,
        'split'         => isset( $test['split'] ) ? (int) $test['split'] : 0,

        // CTA targets: hashed only
        'cta_count'     => (int) count( $links ),
        'cta_kind'      => abtestkit_telemetry_guess_target_kind( $links ),
        'cta_hashes'    => abtestkit_telemetry_hash_targets( $links ),
    ];

    // Optional request context (non-sensitive)
    foreach ( [ 'b_mode', 'seo_safe_existing_b' ] as $k ) {
        if ( array_key_exists( $k, $context ) ) {
            $data[ $k ] = is_scalar( $context[ $k ] ) ? $context[ $k ] : '';
        }
    }

    abtestkit_send_telemetry( 'pt_test_created', $data );

    if ( $count === 1 ) {
        abtestkit_telemetry_send_once( 'pt_first_test_created', 'pt_first_test_created', $data );
    } elseif ( $count === 2 ) {
        abtestkit_telemetry_send_once( 'pt_second_test_created', 'pt_second_test_created', $data );
    }
}

// ───────────────────────────────────────────────────────────
// One-time redirect + "onboarding complete" flag (hardened)
// ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'abtestkit_activate_onboarding' );
function abtestkit_activate_onboarding() {
    // Create the "done" flag if missing
    if ( get_option( 'abtestkit_onboarding_done', null ) === null ) {
        add_option( 'abtestkit_onboarding_done', '0' );
    }
    // Set the one-time redirect flag
    update_option( 'abtestkit_do_activation_redirect', '1', true );
}

/**
 * Some activation paths don’t reliably run the activation callback in the
 * same request pipeline that lands you back in wp-admin. This ensures the
 * flag is still set right after the specific plugin is activated.
 */
add_action( 'activated_plugin', function( $plugin ) {
    if ( $plugin === plugin_basename( __FILE__ ) ) {
        update_option( 'abtestkit_do_activation_redirect', '1', true );
    }
}, 10, 1 );

/**
 * Perform the one-time redirect as soon as we’re safely in admin.
 */
add_action( 'admin_init', function () {
    // Only once
    if ( '1' !== get_option( 'abtestkit_do_activation_redirect' ) ) {
        return;
    }

    // Clear the flag immediately to avoid loops
    delete_option( 'abtestkit_do_activation_redirect' );

    // Don’t redirect in these contexts
    if ( wp_doing_ajax() ) return;
    if ( is_network_admin() ) return; // Multisite network screens
    // If we're on the bulk activate action, bail early. Core has already handled nonce checks.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of a core GET param.
    if ( isset( $_GET['activate-multi'] ) ) {
	    return;
    }

    // Only redirect admins who can actually view the page
    if ( ! current_user_can( 'manage_options' ) ) return;

    // If onboarding is already complete (or tests already exist), skip it.
    $done = ( '1' === get_option( 'abtestkit_onboarding_done' ) );
    $tests = abtestkit_pt_all();
    $has_tests = is_array( $tests ) && ! empty( $tests );

    if ( $done || $has_tests ) {
        wp_safe_redirect( admin_url( 'admin.php?page=abtestkit-dashboard' ) );
        exit;
    }

    // Otherwise, show onboarding once.
    wp_safe_redirect( admin_url( 'admin.php?page=abtestkit-get-started' ) );
    exit;
} );

// ───────────────────────────────────────────────────────────
// Hidden admin pages
// ───────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    // "Create new test +" item under main abtestkit menu → points to the PT wizard
    add_submenu_page(
        'abtestkit-dashboard', // parent = main abtestkit menu
        __( 'Create New Test – abtestkit', 'abtestkit' ),
        __( 'Create new test +', 'abtestkit' ),
        'manage_options',
        'abtestkit-pt-wizard',
        'abtestkit_render_pt_wizard_page'
    );

    // Page Test Wizard (hidden – direct URL only)
    add_submenu_page(
        null, // hidden from menus
        __( 'Create Page Test', 'abtestkit' ),
        __( 'Create Page Test', 'abtestkit' ),
        'manage_options',
        'abtestkit-pt-wizard',
        'abtestkit_render_pt_wizard_page'
    );

    // Onboarding "Get started" screen (hidden – direct URL only)
    add_submenu_page(
        null,
        __( 'Welcome to abtestkit', 'abtestkit' ),
        __( 'Welcome to abtestkit', 'abtestkit' ),
        'manage_options',
        'abtestkit-get-started',
        'abtestkit_render_onboarding_page'
    );

    // Test Performance page (hidden – direct URL only)
    add_submenu_page(
        null,
        __( 'Test Performance', 'abtestkit' ),
        __( 'Test Performance', 'abtestkit' ),
        'manage_options',
        'abtestkit-test-performance',
        'abtestkit_render_test_performance_page'
    );
} );

function abtestkit_render_onboarding_page() {
    echo '<div id="abtestkit-onboarding-root"></div>';
}

// ───────────────────────────────────────────────────────────
// Enqueue wizard assets on that page
// ───────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'admin_page_abtestkit-get-started' !== $hook ) {
        return;
    }

    // Core deps
    wp_enqueue_script( 'wp-element' );
    wp_enqueue_script( 'wp-components' );
    wp_enqueue_script( 'wp-api-fetch' );
    wp_enqueue_style( 'wp-components' );

    // Onboarding wizard JS
    wp_enqueue_script(
        'abtestkit-onboarding',
        plugins_url( 'assets/js/onboarding.js', __FILE__ ),
        array( 'wp-element', 'wp-components', 'wp-api-fetch' ),
        '1.5.1',
        true
    );

    wp_localize_script(
        'abtestkit-onboarding',
        'ABTESTKIT_ONBOARDING',
        array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'rest'  => esc_url_raw( rest_url( 'abtestkit/v1' ) ),
            'done'  => (bool) get_option( 'abtestkit_onboarding_done' ),
            'site'  => get_bloginfo( 'name' ),
            // Telemetry sending itself is a no-op, but this flag can still control UI if needed
            'telemetryOptedIn' => abtestkit_is_telemetry_opted_in(),
            'links' => array(
                'dashboard' => admin_url( 'admin.php?page=abtestkit-dashboard' ),
                'createUrl' => admin_url( 'admin.php?page=abtestkit-pt-wizard' ),

                'new_post'  => admin_url( 'post-new.php' ),
                'blocks'    => admin_url( 'edit.php?post_type=page' ),
                'settings'  => admin_url( 'options-general.php?page=abtestkit-settings' ),
                'plugins'   => admin_url( 'plugins.php' ),
            ),
            // Image assets for the 7-step onboarding wizard
            'assets' => array(
                // Page 1
                'getStarted'  => plugins_url( 'assets/img/onboarding-get-started.png', __FILE__ ),

                // Page 2
                'selectTestType' => plugins_url( 'assets/img/onboarding-select-test-type.png', __FILE__ ),

                // Page 3 – Pages
                'pagesVersionBSource' => plugins_url( 'assets/img/onboarding-page-version-b-source.png', __FILE__ ),
                'pagesReviewVersions' => plugins_url( 'assets/img/onboarding-review-versions.png', __FILE__ ),
                'pagesReviewVersions2' => plugins_url( 'assets/img/onboarding-review-versions-2.png', __FILE__ ),

                // Page 4 – Products
                'productsSelectProduct'  => plugins_url( 'assets/img/onboarding-select-product.png', __FILE__ ),
                'productsReviewVersions' => plugins_url( 'assets/img/onboarding-product-review-versions.png', __FILE__ ),

                // Page 5 – Conversions & Clicks
                'conversionType'               => plugins_url( 'assets/img/onboarding-choose-conversion-type.png', __FILE__ ),
                'clicksSelectTarget'           => plugins_url( 'assets/img/onboarding-clicks-select-target-in-preview.png', __FILE__ ),
                'clicksClickTarget'            => plugins_url( 'assets/img/onboarding-clicks-click-a-target.png', __FILE__ ),
                'clicksSelectAnother'          => plugins_url( 'assets/img/onboarding-clicks-select-another.png', __FILE__ ),
                'clicksOnAnotherPage'          => plugins_url( 'assets/img/onboarding-clicks-on-another-page.png', __FILE__ ),
                'clicksSelectTargetDifferentPage' => plugins_url( 'assets/img/onboarding-select-target-different-page.png', __FILE__ ),

                // Page 6 – Summary
                'summary'  => plugins_url( 'assets/img/onboarding-summary.png', __FILE__ ),

                // Page 7 – Dashboard / How tests work
                'dashboard' => plugins_url( 'assets/img/onboarding-dashboard.png', __FILE__ ),
            ),
        )
    );
} );

// --- Custom CSS test helpers ---
// These must be available globally because the dashboard, health checks,
// frontend rendering, and REST endpoints can all read Custom CSS test data.
if ( ! function_exists( 'abtestkit_sanitize_custom_css_input' ) ) {
    function abtestkit_sanitize_custom_css_input( $css ) : string {
        $css = is_string( $css ) ? wp_unslash( $css ) : '';
        $css = str_replace( "\0", '', $css );

        // Remove HTML/script/style wrappers. The saved value should be stylesheet rules only.
        $css = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $css );
        $css = preg_replace( '#</?style\b[^>]*>#i', '', $css );
        $css = preg_replace( '/<\?(?:php)?/i', '', $css );

        // Remove high-risk CSS features while keeping normal override CSS intact.
        $css = preg_replace( '/@import\b[^;]+;?/i', '', $css );
        $css = preg_replace( '/expression\s*\(/i', '', $css );
        $css = preg_replace( '/behavior\s*:/i', 'x-abtestkit-removed:', $css );
        $css = preg_replace( '/-moz-binding\s*:/i', 'x-abtestkit-removed:', $css );
        $css = preg_replace( '/url\s*\(\s*([\'"]?)\s*(?:javascript|vbscript|data):[^)]*\)/i', 'url($1#)', $css );

        // Keep tabs/newlines, remove other control characters.
        $css = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css );

        return trim( (string) $css );
    }
}

if ( ! function_exists( 'abtestkit_custom_css_marker_class_from_label' ) ) {
    function abtestkit_custom_css_marker_class_from_label( string $label ) : string {
        $slug = sanitize_title( $label );

        if ( $slug === '' ) {
            $slug = 'marker';
        }

        return sanitize_html_class( 'abtestkit-marker-' . $slug );
    }
}

if ( ! function_exists( 'abtestkit_sanitize_custom_css_markers' ) ) {
    function abtestkit_sanitize_custom_css_markers( $markers ) : array {
        if ( ! is_array( $markers ) ) {
            return [];
        }

        $clean = [];

        foreach ( $markers as $marker ) {
            if ( ! is_array( $marker ) ) {
                continue;
            }

            $label    = isset( $marker['label'] ) ? sanitize_text_field( wp_unslash( (string) $marker['label'] ) ) : '';
            $selector = isset( $marker['selector'] ) ? sanitize_text_field( wp_unslash( (string) $marker['selector'] ) ) : '';

            $selector = trim( str_replace( [ '<', '>', '`' ], '', $selector ) );

            if ( $label === '' || $selector === '' ) {
                continue;
            }

            $class_name = isset( $marker['class_name'] )
                ? sanitize_html_class( wp_unslash( (string) $marker['class_name'] ) )
                : '';

            if ( $class_name === '' || strpos( $class_name, 'abtestkit-marker-' ) !== 0 ) {
                $class_name = abtestkit_custom_css_marker_class_from_label( $label );
            }

            $clean[] = [
                'label'      => $label,
                'selector'   => $selector,
                'class_name' => $class_name,
            ];
        }

        return array_values( $clean );
    }
}

if ( ! function_exists( 'abtestkit_custom_css_data_for_test' ) ) {
    function abtestkit_custom_css_data_for_test( array $test ) : array {
        return [
            'css_scope'   => isset( $test['css_scope'] ) ? sanitize_key( (string) $test['css_scope'] ) : '',
            'custom_css'  => isset( $test['custom_css'] ) ? abtestkit_sanitize_custom_css_input( $test['custom_css'] ) : '',
            'css_markers' => isset( $test['css_markers'] ) ? abtestkit_sanitize_custom_css_markers( $test['css_markers'] ) : [],
        ];
    }
}

// --- Custom HTML test helpers ---
if ( ! function_exists( 'abtestkit_sanitize_custom_html_changes' ) ) {
    function abtestkit_sanitize_custom_html_changes( $changes ) : array {
        if ( ! is_array( $changes ) ) {
            return [];
        }

        $allowed_html = wp_kses_allowed_html( 'post' );

        $extra_tags = [
            'section', 'article', 'header', 'footer', 'main', 'nav',
            'button', 'picture', 'source', 'small', 'mark', 'time',
            'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon',
        ];

        $common_attributes = [
            'id'          => true,
            'class'       => true,
            'style'       => true,
            'title'       => true,
            'role'        => true,
            'tabindex'    => true,
            'aria-label'  => true,
            'aria-hidden' => true,
            'aria-live'   => true,
            'aria-*'      => true,
            'data-*'      => true,
        ];

        foreach ( $allowed_html as $tag => $attributes ) {
            $allowed_html[ $tag ] = array_merge(
                is_array( $attributes ) ? $attributes : [],
                $common_attributes
            );
        }

        foreach ( $extra_tags as $tag ) {
            if ( ! isset( $allowed_html[ $tag ] ) || ! is_array( $allowed_html[ $tag ] ) ) {
                $allowed_html[ $tag ] = [];
            }

            $allowed_html[ $tag ] = array_merge( $allowed_html[ $tag ], $common_attributes );
        }

        $allowed_html['button'] = array_merge(
            $allowed_html['button'],
            [
                'type'     => true,
                'name'     => true,
                'value'    => true,
                'disabled' => true,
            ]
        );

        $allowed_html['source'] = array_merge(
            $allowed_html['source'],
            [
                'src'    => true,
                'srcset' => true,
                'sizes'  => true,
                'type'   => true,
                'media'  => true,
            ]
        );

        $allowed_html['svg'] = array_merge(
            $allowed_html['svg'],
            [
                'xmlns'        => true,
                'viewbox'      => true,
                'width'        => true,
                'height'       => true,
                'fill'         => true,
                'stroke'       => true,
                'stroke-width' => true,
                'focusable'    => true,
            ]
        );

        $svg_shape_attributes = [
            'd'               => true,
            'x'               => true,
            'y'               => true,
            'x1'              => true,
            'x2'              => true,
            'y1'              => true,
            'y2'              => true,
            'cx'              => true,
            'cy'              => true,
            'r'               => true,
            'rx'              => true,
            'ry'              => true,
            'width'           => true,
            'height'          => true,
            'points'          => true,
            'fill'            => true,
            'fill-rule'       => true,
            'clip-rule'       => true,
            'stroke'          => true,
            'stroke-width'    => true,
            'stroke-linecap'  => true,
            'stroke-linejoin' => true,
            'transform'       => true,
        ];

        foreach ( [ 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon' ] as $svg_tag ) {
            $allowed_html[ $svg_tag ] = array_merge(
                $allowed_html[ $svg_tag ],
                $svg_shape_attributes
            );
        }

        $clean = [];

        foreach ( array_slice( $changes, 0, 20 ) as $change ) {
            if ( ! is_array( $change ) ) {
                continue;
            }

            $label = isset( $change['label'] )
                ? substr( sanitize_text_field( wp_unslash( (string) $change['label'] ) ), 0, 120 )
                : '';

            $selector = isset( $change['selector'] )
                ? sanitize_text_field( wp_unslash( (string) $change['selector'] ) )
                : '';

            $selector = trim( str_replace( [ "\0", '<', '`' ], '', $selector ) );
            $selector = substr( $selector, 0, 1000 );

            $operation = isset( $change['operation'] )
                ? sanitize_key( wp_unslash( (string) $change['operation'] ) )
                : 'replace_contents';

            if ( ! in_array( $operation, [ 'replace_contents', 'insert_before', 'insert_after', 'prepend_inside', 'append_inside' ], true ) ) {
                $operation = 'replace_contents';
            }

            $match_mode = isset( $change['match_mode'] )
                ? sanitize_key( wp_unslash( (string) $change['match_mode'] ) )
                : 'all';

            if ( ! in_array( $match_mode, [ 'first', 'all' ], true ) ) {
                $match_mode = 'all';
            }

            $html = isset( $change['html'] ) && is_string( $change['html'] )
                ? wp_unslash( $change['html'] )
                : '';

            $html = str_replace( "\0", '', $html );
            $intentionally_empty = trim( $html ) === '';

            $html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
            $html = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $html );
            $html = preg_replace( '#<(?:iframe|object|embed)\b[^>]*>.*?</(?:iframe|object|embed)>#is', '', $html );
            $html = preg_replace( '#<(?:iframe|object|embed)\b[^>]*/?>#is', '', $html );

            if ( preg_match_all( '#<\s*([a-z][a-z0-9-]*-[a-z0-9-]+)\b#i', $html, $custom_tag_matches ) ) {
                foreach ( array_unique( array_map( 'strtolower', $custom_tag_matches[1] ) ) as $custom_tag ) {
                    if ( in_array( $custom_tag, [ 'script', 'style', 'iframe', 'object', 'embed' ], true ) ) {
                        continue;
                    }

                    $allowed_html[ $custom_tag ] = $common_attributes;
                }
            }

            $html = wp_kses( $html, $allowed_html );
            $html = trim( (string) $html );

            if ( $selector === '' || ( ! $intentionally_empty && $html === '' ) ) {
                continue;
            }

            if ( $label === '' ) {
                $label = __( 'Selected element', 'abtestkit' );
            }

            $clean[] = [
                'label'      => $label,
                'selector'   => $selector,
                'operation'  => $operation,
                'match_mode' => $match_mode,
                'html'       => $html,
            ];
        }

        return array_values( $clean );
    }
}

if ( ! function_exists( 'abtestkit_custom_html_data_for_test' ) ) {
    function abtestkit_custom_html_data_for_test( array $test ) : array {
        return [
            'html_scope'   => isset( $test['html_scope'] ) ? sanitize_key( (string) $test['html_scope'] ) : '',
            'html_changes' => isset( $test['html_changes'] ) ? abtestkit_sanitize_custom_html_changes( $test['html_changes'] ) : [],
        ];
    }
}

// ───────────────────────────────────────────────────────────
// Minimal REST endpoint to mark onboarding complete
// (and to capture choices like telemetry opt-in)
// ───────────────────────────────────────────────────────────
add_action( 'rest_api_init', function () {
    register_rest_route(
        'abtestkit/v1',
        '/onboarding',
        array(
            array(
                'methods'             => 'POST',
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'callback'            => function ( WP_REST_Request $req ) {
                    $done = (bool) $req->get_param( 'done' );

                    update_option( 'abtestkit_onboarding_done', $done ? '1' : '0' );

                    return rest_ensure_response(
                        array(
                            'ok'        => true,
                            'done'      => $done,
                            'telemetry' => abtestkit_is_telemetry_opted_in(),
                        )
                    );
                },
            ),
        )
    );

    // --- Page Test Wizard: search pages/products ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/pages',
        [
            'methods'             => 'GET',
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            'callback'            => function ( WP_REST_Request $req ) {
                $q    = sanitize_text_field( (string) $req->get_param( 'q' ) );
                $type = sanitize_key( (string) $req->get_param( 'type' ) );

                // Allow pages, posts, WooCommerce products, or reusable sections.
                // Reusable Section Test v1 targets a WP/Elementor page rendered through a shortcode.
                $post_type = in_array( $type, [ 'page', 'post', 'product' ], true ) ? $type : 'page';

                if ( $type === 'reusable_section' ) {
                    $post_type = 'page';
                }

                // If we're searching products, exclude any Version B posts used in tests
                $post__not_in = [];
                if ( $post_type === 'product' ) {
                    foreach ( abtestkit_pt_all() as $t ) {
                        if ( ! empty( $t['variant_id'] ) ) {
                            $post__not_in[] = (int) $t['variant_id'];
                        }
                    }
                    $post__not_in = array_values( array_unique( array_filter( $post__not_in ) ) );
                }

                $query_args = [
                    'post_type'           => $post_type,
                    'posts_per_page'      => 200,
                    'post_status'         => [ 'publish', 'draft', 'pending', 'future', 'private' ],
                    'no_found_rows'       => true,
                    'fields'              => 'ids',
                    'post__not_in'        => $post__not_in,
                    'ignore_sticky_posts' => true,
                    'orderby'             => 'date',
                    'order'               => 'DESC',
                ];

                // Only apply a search query when the user has actually typed one.
                // On some large/live sites, passing an empty `s` can produce odd results.
                if ( $q !== '' ) {
                    $query_args['s'] = $q;
                }

                $query = new WP_Query( $query_args );

                $items = [];
                foreach ( (array) $query->posts as $pid ) {
                    $actual_post_type = get_post_type( $pid );

                    // Hard guard against anything leaking into the results from filters/plugins.
                    if ( $actual_post_type !== $post_type ) {
                        continue;
                    }

                    $preview_url = '';
                    $permalink   = (string) get_permalink( $pid );

                    if ( $post_type === 'product' ) {
                        if ( $permalink !== '' ) {
                            $preview_url = add_query_arg( 'abtestkit_preview', '1', $permalink );
                        }
                    } else {
                        if ( function_exists( 'get_preview_post_link' ) ) {
                            $preview_url = (string) get_preview_post_link( $pid );
                        }

                        if ( $preview_url === '' ) {
                            $preview_url = $permalink;
                        }

                        if ( $preview_url !== '' ) {
                            $preview_url = add_query_arg( 'abtestkit_preview', '1', $preview_url );
                        }
                    }

                    $item = [
                        'id'          => (int) $pid,
                        'title'       => get_the_title( $pid ),
                        'status'      => get_post_status( $pid ),
                        'post_type'   => (string) $actual_post_type,
                        'preview_url' => $preview_url,
                        'permalink'   => $permalink,
                        // display-ready localised date (matches WP list tables)
                        'date'        => get_the_date( 'Y/m/d', $pid ),
                        // raw ISO for any future sorting/formatting if you want it
                        'date_iso'    => get_post_field( 'post_date', $pid ),
                    ];

                    // Check if this page/product is already part of a running test.
                    // For reusable-section selection, also block source pages that are already
                    // embedded inside another running page/product/post test.
                    $conflict_kind = ( $type === 'reusable_section' ) ? 'reusable_section' : $post_type;
                    $conflicts     = abtestkit_pt_conflicts_for_pages( (int) $pid, 0, '', $conflict_kind );

                    $in_running_test = ! empty( $conflicts );

                    $item['in_running_test'] = $in_running_test;
                    $item['disabled']        = $in_running_test;
                    $item['disabled_reason'] = $in_running_test ? 'conflict_running' : '';
                    $item['conflicts']       = $conflicts;

                    // If listing WooCommerce products, include basic product meta
                    if ( $post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                        $product = wc_get_product( $pid );
                        if ( $product ) {
                            $image_id    = $product->get_image_id();
                            $gallery_ids = $product->get_gallery_image_ids();

                            $image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
                            $gallery_urls = [];

                            if ( is_array( $gallery_ids ) && $gallery_ids ) {
                                foreach ( $gallery_ids as $gid ) {
                                    $url = wp_get_attachment_image_url( $gid, 'full' );
                                    if ( $url ) {
                                        $gallery_urls[] = $url;
                                    }
                                }
                            }

                            $item['product'] = [
                                'price'             => $product->get_price(),
                                'regular_price'     => $product->get_regular_price(),
                                'sale_price'        => $product->get_sale_price(),
                                'short_description' => $product->get_short_description(),
                                'description'       => $product->get_description(),
                                'image_id'          => $image_id,
                                'image_url'         => $image_url,
                                'gallery_ids'       => $gallery_ids,
                                'gallery_urls'      => $gallery_urls,
                            ];

                            // Also expose a simple Category label for the wizard list
                            $cats      = get_the_terms( $pid, 'product_cat' );
                            $cat_names = [];

                            if ( is_array( $cats ) ) {
                                foreach ( $cats as $cat ) {
                                    if ( isset( $cat->name ) && $cat->name !== '' ) {
                                        $cat_names[] = $cat->name;
                                    }
                                }
                            }
                            if ( $cat_names ) {
                                // Top-level field so JS can use p.category directly
                                $item['category'] = implode( ', ', $cat_names );
                            }
                        }
                    }
                    $items[] = $item;
                }

                return rest_ensure_response( [ 'ok' => true, 'pages' => $items ] );
            },
        ]
    );

// --- Page Test Wizard: create a temporary preview token for WooCommerce product overrides ---
register_rest_route(
    'abtestkit/v1',
    '/pt/product-preview',
    [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function ( WP_REST_Request $req ) {

            $control_id = absint( $req->get_param( 'control_id' ) );
            $overrides  = $req->get_param( 'product_overrides' );

            if ( $control_id <= 0 ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            if ( ! is_array( $overrides ) ) {
                $overrides = [];
            }

            // Whitelist keys to store
            $allowed = [
                'title',
                'price',
                'regular_price',
                'sale_price',
                'short_description',
                'description',
                'image_url',
                'image_id',
                'gallery_urls',
                'gallery_ids',
            ];

            $clean = [];
            foreach ( $allowed as $k ) {
                if ( ! array_key_exists( $k, $overrides ) ) continue;

                $v = $overrides[ $k ];

                if ( is_array( $v ) ) {
                    // Arrays are only allowed for gallery urls/ids
                    if ( $k === 'gallery_urls' ) {
                        $urls = array_map( 'trim', array_map( 'sanitize_text_field', array_map( 'wp_unslash', $v ) ) );
                        $urls = array_values( array_filter( $urls ) );
                        if ( $urls ) $clean[ $k ] = $urls;
                    } elseif ( $k === 'gallery_ids' ) {
                        $ids = array_map( 'absint', $v );
                        $ids = array_values( array_filter( $ids ) );
                        if ( $ids ) $clean[ $k ] = $ids;
                    }
                    continue;
                }

                // Scalars
                $sv = sanitize_text_field( wp_unslash( (string) $v ) );
                if ( $sv !== '' ) {
                    $clean[ $k ] = $sv;
                }
            }

            // Must have at least one override to be meaningful
            if ( empty( $clean ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'no_overrides' ] );
            }

            $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_hash( microtime( true ) . wp_rand() );
            $key   = 'abtestkit_product_preview_' . $token;

            // 10 minutes is plenty for a wizard preview
            set_transient( $key, [
                'control_id' => $control_id,
                'overrides'  => $clean,
                'user_id'    => get_current_user_id(),
                'created'    => time(),
            ], 10 * MINUTE_IN_SECONDS );

            return rest_ensure_response( [ 'ok' => true, 'token' => $token ] );
        },
    ]
);

// --- Page Test Wizard: create a temporary Custom CSS preview token for unsaved wizard CSS ---
register_rest_route(
    'abtestkit/v1',
    '/pt/custom-css-preview',
    [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function ( WP_REST_Request $req ) {
            $control_id  = absint( $req->get_param( 'control_id' ) );
            $custom_css  = abtestkit_sanitize_custom_css_input( $req->get_param( 'custom_css' ) );
            $css_markers = abtestkit_sanitize_custom_css_markers( $req->get_param( 'css_markers' ) );

            if ( $control_id <= 0 ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            $post_type = get_post_type( $control_id );
            if ( ! in_array( $post_type, [ 'page', 'post', 'product' ], true ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            if ( $custom_css === '' && empty( $css_markers ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'empty_preview' ] );
            }

            $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_hash( microtime( true ) . wp_rand() );
            $key   = 'abtestkit_custom_css_preview_' . $token;

            set_transient(
                $key,
                [
                    'control_id'  => $control_id,
                    'post_type'   => $post_type,
                    'custom_css'  => $custom_css,
                    'css_markers' => $css_markers,
                    'user_id'     => get_current_user_id(),
                    'created'     => time(),
                ],
                10 * MINUTE_IN_SECONDS
            );

            return rest_ensure_response(
                [
                    'ok'    => true,
                    'token' => $token,
                ]
            );
        },
    ]
);

// --- Page Test Wizard: create a temporary Custom HTML preview token for unsaved wizard HTML ---
register_rest_route(
    'abtestkit/v1',
    '/pt/custom-html-preview',
    [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function ( WP_REST_Request $req ) {
            $control_id   = absint( $req->get_param( 'control_id' ) );
            $html_changes = abtestkit_sanitize_custom_html_changes( $req->get_param( 'html_changes' ) );

            if ( $control_id <= 0 ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            $post_type = get_post_type( $control_id );
            if ( ! in_array( $post_type, [ 'page', 'post', 'product' ], true ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            if ( empty( $html_changes ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'empty_preview' ] );
            }

            $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_hash( microtime( true ) . wp_rand() );
            $key   = 'abtestkit_custom_html_preview_' . $token;

            set_transient(
                $key,
                [
                    'control_id'   => $control_id,
                    'post_type'    => $post_type,
                    'html_changes' => $html_changes,
                    'user_id'      => get_current_user_id(),
                    'created'      => time(),
                ],
                10 * MINUTE_IN_SECONDS
            );

            return rest_ensure_response(
                [
                    'ok'    => true,
                    'token' => $token,
                ]
            );
        },
    ]
);

// --- Page Test Wizard: record admin preview iframe failures for the Health Checker ---
register_rest_route(
    'abtestkit/v1',
    '/pt/preview-health',
    [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function ( WP_REST_Request $req ) {
            $post_id = absint( $req->get_param( 'post_id' ) );
            $url     = esc_url_raw( (string) $req->get_param( 'url' ) );
            $label   = sanitize_text_field( (string) $req->get_param( 'label' ) );
            $context = sanitize_key( (string) $req->get_param( 'context' ) );

            if ( $post_id <= 0 && $url === '' ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'missing_preview' ] );
            }

            if ( function_exists( 'abtestkit_preview_health_record_failure' ) ) {
                abtestkit_preview_health_record_failure( $post_id, $url, $label, $context );
            }

            return rest_ensure_response( [ 'ok' => true ] );
        },
    ]
);

// --- Custom CSS test helpers ---
if ( ! function_exists( 'abtestkit_sanitize_custom_css_input' ) ) {
    function abtestkit_sanitize_custom_css_input( $css ) : string {
        $css = is_string( $css ) ? wp_unslash( $css ) : '';
        $css = str_replace( "\0", '', $css );

        // Remove HTML/script/style wrappers. The saved value should be stylesheet rules only.
        $css = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $css );
        $css = preg_replace( '#</?style\b[^>]*>#i', '', $css );
        $css = preg_replace( '/<\?(?:php)?/i', '', $css );

        // Remove high-risk CSS features while keeping normal override CSS intact.
        $css = preg_replace( '/@import\b[^;]+;?/i', '', $css );
        $css = preg_replace( '/expression\s*\(/i', '', $css );
        $css = preg_replace( '/behavior\s*:/i', 'x-abtestkit-removed:', $css );
        $css = preg_replace( '/-moz-binding\s*:/i', 'x-abtestkit-removed:', $css );
        $css = preg_replace( '/url\s*\(\s*([\'"]?)\s*(?:javascript|vbscript|data):[^)]*\)/i', 'url($1#)', $css );

        // Keep tabs/newlines, remove other control characters.
        $css = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css );

        return trim( (string) $css );
    }
}

if ( ! function_exists( 'abtestkit_custom_css_marker_class_from_label' ) ) {
    function abtestkit_custom_css_marker_class_from_label( string $label ) : string {
        $slug = sanitize_title( $label );

        if ( $slug === '' ) {
            $slug = 'marker';
        }

        return sanitize_html_class( 'abtestkit-marker-' . $slug );
    }
}

if ( ! function_exists( 'abtestkit_sanitize_custom_css_markers' ) ) {
    function abtestkit_sanitize_custom_css_markers( $markers ) : array {
        if ( ! is_array( $markers ) ) {
            return [];
        }

        $clean = [];

        foreach ( $markers as $marker ) {
            if ( ! is_array( $marker ) ) {
                continue;
            }

            $label    = isset( $marker['label'] ) ? sanitize_text_field( wp_unslash( (string) $marker['label'] ) ) : '';
            $selector = isset( $marker['selector'] ) ? sanitize_text_field( wp_unslash( (string) $marker['selector'] ) ) : '';

            $selector = trim( str_replace( [ '<', '>', '`' ], '', $selector ) );

            if ( $label === '' || $selector === '' ) {
                continue;
            }

            $class_name = isset( $marker['class_name'] )
                ? sanitize_html_class( wp_unslash( (string) $marker['class_name'] ) )
                : '';

            if ( $class_name === '' || strpos( $class_name, 'abtestkit-marker-' ) !== 0 ) {
                $class_name = abtestkit_custom_css_marker_class_from_label( $label );
            }

            $clean[] = [
                'label'      => $label,
                'selector'   => $selector,
                'class_name' => $class_name,
            ];
        }

        return array_values( $clean );
    }
}

if ( ! function_exists( 'abtestkit_custom_css_data_for_test' ) ) {
    function abtestkit_custom_css_data_for_test( array $test ) : array {
        return [
            'css_scope'   => isset( $test['css_scope'] ) ? sanitize_key( (string) $test['css_scope'] ) : '',
            'custom_css'  => isset( $test['custom_css'] ) ? abtestkit_sanitize_custom_css_input( $test['custom_css'] ) : '',
            'css_markers' => isset( $test['css_markers'] ) ? abtestkit_sanitize_custom_css_markers( $test['css_markers'] ) : [],
        ];
    }
}

// --- Page Test Wizard: create test (duplicate or use existing as Version B) ---
register_rest_route(
    'abtestkit/v1',
    '/pt/create',
    [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function ( WP_REST_Request $req ) {
            $control_id = absint( $req->get_param( 'control_id' ) );
            $test_title = sanitize_text_field( (string) $req->get_param( 'test_title' ) );
            $mode       = sanitize_key( $req->get_param( 'b_mode' ) ); // 'duplicate' | 'existing'
            $b_page_id  = absint( $req->get_param( 'b_page_id' ) );
            $test_type  = sanitize_key( (string) $req->get_param( 'test_type' ) );

            // Existing page as B: optional SEO protection toggle (default ON).
            $seo_safe_existing_b = $req->get_param( 'seo_safe_existing_b' );
            $seo_safe_existing_b = ( $seo_safe_existing_b === null ) ? true : (bool) $seo_safe_existing_b;

            $start      = (bool) $req->get_param( 'start' );
            $split      = max( 0, min( 100, (int) ( $req->get_param( 'split' ) ?? 50 ) ) );

            // Decision rule (wizard dropdown) — controls when a winner may be auto-declared.
            $decision_rule = sanitize_key( (string) $req->get_param( 'decision_rule' ) ); // fast|balanced|precise|manual
            $decision_mode = sanitize_key( (string) $req->get_param( 'decision_mode' ) ); // auto|manual

            $allowed_rules = [ 'fast', 'balanced', 'precise', 'manual' ];
            if ( ! in_array( $decision_rule, $allowed_rules, true ) ) {
                $decision_rule = 'balanced';
            }

            // Defaults
            $min_impressions = 50;
            $min_conversions = 5;
            $decision_mode   = ( $decision_mode === 'manual' || $decision_rule === 'manual' ) ? 'manual' : 'auto';

            if ( $decision_mode === 'manual' ) {
                // Manual mode: never auto-declare a winner.
                $min_impressions = 0;
                $min_conversions = 0;
                $decision_rule   = 'manual';
            } else {
                // Auto mode: accept explicit thresholds but enforce allowed values.
                $mi = absint( $req->get_param( 'min_impressions' ) );
                $mc = absint( $req->get_param( 'min_conversions' ) );

                $allowed_mi = [ 25, 50, 75 ];
                $allowed_mc = [ 3, 5, 10 ];

                $min_impressions = in_array( $mi, $allowed_mi, true ) ? $mi : 50;
                $min_conversions = in_array( $mc, $allowed_mc, true ) ? $mc : 5;

                // Ensure rule presets stay consistent even if JS changes later.
                if ( $decision_rule === 'fast' )    { $min_impressions = 25; $min_conversions = 3; }
                if ( $decision_rule === 'balanced' ){ $min_impressions = 50; $min_conversions = 5; }
                if ( $decision_rule === 'precise' ) { $min_impressions = 75; $min_conversions = 10; }
            }

            $post_type     = $control_id ? get_post_type( $control_id ) : '';
            $allowed_types = [ 'page', 'post', 'product' ];

            $is_reusable_section = ( $test_type === 'reusable_section' );

            if ( $is_reusable_section && $post_type !== 'page' ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            if ( ! $control_id || ! in_array( $post_type, $allowed_types, true ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_control' ] );
            }

            if ( $test_type === 'custom_css' ) {
                $custom_css  = abtestkit_sanitize_custom_css_input( $req->get_param( 'custom_css' ) );
                $css_markers = abtestkit_sanitize_custom_css_markers( $req->get_param( 'css_markers' ) );
                $css_scope   = sanitize_key( (string) $req->get_param( 'css_scope' ) );

                if ( ! in_array( $css_scope, [ 'page', 'post', 'product' ], true ) ) {
                    $css_scope = $post_type;
                }

                if ( $custom_css === '' ) {
                    return rest_ensure_response( [ 'ok' => false, 'error' => 'missing_custom_css' ] );
                }

                $test = [
                    'id'              => 'pt-' . substr( md5( $control_id . '|custom_css|' . microtime( true ) ), 0, 8 ),
                    'title'           => $test_title !== '' ? $test_title : get_the_title( $control_id ),
                    'control_id'      => $control_id,
                    'variant_id'      => 0,
                    'status'          => $start ? 'running' : 'draft',
                    'split'           => $split,
                    'decision_rule'   => $decision_rule,
                    'decision_mode'   => $decision_mode,
                    'min_impressions' => (int) $min_impressions,
                    'min_conversions' => (int) $min_conversions,
                    'cookie_ttl_days' => 30,
                    'started_at'      => $start ? time() : 0,
                    'finished_at'     => 0,
                    'paused_at'       => 0,
                    'paused_total'    => 0,
                    'kind'            => 'custom_css',
                    'css_scope'       => $css_scope,
                    'custom_css'      => $custom_css,
                    'css_markers'     => $css_markers,
                ];

                $conflicts = abtestkit_pt_conflicts_for_pages(
                    (int) $test['control_id'],
                    0,
                    '',
                    'custom_css'
                );

                if ( ! empty( $conflicts ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'conflict_running',
                            'info'  => [
                                'message'   => 'This page is already in a running test, or a reusable section test is causing a conflict.',
                                'conflicts' => $conflicts,
                            ],
                        ]
                    );
                }

                $goal  = sanitize_key( $req->get_param( 'goal' ) );
                $links = array_filter(
                    array_map(
                        'sanitize_text_field',
                        (array) $req->get_param( 'links' )
                    )
                );

                if ( $goal === 'button' || $goal === 'link' ) {
                    $goal = 'clicks';
                }

                if ( $goal === 'destination' || $goal === 'url' || $goal === 'destination-url' ) {
                    $goal = 'destination_url';
                }

                if ( in_array( $goal, [ 'scroll', 'scroll-depth', 'scroll_percentage', 'scroll-percentage' ], true ) ) {
                    $goal = 'scroll_depth';
                }

                if ( in_array( $goal, [ 'clicks', 'form', 'add_to_cart', 'purchase', 'destination_url', 'scroll_depth' ], true ) ) {
                    $test['goal'] = $goal;

                    if ( $goal === 'scroll_depth' ) {
                        $scroll_depth          = absint( $req->get_param( 'scroll_depth' ) );
                        $allowed_scroll_depths = [ 25, 50, 75, 90 ];

                        if ( ! in_array( $scroll_depth, $allowed_scroll_depths, true ) ) {
                            $scroll_depth = 50;
                        }

                        $test['scroll_depth'] = $scroll_depth;
                        $test['links']        = [];
                    } elseif ( in_array( $goal, [ 'clicks', 'add_to_cart', 'destination_url' ], true ) ) {
                        $test['links'] = $links;
                    } else {
                        $test['links'] = [];
                    }
                }

                abtestkit_pt_put( $test );

                if ( function_exists( 'abtestkit_review_prompt_note_test_created' ) ) {
                    abtestkit_review_prompt_note_test_created();
                }

                if ( function_exists( 'abtestkit_compatibility_prompt_note_first_test_created' ) ) {
                    abtestkit_compatibility_prompt_note_first_test_created();
                }

                if ( function_exists( 'abtestkit_telemetry_track_test_created' ) ) {
                    abtestkit_telemetry_track_test_created( $test, [
                        'b_mode'              => 'custom_css',
                        'seo_safe_existing_b' => 1,
                    ] );
                }

                abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) get_current_user_id() );

                update_option( 'abtestkit_onboarding_done', '1' );

                return rest_ensure_response(
                    [
                        'ok'       => true,
                        'test'     => $test,
                        'redirect' => admin_url( 'admin.php?page=abtestkit-dashboard' ),
                    ]
                );
            }

            if ( $test_type === 'custom_html' ) {
                $html_changes = abtestkit_sanitize_custom_html_changes( $req->get_param( 'html_changes' ) );
                $html_scope   = sanitize_key( (string) $req->get_param( 'html_scope' ) );

                if ( ! in_array( $html_scope, [ 'page', 'post', 'product' ], true ) ) {
                    $html_scope = $post_type;
                }

                if ( empty( $html_changes ) ) {
                    return rest_ensure_response( [ 'ok' => false, 'error' => 'missing_custom_html' ] );
                }

                $test = [
                    'id'              => 'pt-' . substr( md5( $control_id . '|custom_html|' . microtime( true ) ), 0, 8 ),
                    'title'           => $test_title !== '' ? $test_title : get_the_title( $control_id ),
                    'control_id'      => $control_id,
                    'variant_id'      => 0,
                    'status'          => $start ? 'running' : 'draft',
                    'split'           => $split,
                    'decision_rule'   => $decision_rule,
                    'decision_mode'   => $decision_mode,
                    'min_impressions' => (int) $min_impressions,
                    'min_conversions' => (int) $min_conversions,
                    'cookie_ttl_days' => 30,
                    'started_at'      => $start ? time() : 0,
                    'finished_at'     => 0,
                    'paused_at'       => 0,
                    'paused_total'    => 0,
                    'kind'            => 'custom_html',
                    'html_scope'      => $html_scope,
                    'html_changes'    => $html_changes,
                ];

                $conflicts = abtestkit_pt_conflicts_for_pages(
                    (int) $test['control_id'],
                    0,
                    '',
                    'custom_html'
                );

                if ( ! empty( $conflicts ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'conflict_running',
                            'info'  => [
                                'message'   => 'This page is already in a running test, or a reusable section test is causing a conflict.',
                                'conflicts' => $conflicts,
                            ],
                        ]
                    );
                }

                $goal  = sanitize_key( $req->get_param( 'goal' ) );
                $links = array_filter(
                    array_map(
                        'sanitize_text_field',
                        (array) $req->get_param( 'links' )
                    )
                );

                if ( $goal === 'button' || $goal === 'link' ) {
                    $goal = 'clicks';
                }

                if ( $goal === 'destination' || $goal === 'url' || $goal === 'destination-url' ) {
                    $goal = 'destination_url';
                }

                if ( in_array( $goal, [ 'scroll', 'scroll-depth', 'scroll_percentage', 'scroll-percentage' ], true ) ) {
                    $goal = 'scroll_depth';
                }

                if ( in_array( $goal, [ 'clicks', 'form', 'add_to_cart', 'purchase', 'destination_url', 'scroll_depth' ], true ) ) {
                    $test['goal'] = $goal;

                    if ( $goal === 'scroll_depth' ) {
                        $scroll_depth          = absint( $req->get_param( 'scroll_depth' ) );
                        $allowed_scroll_depths = [ 25, 50, 75, 90 ];

                        if ( ! in_array( $scroll_depth, $allowed_scroll_depths, true ) ) {
                            $scroll_depth = 50;
                        }

                        $test['scroll_depth'] = $scroll_depth;
                        $test['links']        = [];
                    } elseif ( in_array( $goal, [ 'clicks', 'add_to_cart', 'destination_url' ], true ) ) {
                        $test['links'] = $links;
                    } else {
                        $test['links'] = [];
                    }
                }

                abtestkit_pt_put( $test );

                if ( function_exists( 'abtestkit_review_prompt_note_test_created' ) ) {
                    abtestkit_review_prompt_note_test_created();
                }

                if ( function_exists( 'abtestkit_compatibility_prompt_note_first_test_created' ) ) {
                    abtestkit_compatibility_prompt_note_first_test_created();
                }

                if ( function_exists( 'abtestkit_telemetry_track_test_created' ) ) {
                    abtestkit_telemetry_track_test_created( $test, [
                        'b_mode'              => 'custom_html',
                        'seo_safe_existing_b' => 1,
                    ] );
                }

                abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) get_current_user_id() );
                update_option( 'abtestkit_onboarding_done', '1' );

                return rest_ensure_response(
                    [
                        'ok'       => true,
                        'test'     => $test,
                        'redirect' => admin_url( 'admin.php?page=abtestkit-dashboard' ),
                    ]
                );
            }

            // Shadow product approach: do NOT accept product_overrides from the wizard.
            // Variant B is edited directly on the shadow product, and parsing large payloads can OOM.
            $overrides = [];
            if ( $post_type === 'product' ) {
                // Intentionally ignore $req->get_param('product_overrides')
            }

            $variant_id = 0;

            // Wizard safety:
            // The wizard can call /pt/duplicate (create B for editing) and then /pt/create.
            // If we "duplicate" again here, you end up with two shadows and the test wired to the wrong one.
            $user_id = (int) get_current_user_id();

            // If the UI already has a B post ID, prefer using it even if b_mode still says "duplicate".
            if ( $b_page_id && get_post_type( $b_page_id ) === $post_type ) {
                $ok = true;

                // Product tests: only accept a valid shadow product of this control.
                if ( $post_type === 'product' ) {
                    $ok = abtestkit_is_shadow_product( $b_page_id )
                        && (int) get_post_meta( $b_page_id, '_abtestkit_shadow_of', true ) === (int) $control_id;
                }

                if ( $ok && ! abtestkit_pt_variant_id_in_any_test( (int) $b_page_id ) ) {
                    $variant_id = (int) $b_page_id;
                }
            }

            if ( ! $variant_id ) {
                if ( $mode === 'duplicate' ) {

                    // Reuse the last duplicate created in this wizard session (if any).
                    $maybe = abtestkit_pt_get_last_duplicate_for_user( (int) $control_id, (int) $user_id );
                    if ( $maybe ) {
                        $variant_id = (int) $maybe;
                    } else {
                        $variant_id = abtestkit_duplicate_post_deep( $control_id );
                    }

                } elseif ( $mode === 'existing' && $b_page_id && get_post_type( $b_page_id ) === $post_type ) {

                    // Existing mode: for products, only allow a valid shadow of this control.
                    if ( $post_type === 'product' ) {
                        if ( abtestkit_is_shadow_product( $b_page_id )
                            && (int) get_post_meta( $b_page_id, '_abtestkit_shadow_of', true ) === (int) $control_id
                            && ! abtestkit_pt_variant_id_in_any_test( (int) $b_page_id )
                        ) {
                            $variant_id = (int) $b_page_id;
                        } else {
                            return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_mode' ] );
                        }
                    } else {
                        $variant_id = $b_page_id;
                    }

                } else {
                    return rest_ensure_response( [ 'ok' => false, 'error' => 'invalid_mode' ] );
                }
            }

            // For all tests (including products now), we require a real variant ID.
            if ( ! $variant_id ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'create_failed' ] );
            }

            // For non-product tests we still require a real B page.
            if ( $post_type !== 'product' && ! $variant_id ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'create_failed' ] );
            }

            $test = [
                'id'              => 'pt-' . substr( md5( $control_id . '|' . microtime( true ) ), 0, 8 ),
                'title'           => $test_title !== '' ? $test_title : get_the_title( $control_id ),
                'control_id'      => $control_id,
                // Product tests use a virtual B (no separate post); page tests keep a real B.
                'variant_id'      => $variant_id,
                'status'          => $start ? 'running' : 'draft',
                'split'           => $split,
                'decision_rule'   => $decision_rule,
                'decision_mode'   => $decision_mode,
                'min_impressions' => (int) $min_impressions,
                'min_conversions' => (int) $min_conversions,
                'cookie_ttl_days' => 30,
                'started_at'      => $start ? time() : 0,
                'finished_at'     => 0,
                'paused_at'       => 0,
                'paused_total'    => 0,
                // Mark what kind of test this is.
                'kind'            => $is_reusable_section
                    ? 'reusable_section'
                    : (
                        ( $post_type === 'product' )
                            ? 'product'
                            : ( $post_type === 'post' ? 'post' : 'page' )
                    ),
            ];

            if ( $is_reusable_section ) {
                $test['shortcode_tag']       = 'embed_page';
                $test['shortcode_attribute'] = 'id';
                $test['source_page_id']      = (int) $control_id;
            }

            // Attach overrides for product tests so Woo filters can read them.
            if ( $post_type === 'product' ) {
                $test['overrides'] = $overrides;
            }

            // Prevent creating a test if either page/product is already part of another running test.
            // This applies when creating both drafts (paused) and running tests.
            $conflicts = abtestkit_pt_conflicts_for_pages(
                (int) $test['control_id'],
                (int) $test['variant_id'],
                '',
                isset( $test['kind'] ) ? (string) $test['kind'] : ''
            );

            if ( ! empty( $conflicts ) ) {
                return rest_ensure_response(
                    [
                        'ok'    => false,
                        'error' => 'conflict_running',
                        'info'  => [
                            'message'   => 'This page is already in a running test, or a reusable section test is causing a conflict.',
                            'conflicts' => $conflicts,
                        ],
                    ]
                );
            }

            // store wizard-picked goal info
            // Goal can be: 'clicks' | 'form' | 'add_to_cart' | 'purchase' | 'destination_url' | 'scroll_depth'
            $goal  = sanitize_key( $req->get_param( 'goal' ) );
            $links = array_filter(
                array_map(
                    'sanitize_text_field',
                    (array) $req->get_param( 'links' )
                )
            );

            // Back-compat: normalise any old values to the new shape.
            if ( $goal === 'button' || $goal === 'link' ) {
                $goal = 'clicks';
            }

            if ( $goal === 'destination' || $goal === 'url' || $goal === 'destination-url' ) {
                $goal = 'destination_url';
            }

            if ( in_array( $goal, [ 'scroll', 'scroll-depth', 'scroll_percentage', 'scroll-percentage' ], true ) ) {
                $goal = 'scroll_depth';
            }

            if ( in_array( $goal, [ 'clicks', 'form', 'add_to_cart', 'purchase', 'destination_url', 'scroll_depth' ], true ) ) {
                $test['goal'] = $goal;

                // Scroll depth stores a percentage threshold, not a URL/selector target.
                if ( $goal === 'scroll_depth' ) {
                    $scroll_depth = absint( $req->get_param( 'scroll_depth' ) );
                    $allowed_scroll_depths = [ 25, 50, 75, 90 ];

                    if ( ! in_array( $scroll_depth, $allowed_scroll_depths, true ) ) {
                        $scroll_depth = 50;
                    }

                    $test['scroll_depth'] = $scroll_depth;
                    $test['links']        = [];
                } elseif ( in_array( $goal, [ 'clicks', 'add_to_cart', 'destination_url' ], true ) ) {
                    // Goals with explicit targets store those targets in links.
                    // For clicks/add-to-cart these are selectors or hrefs.
                    // For destination_url these are URL/path targets to match on page load.
                    $test['links'] = $links;
                } else {
                    $test['links'] = [];
                }
            }

            // If using an EXISTING page/post as Version B, mark it as SEO-protected while this test exists
            // (even if paused/draft). Shadow duplicates are permanently protected already.
            if ( $post_type !== 'product' && $mode === 'existing' && $seo_safe_existing_b ) {
                if ( ! abtestkit_is_shadow_variant( (int) $variant_id ) ) {
                    abtestkit_pt_mark_existing_variant_in_use(
                        (int) $variant_id,
                        (int) $control_id,
                        (string) $test['id']
                    );
                }
            }

            abtestkit_pt_put( $test );

            if ( function_exists( 'abtestkit_review_prompt_note_test_created' ) ) {
                abtestkit_review_prompt_note_test_created();
            }

            if ( function_exists( 'abtestkit_compatibility_prompt_note_first_test_created' ) ) {
                abtestkit_compatibility_prompt_note_first_test_created();
            }

                        // Telemetry (opt-in): successful test creation
                        if ( function_exists( 'abtestkit_telemetry_track_test_created' ) ) {
                            abtestkit_telemetry_track_test_created( $test, [
                                'b_mode'              => (string) $mode,
                                'seo_safe_existing_b' => $seo_safe_existing_b ? 1 : 0,
                            ] );
                        }

                        // Clear any cached duplicate pointer for this user+control once the test is created.
            abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) get_current_user_id() );

            // Once the user has created a test, never show onboarding again.
            update_option( 'abtestkit_onboarding_done', '1' );

            return rest_ensure_response(
                [
                    'ok'       => true,
                    'test'     => $test,
                    'redirect' => admin_url( 'admin.php?page=abtestkit-dashboard' ),
                ]
            );
        },
    ]
);

    // --- Dashboard: list all tests with basic stats ---
    register_rest_route(
        'abtestkit/v1',
        '/pt',
        [
            'methods'             => 'GET',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                $tests = abtestkit_pt_all();
                $tests = is_array( $tests ) ? $tests : [];

                /*
                 * Do not cache the full dashboard payload.
                 *
                 * The dashboard includes live impression/conversion counts. Caching the
                 * whole response makes new reusable-section impressions appear delayed,
                 * especially when testing in repeated incognito sessions.
                 *
                 * abtestkit_pt_stats_bulk() already performs one grouped stats query for
                 * all tests, so keeping this response live is acceptable and much clearer.
                 */
                $out                  = [];
                $stats_bulk           = abtestkit_pt_stats_bulk( $tests );
                $http_exclusions_bulk = abtestkit_pt_http_exclusions_bulk( $tests );

                foreach ( $tests as $test ) {
                    $test_id    = isset( $test['id'] ) ? (string) $test['id'] : '';
                    $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
                    $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
                    $kind       = isset( $test['kind'] ) ? (string) $test['kind'] : '';

                    if ( $kind === '' ) {
                        $control_type = $control_id > 0 ? get_post_type( $control_id ) : '';

                        if ( $control_type === 'product' ) {
                            $kind = 'product';
                        } elseif ( $control_type === 'post' ) {
                            $kind = 'post';
                        } else {
                            $kind = 'page';
                        }
                    }

                    // Base admin previews are different for product tests vs page/post tests.
                    // For products, we keep the same URL and force A/B via query args.
                    // For pages/posts, A and B each have their own permalink.
                    // Normal URL (non-preview) for the dashboard title link:
                    $url       = '';
                    $preview_a = '';
                    $preview_b = '';

                    if ( $control_id > 0 ) {
                        $base = get_permalink( $control_id );
                        $url  = $base;

                    if ( in_array( $kind, [ 'custom_css', 'custom_html' ], true ) ) {
                            $preview_a = add_query_arg(
                                [
                                    'abtestkit_preview' => '1',
                                    'abtestkit_force'   => 'A',
                                ],
                                $base
                            );

                            $preview_b = add_query_arg(
                                [
                                    'abtestkit_preview' => '1',
                                    'abtestkit_force'   => 'B',
                                ],
                                $base
                            );
                        } elseif ( $kind === 'product' ) {
                            // Product tests: force the variant via query string, but still include
                            // abtestkit_preview=1 so no impressions/clicks are logged.
                            $preview_a = add_query_arg(
                                [
                                    'abtestkit_preview' => '1',
                                    'abtestkit_force'   => 'A',
                                ],
                                $base
                            );

                            $preview_b_args = [
                                'abtestkit_preview' => '1',
                                'abtestkit_force'   => 'B',
                            ];

                            if ( $variant_id > 0 ) {
                                $preview_b_args['abtestkit_shadow_preview_id'] = (int) $variant_id;
                            }

                            $preview_b = add_query_arg( $preview_b_args, $base );
                        } else {
                            // Page/post tests: previews point at the underlying permalinks.
                            $preview_a = add_query_arg( 'abtestkit_preview', '1', $base );

                            if ( $variant_id > 0 ) {
                                $preview_b = add_query_arg(
                                    'abtestkit_preview',
                                    '1',
                                    get_permalink( $variant_id )
                                );
                            }
                        }
                    }

                    $kind_label = ( $kind === 'custom_html' )
                        ? 'Custom HTML'
                        : (
                            ( $kind === 'custom_css' )
                                ? 'Custom CSS'
                                : (
                                    ( $kind === 'reusable_section' )
                                        ? 'Reusable Section'
                                        : ucfirst( str_replace( '_', ' ', $kind ) )
                                )
                        );

                    $custom_css_data = ( $kind === 'custom_css' )
                        ? abtestkit_custom_css_data_for_test( $test )
                        : [
                            'css_scope'   => '',
                            'custom_css'  => '',
                            'css_markers' => [],
                        ];

                    $custom_html_data = ( $kind === 'custom_html' )
                        ? abtestkit_custom_html_data_for_test( $test )
                        : [
                            'html_scope'   => '',
                            'html_changes' => [],
                        ];

                    $testing_title = $control_id > 0 ? get_the_title( $control_id ) : '';
                    $test_stats            = $stats_bulk[ $test_id ] ?? abtestkit_pt_stats_default();
                    $http_exclusion_context = $http_exclusions_bulk[ $test_id ] ?? [
                        'http_excluded_count' => 0,
                        'http_excluded_last'  => '',
                    ];
                    $health = abtestkit_pt_health_summary( $test, $test_stats, $http_exclusion_context );

                    $out[] = [
                        'id'              => isset( $test['id'] ) ? (string) $test['id'] : '',
                        'name'            => isset( $test['title'] ) ? (string) $test['title'] : '',
                        'kind'            => $kind,
                        'kind_label'      => $kind_label,
                        'testing_title'   => $testing_title,
                        'status'          => isset( $test['status'] ) ? (string) $test['status'] : 'paused',
                        'decision_rule'   => isset( $test['decision_rule'] ) ? (string) $test['decision_rule'] : 'balanced',
                        'decision_mode'   => isset( $test['decision_mode'] ) ? (string) $test['decision_mode'] : 'auto',
                        'min_impressions' => isset( $test['min_impressions'] ) ? (int) $test['min_impressions'] : 50,
                        'min_conversions' => isset( $test['min_conversions'] ) ? (int) $test['min_conversions'] : 5,
                        'started_at'      => isset( $test['started_at'] ) ? (int) $test['started_at'] : 0,
                        'finished_at'     => isset( $test['finished_at'] ) ? (int) $test['finished_at'] : 0,
                        'winner'          => abtestkit_pt_winner_for_dashboard( $test ),
                        'goal'            => isset( $test['goal'] ) ? abtestkit_pt_normalize_goal_for_display( $test['goal'] ) : '',
                        'scroll_depth'    => abtestkit_pt_scroll_depth_for_test( $test ),
                        'css_scope'       => $custom_css_data['css_scope'],
                        'custom_css'      => $custom_css_data['custom_css'],
                        'css_markers'     => $custom_css_data['css_markers'],
                        'html_scope'       => $custom_html_data['html_scope'],
                        'html_changes'     => $custom_html_data['html_changes'],
                        'stats'           => $test_stats,
                        'health'          => $health,
                        'http_excluded_count' => (int) $http_exclusion_context['http_excluded_count'],
                        'http_excluded_last'  => (string) $http_exclusion_context['http_excluded_last'],
                        'auto_paused_broken'    => ! empty( $test['auto_paused_broken'] ),
                        'auto_paused_broken_at' => isset( $test['auto_paused_broken_at'] ) ? (int) $test['auto_paused_broken_at'] : 0,
                        'url'             => $url,
                        'performance_url' => add_query_arg(
                            'test_id',
                            (string) $test_id,
                            admin_url( 'admin.php?page=abtestkit-test-performance' )
                        ),
                        // Legacy: keep preview_url pointing at Version A (Version A permalink or forced A for products)
                        'preview_url'     => $preview_a,
                        // New: explicit admin preview URLs for A/B
                        'preview_a'       => $preview_a,
                        'preview_b'       => $preview_b,
                    ];
                }

                return rest_ensure_response( $out );
            },
        ]
    );

    // --- Test performance: update test title ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/update-title',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {

                $test_id = sanitize_text_field( (string) $req->get_param( 'test_id' ) );
                $title   = sanitize_text_field( (string) $req->get_param( 'title' ) );

                if ( $test_id === '' ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_test_id',
                        ]
                    );
                }

                if ( $title === '' ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_title',
                        ]
                    );
                }

                $test = abtestkit_pt_get( $test_id );
                if ( ! is_array( $test ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'not_found',
                        ]
                    );
                }

                $test['title'] = $title;
                abtestkit_pt_put( $test );

                return rest_ensure_response(
                    [
                        'ok'    => true,
                        'title' => $title,
                        'test'  => [
                            'id'    => (string) ( $test['id'] ?? '' ),
                            'title' => (string) ( $test['title'] ?? '' ),
                        ],
                    ]
                );
            },
        ]
    );

    // --- Test performance: compatibility help request ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/compatibility-help',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                $test_id = sanitize_text_field( (string) $req->get_param( 'test_id' ) );
                $message = sanitize_textarea_field( (string) $req->get_param( 'message' ) );
                $source  = sanitize_key( (string) $req->get_param( 'source' ) );

                if ( $test_id === '' ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_test_id',
                        ]
                    );
                }

                if ( ! function_exists( 'abtestkit_telemetry_track_compatibility_help_request' ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_handler',
                        ]
                    );
                }

                $sent = abtestkit_telemetry_track_compatibility_help_request( $test_id, $message, $source );

                if ( ! $sent ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'not_found',
                        ]
                    );
                }

                return rest_ensure_response(
                    [
                        'ok' => true,
                    ]
                );
            },
        ]
    );

    // --- Test performance: single test details + timeline ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/performance',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                global $wpdb;

                $test_id = sanitize_text_field( (string) $req->get_param( 'test_id' ) );
                $range   = sanitize_key( (string) $req->get_param( 'range' ) );

                if ( ! in_array( $range, [ 'day', 'week', 'month' ], true ) ) {
                    $range = 'day';
                }

                if ( $test_id === '' ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_test_id',
                        ]
                    );
                }

                $test = abtestkit_pt_get( $test_id );
                if ( ! is_array( $test ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'not_found',
                        ]
                    );
                }

                $cache_key = abtestkit_pt_performance_cache_key( $test_id, $range );
                $cached    = get_transient( $cache_key );

                if ( is_array( $cached ) && ! empty( $cached['ok'] ) && ! empty( $cached['test'] ) ) {
                    $cached_targets = abtestkit_pt_click_targets_for_test( $test );
                    $cached_goal    = isset( $test['goal'] )
                        ? abtestkit_pt_normalize_goal_for_display( $test['goal'] )
                        : '';

                    $cached['test']['goal']                  = $cached_goal;
                    $cached['test']['links']                 = $cached_targets;
                    $cached['test']['click_targets']         = $cached_targets;
                    $cached_custom_css_data                  = abtestkit_custom_css_data_for_test( $test );
                    $cached['test']['scroll_depth']          = abtestkit_pt_scroll_depth_for_test( $test );
                    $cached['test']['css_scope']             = $cached_custom_css_data['css_scope'];
                    $cached['test']['custom_css']            = $cached_custom_css_data['custom_css'];
                    $cached['test']['css_markers']           = $cached_custom_css_data['css_markers'];
                    $cached_custom_html_data                 = abtestkit_custom_html_data_for_test( $test );
                    $cached['test']['html_scope']             = $cached_custom_html_data['html_scope'];
                    $cached['test']['html_changes']           = $cached_custom_html_data['html_changes'];
                    $cached['test']['status']                = isset( $test['status'] ) ? (string) $test['status'] : 'paused';
                    $cached['test']['auto_paused_broken']    = ! empty( $test['auto_paused_broken'] );
                    $cached['test']['auto_paused_broken_at'] = isset( $test['auto_paused_broken_at'] ) ? (int) $test['auto_paused_broken_at'] : 0;
                    $cached['test']['engagement']            = abtestkit_pt_engagement_stats( $test_id );

                    $cached_stats = ( isset( $cached['test']['stats'] ) && is_array( $cached['test']['stats'] ) )
                        ? $cached['test']['stats']
                        : abtestkit_pt_stats( $test );

                    $cached['test']['evaluation'] = abtestkit_pt_evaluation_for_test( $test );

                    $cached['test']['health'] = abtestkit_pt_health_summary( $test, $cached_stats, [
                        'http_excluded_count' => isset( $cached['test']['http_excluded_count'] ) ? (int) $cached['test']['http_excluded_count'] : 0,
                        'http_excluded_last'  => isset( $cached['test']['http_excluded_last'] ) ? (string) $cached['test']['http_excluded_last'] : '',
                    ] );

                    return rest_ensure_response( $cached );
                }

                $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
                $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
                $kind       = isset( $test['kind'] ) ? (string) $test['kind'] : 'page';
                $url        = $control_id > 0 ? get_permalink( $control_id ) : '';

                $preview_a = '';
                $preview_b = '';

                if ( $control_id > 0 ) {
                    $base = get_permalink( $control_id );

                    if ( in_array( $kind, [ 'custom_css', 'custom_html' ], true ) ) {
                        $preview_a = add_query_arg(
                            [
                                'abtestkit_preview' => '1',
                                'abtestkit_force'   => 'A',
                            ],
                            $base
                        );

                        $preview_b = add_query_arg(
                            [
                                'abtestkit_preview' => '1',
                                'abtestkit_force'   => 'B',
                            ],
                            $base
                        );
                    } elseif ( $kind === 'product' ) {
                        $preview_a = add_query_arg(
                            [
                                'abtestkit_preview' => '1',
                                'abtestkit_force'   => 'A',
                            ],
                            $base
                        );

                        $preview_b_args = [
                            'abtestkit_preview' => '1',
                            'abtestkit_force'   => 'B',
                        ];

                        if ( $variant_id > 0 ) {
                            $preview_b_args['abtestkit_shadow_preview_id'] = (int) $variant_id;
                        }

                        $preview_b = add_query_arg( $preview_b_args, $base );
                    } else {
                        $preview_a = add_query_arg( 'abtestkit_preview', '1', $base );

                        if ( $variant_id > 0 ) {
                            $preview_b = add_query_arg(
                                'abtestkit_preview',
                                '1',
                                get_permalink( $variant_id )
                            );
                        }
                    }
                }

                $timeline = [];
                $http_excluded_count = 0;
                $http_excluded_last  = '';

                if ( $test_id !== '' ) {
                    $table = ABTESTKIT_EVENTS_TABLE;

                    // Keep timeline queries bounded on slow sites.
                    $days_back = abtestkit_pt_timeline_days_back( $range );
                    $from_date = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . $days_back . ' days', current_time( 'timestamp' ) ) );

                    if ( abtestkit_events_table_has_column( 'amount' ) ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $rows = $wpdb->get_results(
                            $wpdb->prepare(
                                "
                                SELECT
                                    DATE(`time`) AS bucket,
                                    variant,
                                    event_type,
                                    protocol,
                                    excluded_reason,
                                    COUNT(*) AS total,
                                    COALESCE(SUM(amount), 0) AS revenue,
                                    MAX(`time`) AS last_seen
                                FROM %i
                                WHERE ab_test_id = %s
                                  AND (
                                        event_type IN ('impression', 'click', 'purchase')
                                        OR (
                                            event_type = 'protocol_warning'
                                            AND protocol = 'http'
                                            AND excluded_reason = 'http'
                                        )
                                  )
                                  AND `time` >= %s
                                GROUP BY bucket, variant, event_type, protocol, excluded_reason
                                ORDER BY bucket ASC
                                ",
                                $table,
                                $test_id,
                                $from_date
                            ),
                            ARRAY_A
                        );
                    } else {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $rows = $wpdb->get_results(
                            $wpdb->prepare(
                                "
                                SELECT
                                    DATE(`time`) AS bucket,
                                    variant,
                                    event_type,
                                    protocol,
                                    excluded_reason,
                                    COUNT(*) AS total,
                                    0 AS revenue,
                                    MAX(`time`) AS last_seen
                                FROM %i
                                WHERE ab_test_id = %s
                                  AND (
                                        event_type IN ('impression', 'click', 'purchase')
                                        OR (
                                            event_type = 'protocol_warning'
                                            AND protocol = 'http'
                                            AND excluded_reason = 'http'
                                        )
                                  )
                                  AND `time` >= %s
                                GROUP BY bucket, variant, event_type, protocol, excluded_reason
                                ORDER BY bucket ASC
                                ",
                                $table,
                                $test_id,
                                $from_date
                            ),
                            ARRAY_A
                        );
                    }

                    $daily_indexed = [];

                    foreach ( (array) $rows as $row ) {
                        $bucket          = isset( $row['bucket'] ) ? (string) $row['bucket'] : '';
                        $variant         = isset( $row['variant'] ) ? (string) $row['variant'] : '';
                        $event_type      = isset( $row['event_type'] ) ? (string) $row['event_type'] : '';
                        $protocol        = isset( $row['protocol'] ) ? (string) $row['protocol'] : '';
                        $excluded_reason = isset( $row['excluded_reason'] ) ? (string) $row['excluded_reason'] : '';
                        $total           = isset( $row['total'] ) ? (int) $row['total'] : 0;
                        $revenue         = isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0;
                        $last_seen       = isset( $row['last_seen'] ) ? (string) $row['last_seen'] : '';

                        if ( $event_type === 'protocol_warning' && $protocol === 'http' && $excluded_reason === 'http' ) {
                            $http_excluded_count += $total;

                            if ( $last_seen !== '' && ( $http_excluded_last === '' || strtotime( $last_seen ) > strtotime( $http_excluded_last ) ) ) {
                                $http_excluded_last = $last_seen;
                            }

                            continue;
                        }

                        if ( $bucket === '' || ! in_array( $variant, [ 'A', 'B' ], true ) ) {
                            continue;
                        }

                        if ( ! isset( $daily_indexed[ $bucket ] ) ) {
                            $daily_indexed[ $bucket ] = [
                                'bucket' => $bucket,
                                'A'      => [
                                    'impressions' => 0,
                                    'clicks'      => 0,
                                    'purchases'   => 0,
                                    'revenue'     => 0.0,
                                ],
                                'B'      => [
                                    'impressions' => 0,
                                    'clicks'      => 0,
                                    'purchases'   => 0,
                                    'revenue'     => 0.0,
                                ],
                            ];
                        }

                        if ( $event_type === 'impression' ) {
                            $daily_indexed[ $bucket ][ $variant ]['impressions'] = $total;
                        } elseif ( $event_type === 'click' ) {
                            $daily_indexed[ $bucket ][ $variant ]['clicks'] = $total;
                        } elseif ( $event_type === 'purchase' ) {
                            $daily_indexed[ $bucket ][ $variant ]['purchases'] = $total;
                            $daily_indexed[ $bucket ][ $variant ]['revenue']   = round( $revenue, 2 );
                        }
                    }

                    $start_ts = isset( $test['started_at'] ) ? (int) $test['started_at'] : 0;
                    $end_ts   = isset( $test['finished_at'] ) ? (int) $test['finished_at'] : 0;

                    $first_bucket = array_key_first( $daily_indexed );
                    if ( $first_bucket ) {
                        $first_bucket_ts = strtotime( $first_bucket . ' 00:00:00' );

                        // Always include the earliest recorded event bucket in the chart,
                        // even if started_at was previously shifted forward by pause/resume.
                        if ( $start_ts <= 0 || ( $first_bucket_ts && $first_bucket_ts < $start_ts ) ) {
                            $start_ts = $first_bucket_ts;
                        }
                    }

                    if ( $end_ts <= 0 ) {
                        $end_ts = current_time( 'timestamp' );
                    }

                    $daily_timeline = [];

                    if ( $start_ts > 0 && $end_ts > 0 && $end_ts >= $start_ts ) {
                        $cursor = strtotime( gmdate( 'Y-m-d 00:00:00', $start_ts ) );
                        $end_day = strtotime( gmdate( 'Y-m-d 00:00:00', $end_ts ) );

                        while ( $cursor <= $end_day ) {
                            $bucket = gmdate( 'Y-m-d', $cursor );

                            $daily_timeline[] = isset( $daily_indexed[ $bucket ] )
                                ? $daily_indexed[ $bucket ]
                                : [
                                    'bucket' => $bucket,
                                    'A'      => [
                                        'impressions' => 0,
                                        'clicks'      => 0,
                                        'purchases'   => 0,
                                        'revenue'     => 0.0,
                                    ],
                                    'B'      => [
                                        'impressions' => 0,
                                        'clicks'      => 0,
                                        'purchases'   => 0,
                                        'revenue'     => 0.0,
                                    ],
                                ];

                            $cursor = strtotime( '+1 day', $cursor );
                        }
                    } else {
                        $daily_timeline = array_values( $daily_indexed );
                    }

                    if ( $range === 'day' ) {
                        $timeline = $daily_timeline;
                    } else {
                        $rolled = [];

                        foreach ( $daily_timeline as $day_row ) {
                            $day_bucket = isset( $day_row['bucket'] ) ? (string) $day_row['bucket'] : '';
                            if ( $day_bucket === '' ) {
                                continue;
                            }

                            $day_ts = strtotime( $day_bucket . ' 00:00:00' );
                            if ( false === $day_ts ) {
                                continue;
                            }

                            if ( $range === 'month' ) {
                                $rollup_bucket = gmdate( 'Y-m-01', $day_ts );
                            } else {
                                $weekday       = (int) gmdate( 'N', $day_ts );
                                $rollup_bucket = gmdate( 'Y-m-d', strtotime( '-' . ( $weekday - 1 ) . ' days', $day_ts ) );
                            }

                            if ( ! isset( $rolled[ $rollup_bucket ] ) ) {
                                $rolled[ $rollup_bucket ] = [
                                    'bucket' => $rollup_bucket,
                                    'A'      => [
                                        'impressions' => 0,
                                        'clicks'      => 0,
                                        'purchases'   => 0,
                                        'revenue'     => 0.0,
                                    ],
                                    'B'      => [
                                        'impressions' => 0,
                                        'clicks'      => 0,
                                        'purchases'   => 0,
                                        'revenue'     => 0.0,
                                    ],
                                ];
                            }

                            $rolled[ $rollup_bucket ]['A']['impressions'] += isset( $day_row['A']['impressions'] ) ? (int) $day_row['A']['impressions'] : 0;
                            $rolled[ $rollup_bucket ]['A']['clicks']      += isset( $day_row['A']['clicks'] ) ? (int) $day_row['A']['clicks'] : 0;
                            $rolled[ $rollup_bucket ]['A']['purchases']   += isset( $day_row['A']['purchases'] ) ? (int) $day_row['A']['purchases'] : 0;
                            $rolled[ $rollup_bucket ]['A']['revenue']     += isset( $day_row['A']['revenue'] ) ? (float) $day_row['A']['revenue'] : 0.0;

                            $rolled[ $rollup_bucket ]['B']['impressions'] += isset( $day_row['B']['impressions'] ) ? (int) $day_row['B']['impressions'] : 0;
                            $rolled[ $rollup_bucket ]['B']['clicks']      += isset( $day_row['B']['clicks'] ) ? (int) $day_row['B']['clicks'] : 0;
                            $rolled[ $rollup_bucket ]['B']['purchases']   += isset( $day_row['B']['purchases'] ) ? (int) $day_row['B']['purchases'] : 0;
                            $rolled[ $rollup_bucket ]['B']['revenue']     += isset( $day_row['B']['revenue'] ) ? (float) $day_row['B']['revenue'] : 0.0;
                        }

                        foreach ( $rolled as $bucket_key => $bucket_row ) {
                            $rolled[ $bucket_key ]['A']['revenue'] = round( (float) $bucket_row['A']['revenue'], 2 );
                            $rolled[ $bucket_key ]['B']['revenue'] = round( (float) $bucket_row['B']['revenue'], 2 );
                        }

                        ksort( $rolled );
                        $timeline = array_values( $rolled );
                    }
                }

                if ( ! empty( $wpdb->last_error ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'db_error',
                            'debug' => $wpdb->last_error,
                        ]
                    );
                }

                /*
                 * Health is independent of the selected chart range. Use the same
                 * lifetime HTTP-exclusion aggregate as the dashboard so changing
                 * Day/Week/Month cannot change a test's health status.
                 */
                $http_exclusions = abtestkit_pt_http_exclusions_bulk( [ $test ] );
                $http_context    = $http_exclusions[ $test_id ] ?? [
                    'http_excluded_count' => 0,
                    'http_excluded_last'  => '',
                ];

                $http_excluded_count = (int) $http_context['http_excluded_count'];
                $http_excluded_last  = (string) $http_context['http_excluded_last'];

                if ( ! empty( $wpdb->last_error ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'db_error',
                            'debug' => $wpdb->last_error,
                        ]
                    );
                }

                $stats = abtestkit_pt_stats( $test );

                if ( ! empty( $wpdb->last_error ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'db_error',
                            'debug' => $wpdb->last_error,
                        ]
                    );
                }

                $evaluation = abtestkit_pt_evaluation_for_test( $test );

                $goal = '';
                if ( isset( $test['goal'] ) ) {
                    $goal = abtestkit_pt_normalize_goal_for_display( $test['goal'] );
                }

                $click_targets = abtestkit_pt_click_targets_for_test( $test );

                $kind_label = ( $kind === 'custom_html' )
                    ? 'Custom HTML'
                    : (
                        ( $kind === 'custom_css' )
                            ? 'Custom CSS'
                            : (
                                ( $kind === 'reusable_section' )
                                    ? 'Reusable Section'
                                    : ucfirst( str_replace( '_', ' ', $kind ) )
                            )
                    );

                $custom_css_data = ( $kind === 'custom_css' )
                    ? abtestkit_custom_css_data_for_test( $test )
                    : [
                        'css_scope'   => '',
                        'custom_css'  => '',
                        'css_markers' => [],
                    ];

                $custom_html_data = ( $kind === 'custom_html' )
                    ? abtestkit_custom_html_data_for_test( $test )
                    : [
                        'html_scope'   => '',
                        'html_changes' => [],
                    ];

                $testing_title = $control_id > 0 ? get_the_title( $control_id ) : '';

                $health = abtestkit_pt_health_summary( $test, $stats, [
                    'http_excluded_count' => $http_excluded_count,
                    'http_excluded_last'  => $http_excluded_last,
                ] );

                $response = [
                    'ok' => true,
                    'test' => [
                        'id'                  => isset( $test['id'] ) ? (string) $test['id'] : '',
                        'title'               => isset( $test['title'] ) ? (string) $test['title'] : '',
                        'kind'                => $kind,
                        'kind_label'          => $kind_label,
                        'testing_title'       => $testing_title,
                        'status'              => isset( $test['status'] ) ? (string) $test['status'] : 'paused',
                        'goal'                => $goal,
                        'links'               => $click_targets,
                        'click_targets'       => $click_targets,
                        'scroll_depth'        => abtestkit_pt_scroll_depth_for_test( $test ),
                        'css_scope'           => $custom_css_data['css_scope'],
                        'custom_css'          => $custom_css_data['custom_css'],
                        'css_markers'         => $custom_css_data['css_markers'],
                        'html_scope'           => $custom_html_data['html_scope'],
                        'html_changes'         => $custom_html_data['html_changes'],
                        'evaluation'          => $evaluation,
                        'decision_rule'       => isset( $test['decision_rule'] ) ? (string) $test['decision_rule'] : 'balanced',
                        'decision_mode'       => isset( $test['decision_mode'] ) ? (string) $test['decision_mode'] : 'auto',
                        'min_impressions'     => isset( $test['min_impressions'] ) ? (int) $test['min_impressions'] : 50,
                        'min_conversions'     => isset( $test['min_conversions'] ) ? (int) $test['min_conversions'] : 5,
                        'started_at'          => isset( $test['started_at'] ) ? (int) $test['started_at'] : 0,
                        'finished_at'         => isset( $test['finished_at'] ) ? (int) $test['finished_at'] : 0,
                        'winner'              => isset( $test['winner'] ) ? (string) $test['winner'] : '',
                        'url'                 => $url ? (string) $url : '',
                        'preview_a'           => $preview_a ? (string) $preview_a : '',
                        'preview_b'           => $preview_b ? (string) $preview_b : '',
                        'control_id'          => $control_id,
                        'variant_id'          => $variant_id,
                        'stats'               => $stats,
                        'engagement'          => abtestkit_pt_engagement_stats( $test_id ),
                        'health'              => $health,
                        'auto_paused_broken'    => ! empty( $test['auto_paused_broken'] ),
                        'auto_paused_broken_at' => isset( $test['auto_paused_broken_at'] ) ? (int) $test['auto_paused_broken_at'] : 0,
                        'timeline'            => $timeline,
                        'http_excluded_count' => $http_excluded_count,
                        'http_excluded_last'  => $http_excluded_last,
                    ],
                ];

                set_transient(
                    $cache_key,
                    $response,
                    abtestkit_pt_performance_cache_ttl( $range )
                );

                return rest_ensure_response( $response );
            },
        ]
    );

    // --- Test performance: reset a whole test's metrics ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/reset-test',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                global $wpdb;

                $test_id = sanitize_text_field( (string) $req->get_param( 'test_id' ) );
                if ( $test_id === '' ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'missing_test_id',
                        ]
                    );
                }

                $test = abtestkit_pt_get( $test_id );
                if ( ! is_array( $test ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'not_found',
                        ]
                    );
                }

                $table = ABTESTKIT_EVENTS_TABLE;

                // Delete by test ID only so reset clears the same data source used by cards and timeline.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM %i
                         WHERE ab_test_id = %s",
                        $table,
                        $test_id
                    )
                );

                $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
                $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
                $kind       = isset( $test['kind'] ) ? (string) $test['kind'] : 'page';

                $post_ids = [ $control_id ];
                if ( $kind !== 'product' && $variant_id > 0 ) {
                    $post_ids[] = $variant_id;
                }
                $post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );

                foreach ( $post_ids as $pid ) {
                    wp_cache_delete( "stats:$pid:$test_id", 'abtestkit_stats' );
                    wp_cache_delete( "stats_multi:$pid", 'abtestkit_stats' );
                }

                return rest_ensure_response(
                    [
                        'ok'      => true,
                        'deleted' => (int) $deleted,
                    ]
                );
            },
        ]
    );

    // --- Page Test Wizard: delete an unused temporary duplicate/shadow created during the wizard ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/cleanup-duplicate',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                $control_id    = absint( $req->get_param( 'control_id' ) );
                $variant_id    = absint( $req->get_param( 'variant_id' ) );
                $user_id       = (int) get_current_user_id();
                $post_type     = $control_id ? get_post_type( $control_id ) : '';
                $allowed_types = [ 'page', 'post', 'product' ];

                if ( ! $control_id || ! in_array( $post_type, $allowed_types, true ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'invalid_control',
                        ]
                    );
                }

                if ( $variant_id <= 0 ) {
                    $variant_id = abtestkit_pt_get_last_duplicate_for_user( (int) $control_id, (int) $user_id );
                }

                if ( $variant_id <= 0 ) {
                    abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) $user_id );

                    return rest_ensure_response(
                        [
                            'ok'               => true,
                            'deleted'          => 0,
                            'deleted_children' => 0,
                        ]
                    );
                }

                $variant = get_post( $variant_id );

                if ( ! $variant ) {
                    abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) $user_id );

                    return rest_ensure_response(
                        [
                            'ok'               => true,
                            'deleted'          => 0,
                            'deleted_children' => 0,
                        ]
                    );
                }

                if ( $variant->post_type !== $post_type ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'invalid_variant',
                        ]
                    );
                }

                if ( abtestkit_pt_variant_id_in_any_test( (int) $variant_id ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'variant_in_use',
                        ]
                    );
                }

                if ( ! function_exists( 'abtestkit_is_shadow_variant' ) || ! abtestkit_is_shadow_variant( (int) $variant_id ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'invalid_variant',
                        ]
                    );
                }

                $shadow_of = (int) get_post_meta( $variant_id, '_abtestkit_shadow_of', true );
                if ( $shadow_of !== (int) $control_id ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'invalid_variant',
                        ]
                    );
                }

                $deleted_children = 0;

                // Variable-product shadows can have shadow child variations.
                // Delete those first so product cleanup is complete and reliable.
                if ( $post_type === 'product' ) {
                    $child_ids = get_children(
                        [
                            'post_parent'    => (int) $variant_id,
                            'post_type'      => 'product_variation',
                            'post_status'    => 'any',
                            'fields'         => 'ids',
                            'posts_per_page' => -1,
                        ]
                    );

                    if ( is_array( $child_ids ) && ! empty( $child_ids ) ) {
                        foreach ( $child_ids as $child_id ) {
                            $child_id = (int) $child_id;
                            if ( $child_id <= 0 ) {
                                continue;
                            }

                            $child_deleted = wp_delete_post( $child_id, true );
                            if ( $child_deleted ) {
                                $deleted_children++;
                            }
                        }
                    }
                }

                $deleted = wp_delete_post( $variant_id, true );

                if ( ! $deleted ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'delete_failed',
                        ]
                    );
                }

                abtestkit_pt_clear_last_duplicate_for_user( (int) $control_id, (int) $user_id );

                if ( function_exists( 'abtestkit_clear_shadow_counts_cache' ) ) {
                    abtestkit_clear_shadow_counts_cache( $post_type );
                }

                clean_post_cache( (int) $variant_id );
                clean_post_cache( (int) $control_id );

                return rest_ensure_response(
                    [
                        'ok'               => true,
                        'deleted'          => (int) $variant_id,
                        'deleted_children' => (int) $deleted_children,
                    ]
                );
            },
        ]
    );

    // --- Page Test Wizard: duplicate Version A now (returns the new page so you can edit it) ---
    register_rest_route(
        'abtestkit/v1',
        '/pt/duplicate',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => function ( WP_REST_Request $req ) {
                $control_id          = absint( $req->get_param( 'control_id' ) );
                $requested_test_type = sanitize_key( (string) $req->get_param( 'test_type' ) );
                $post_type           = $control_id ? get_post_type( $control_id ) : '';
                $allowed_types       = [ 'page', 'post', 'product' ];

                if ( ! $control_id || ! in_array( $post_type, $allowed_types, true ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'invalid_control',
                        ]
                    );
                }

                // Block creating a Version B draft if this page/product is already in a running test.
                // For reusable-section tests, also block if this source page is embedded inside
                // another running page/product/post test.
                $conflict_kind = ( $requested_test_type === 'reusable_section' ) ? 'reusable_section' : $post_type;
                $conflicts     = abtestkit_pt_conflicts_for_pages( (int) $control_id, 0, '', $conflict_kind );
                if ( ! empty( $conflicts ) ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'conflict_running',
                            'info'  => [
                                'message'   => 'This page is already in a running test, or a reusable section test is causing a conflict.',
                                'conflicts' => $conflicts,
                            ],
                        ]
                    );
                }

                $user_id = (int) get_current_user_id();

                // If this user already duplicated this control in the current wizard session,
                // return the same B post instead of creating another clone.
                $variant_id = abtestkit_pt_get_last_duplicate_for_user( (int) $control_id, (int) $user_id );

                if ( ! $variant_id ) {
                    $variant_id = abtestkit_duplicate_post_deep( $control_id );
                    if ( $variant_id ) {
                        abtestkit_pt_set_last_duplicate_for_user( (int) $control_id, (int) $variant_id, (int) $user_id );
                    }
                }

                if ( ! $variant_id ) {
                    return rest_ensure_response(
                        [
                            'ok'    => false,
                            'error' => 'duplicate_failed',
                        ]
                    );
                }

                if (
                    $post_type === 'product'
                    && function_exists( 'abtestkit_pt_sync_shadow_product_type' )
                ) {
                    abtestkit_pt_sync_shadow_product_type( (int) $control_id, (int) $variant_id );
                }

                $preview_url = '';

                if ( $post_type !== 'product' ) {
                    if ( function_exists( 'get_preview_post_link' ) ) {
                        $preview_url = (string) get_preview_post_link( $variant_id );
                    }

                    if ( $preview_url === '' ) {
                        $preview_url = (string) get_permalink( $variant_id );
                    }

                    if ( $preview_url !== '' ) {
                        $preview_url = add_query_arg( 'abtestkit_preview', '1', $preview_url );
                    }
                }

                $page = [
                    'id'          => (int) $variant_id,
                    'title'       => get_the_title( $variant_id ),
                    'status'      => get_post_status( $variant_id ),
                    'preview_url' => $preview_url,
                    'date'        => get_the_date( 'Y/m/d', $variant_id ),
                    'date_iso'    => get_post_field( 'post_date', $variant_id ),
                ];

                return rest_ensure_response(
                    [
                        'ok'   => true,
                        'page' => $page,
                    ]
                );
            },
        ]
    );
} );


/**
 * Load block-config.json into a PHP array for localization.
 */
function abtestkit_load_block_config() {
    // Make sure block-config.json lives under assets/js/, next to editor.js & ab-sidebar.js
    $config_path = plugin_dir_path( __FILE__ ) . 'assets/js/block-config.json';
    if ( ! file_exists( $config_path ) ) {
        return [];
    }
    $json = file_get_contents( $config_path );
    $arr  = json_decode( $json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return [];
    }
    return $arr;
}

function abtestkit_make_sig( int $post_id, int $ts ): string {
    return hash_hmac('sha256', $post_id . '|' . $ts, wp_salt('auth'));
}

function abtestkit_sanitize_test_id( $id ): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $id);
}

function abtestkit_rest_check_nonce(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) return false;
    return wp_verify_nonce($nonce, 'wp_rest') === 1;
}

function abtestkit_rest_permission( WP_REST_Request $request ) {
    return abtestkit_rest_check_nonce( $request ) && current_user_can( 'manage_options' );
}

function abtestkit_handle_html_runtime_health( WP_REST_Request $request ) {
    if ( ! abtestkit_is_same_origin_request() && ! abtestkit_rest_check_nonce( $request ) ) {
        return new WP_Error(
            'abtestkit_html_health_forbidden',
            __( 'This selector health report could not be verified.', 'abtestkit' ),
            [ 'status' => 403 ]
        );
    }

    $test_id    = abtestkit_sanitize_test_id( $request->get_param( 'test_id' ) );
    $control_id = absint( $request->get_param( 'control_id' ) );
    $test       = $test_id !== '' ? abtestkit_pt_get( $test_id ) : null;

    if (
        ! is_array( $test )
        || ( $test['kind'] ?? '' ) !== 'custom_html'
        || ( $test['status'] ?? '' ) !== 'running'
        || (int) ( $test['control_id'] ?? 0 ) !== $control_id
    ) {
        return new WP_Error(
            'abtestkit_html_health_test_not_found',
            __( 'The running Custom HTML test could not be verified.', 'abtestkit' ),
            [ 'status' => 404 ]
        );
    }

    $changes = isset( $test['html_changes'] )
        ? abtestkit_sanitize_custom_html_changes( $test['html_changes'] )
        : [];
    $total   = count( $changes );
    $matched = max( 0, (int) $request->get_param( 'matched' ) );
    $missing = max( 0, (int) $request->get_param( 'missing' ) );
    $invalid = max( 0, (int) $request->get_param( 'invalid' ) );

    if (
        $total <= 0
        || (int) $request->get_param( 'total' ) !== $total
        || $matched + $missing + $invalid !== $total
    ) {
        return new WP_Error(
            'abtestkit_html_health_invalid_counts',
            __( 'The selector health counts did not match the saved test.', 'abtestkit' ),
            [ 'status' => 400 ]
        );
    }

    $minute_key = 'abtestkit_html_health_' . md5( $test_id . '|' . gmdate( 'YmdHi' ) );
    $requests   = (int) get_transient( $minute_key );

    if ( $requests >= 30 ) {
        return new WP_Error(
            'abtestkit_html_health_rate_limited',
            __( 'Selector health is already up to date. Try again shortly.', 'abtestkit' ),
            [ 'status' => 429 ]
        );
    }

    set_transient( $minute_key, $requests + 1, 2 * MINUTE_IN_SECONDS );

    $record = abtestkit_html_runtime_health_record(
        $test,
        [
            'matched' => $matched,
            'missing' => $missing,
            'invalid' => $invalid,
        ]
    );

    if ( empty( $record ) ) {
        return new WP_Error(
            'abtestkit_html_health_not_saved',
            __( 'The selector health report could not be saved.', 'abtestkit' ),
            [ 'status' => 400 ]
        );
    }

    return rest_ensure_response(
        [
            'ok'     => true,
            'status' => (string) $record['status'],
        ]
    );
}

/**
 * Register REST endpoints: /track, /stats, /evaluate, /reset.
 */
add_action('rest_api_init', function() {
    // Tracking endpoint (anonymous allowed; security handled inside handler)
    register_rest_route('abtestkit/v1', '/track', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'abtestkit_handle_track',
    ]);

    register_rest_route( 'abtestkit/v1', '/pt/html-runtime-health', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'abtestkit_handle_html_runtime_health',
    ] );

    // Secure endpoints (require nonce+capability)
    foreach (['stats', 'evaluate', 'reset'] as $route) {
        register_rest_route('abtestkit/v1', "/$route", [
            'methods'             => $route === 'reset' ? 'POST' : 'GET',
            'permission_callback' => 'abtestkit_rest_permission',
            'callback'            => "abtestkit_handle_$route",
        ]);
    }

    // Capture delete reason from the Installed Plugins screen before WP deletes the plugin.
    register_rest_route('abtestkit/v1', '/delete-reason', [
        'methods'             => 'POST',
        'permission_callback' => function( WP_REST_Request $request ) {
            return abtestkit_rest_check_nonce( $request ) && current_user_can( 'activate_plugins' );
        },
        'callback'            => function( WP_REST_Request $request ) {
            $reason     = sanitize_key( (string) $request->get_param( 'reason' ) );
            $detail     = sanitize_textarea_field( (string) $request->get_param( 'detail' ) );
            $detail_tag = sanitize_key( (string) $request->get_param( 'detail_tag' ) );
            $area       = sanitize_key( (string) $request->get_param( 'area' ) );
            $wizard     = $request->get_param( 'wizard' );
            $wizard     = is_array( $wizard ) ? $wizard : [];

            abtestkit_telemetry_track_plugin_delete_reason( $reason, $detail, $detail_tag, $area, $wizard );

            return rest_ensure_response( [ 'ok' => true ] );
        },
    ]);


    // Admin UI telemetry (wizard friction + existing milestones)
    // - opt-in gated by abtestkit_send_telemetry()
    // - tight allow-list + shallow payload sanitation
    register_rest_route( 'abtestkit/v1', '/telemetry', [
        'methods'             => 'POST',
        'permission_callback' => 'abtestkit_rest_permission',
        'callback'            => function( WP_REST_Request $req ) {

            $event   = sanitize_key( (string) $req->get_param( 'event' ) );
            $payload = $req->get_param( 'payload' );
            $payload = is_array( $payload ) ? $payload : [];

            // Tight allow-list: add new events here only.
            $allowed = [
                // Existing (editor milestones)
                'first_toggle_enabled',
                'first_test_launched',
                'first_test_finished',
                'winner_applied',

                // Wizard milestones (one-shot handled by helper)
                'pt_wizard_opened',

                // Wizard friction (session-based)
                'pt_wizard_session_start',
                'pt_wizard_step',
                'pt_wizard_blocked',
                'pt_wizard_action',
                'pt_wizard_create_attempt',
                'pt_wizard_create_failed',
                'pt_wizard_create_succeeded',
                'pt_wizard_result',
            ];

            if ( ! $event || ! in_array( $event, $allowed, true ) ) {
                return rest_ensure_response( [ 'ok' => false, 'error' => 'unknown_event' ] );
            }

            // Shallow sanitize (keep payload small + inert)
            $san      = [];
            $max_keys = 25;
            $i        = 0;

            foreach ( $payload as $k => $v ) {
                if ( $i++ >= $max_keys ) {
                    break;
                }

                $kk = sanitize_key( (string) $k );
                if ( $kk === '' ) {
                    continue;
                }

                if ( is_scalar( $v ) || $v === null ) {
                    $san[ $kk ] = is_string( $v ) ? sanitize_text_field( (string) $v ) : $v;
                    continue;
                }

                if ( is_array( $v ) ) {
                    $tmp = [];
                    foreach ( array_values( $v ) as $idx => $item ) {
                        if ( $idx >= 10 ) {
                            break;
                        }
                        if ( is_scalar( $item ) || $item === null ) {
                            $tmp[] = is_string( $item ) ? sanitize_text_field( (string) $item ) : $item;
                        }
                    }
                    $san[ $kk ] = $tmp;
                }
            }

            // Special: wizard opened milestone is already implemented as a one-shot helper.
            if ( $event === 'pt_wizard_opened' ) {
                if ( function_exists( 'abtestkit_telemetry_track_pt_wizard_opened' ) ) {
                    abtestkit_telemetry_track_pt_wizard_opened();
                }
                return rest_ensure_response( [ 'ok' => true ] );
            }

            // One-shot gating for editor milestones
            $oneshot = [
                'first_toggle_enabled' => 'first_toggle_enabled',
                'first_test_launched'  => 'first_test_launched',
                'first_test_finished'  => 'first_test_finished',
            ];

            if ( isset( $oneshot[ $event ] ) ) {
                $flag = (string) $oneshot[ $event ];
                if ( ! abtestkit_flag_is_set( $flag ) ) {
                    abtestkit_send_telemetry( $event, $san );
                    abtestkit_mark_flag( $flag );
                }
                return rest_ensure_response( [ 'ok' => true ] );
            }

            // Everything else: send every time (still opt-in gated inside abtestkit_send_telemetry()).
            abtestkit_send_telemetry( $event, $san );

            return rest_ensure_response( [ 'ok' => true ] );
        },
    ] );
});

/**
 * One-off upgrade: remove " (Version B)" suffix from any existing titles created by
 * older versions of the plugin that appended this to duplicated pages/posts/products.
 */
function abtestkit_upgrade_strip_version_b_titles() {
    // Only run once.
    if ( get_option( 'abtestkit_fixed_version_b_titles' ) ) {
        return;
    }

    $suffix     = ' (Version B)';
    $post_types = [ 'page', 'post', 'product' ];

    $args = [
        'post_type'      => $post_types,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        's'              => $suffix, // candidate filter; we still double-check in PHP.
        'fields'         => 'ids',
    ];

    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        foreach ( $query->posts as $post_id ) {
            $title = get_the_title( $post_id );
            if ( substr( $title, -strlen( $suffix ) ) === $suffix ) {
                $clean = substr( $title, 0, -strlen( $suffix ) );
                wp_update_post( [
                    'ID'         => $post_id,
                    'post_title' => $clean,
                ] );
            }
        }
    }

    update_option( 'abtestkit_fixed_version_b_titles', 1 );
}

add_action( 'admin_init', 'abtestkit_upgrade_strip_version_b_titles' );

function abtestkit_log_event_to_db( $type, $post_id, $ab_test_id, $variant, array $extra = [] ) {
    global $wpdb;

    // keep IDs tight and types sane
    $ab_test_id = abtestkit_sanitize_test_id( $ab_test_id );
    $variant    = ( $variant === 'A' || $variant === 'B' ) ? $variant : '';
    $allowed    = [ 'impression', 'click', 'purchase', 'engagement', 'decision', 'decision_applied', 'stale', 'protocol_warning' ];
    $event_type = in_array( $type, $allowed, true ) ? $type : 'impression';

    // Privacy-safe storage: hash IP / UA so we keep light dedupe value without raw personal data.
    // Engagement aggregates do not need either value, so avoid storing them for those samples.
    $ip_raw = $event_type !== 'engagement' && isset( $_SERVER['REMOTE_ADDR'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
        : '';
    $ip     = '';
    if ( filter_var( $ip_raw, FILTER_VALIDATE_IP ) ) {
        $ip = substr( hash_hmac( 'sha256', $ip_raw, wp_salt( 'auth' ) ), 0, 32 );
    }

    $ua_raw = $event_type !== 'engagement' && isset( $_SERVER['HTTP_USER_AGENT'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
        : '';
    $ua     = '';
    if ( $ua_raw !== '' ) {
        $ua = substr( hash_hmac( 'sha256', $ua_raw, wp_salt( 'auth' ) ), 0, 32 );
    }

    $order_id = 0;
    if ( isset( $extra['order_id'] ) ) {
        $order_id = absint( $extra['order_id'] );
    }

    $amount = null;
    if ( isset( $extra['amount'] ) && is_numeric( $extra['amount'] ) ) {
        $amount = number_format( (float) $extra['amount'], 2, '.', '' );
    }

    $protocol = '';
    if ( isset( $extra['protocol'] ) ) {
        $protocol = sanitize_key( (string) $extra['protocol'] );
    }

    $excluded_reason = '';
    if ( isset( $extra['excluded_reason'] ) ) {
        $excluded_reason = sanitize_key( (string) $extra['excluded_reason'] );
    }

    $scroll_pct = 0;
    if ( isset( $extra['scroll_pct'] ) && is_numeric( $extra['scroll_pct'] ) ) {
        $scroll_pct = max( 0, min( 100, (int) round( (float) $extra['scroll_pct'] ) ) );
    }

    $time_sec = 0;
    if ( isset( $extra['time_sec'] ) && is_numeric( $extra['time_sec'] ) ) {
        $time_sec = max( 0, min( 3600, (int) round( (float) $extra['time_sec'] ) ) );
    }

    /*
     * Final safety net:
     * never write normal tracking rows for non-HTTPS frontend requests,
     * even if a higher-level caller reaches this function by mistake.
     */
    if ( $event_type !== 'protocol_warning' ) {
        // Absolute request-level guard.
        if ( ! abtestkit_request_is_real_https() ) {
            return;
        }

        // Caller-provided protocol guard.
        if ( $protocol === 'http' ) {
            return;
        }

        // Matching page-test request-context guard.
        if (
            isset( $GLOBALS['abtestkit_current_pt_assignment'] )
            && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
        ) {
            $ctx = $GLOBALS['abtestkit_current_pt_assignment'];

            $ctx_protocol = isset( $ctx['protocol'] )
                ? sanitize_key( (string) $ctx['protocol'] )
                : '';

            $ctx_no_track = ! empty( $ctx['no_track'] );

            $ctx_test_id = '';
            if ( isset( $ctx['test'] ) && is_array( $ctx['test'] ) && isset( $ctx['test']['id'] ) ) {
                $ctx_test_id = abtestkit_sanitize_test_id( (string) $ctx['test']['id'] );
            }

            if (
                $ctx_test_id !== ''
                && $ctx_test_id === $ab_test_id
                && ( $ctx_no_track || $ctx_protocol === 'http' )
            ) {
                return;
            }
        }
    }

    $now = current_time( 'mysql' );

    $data = [
        'time'            => $now,
        'post_id'         => absint( $post_id ),
        'ab_test_id'      => $ab_test_id,
        'variant'         => $variant,
        'event_type'      => $event_type,
        'order_id'        => $order_id > 0 ? $order_id : null,
        'amount'          => $amount,
        'protocol'        => $protocol !== '' ? $protocol : null,
        'excluded_reason' => $excluded_reason !== '' ? $excluded_reason : null,
        'ip'              => $ip,
        'user_agent'      => $ua,
    ];

    $format = [
        '%s', // time
        '%d', // post_id
        '%s', // ab_test_id
        '%s', // variant
        '%s', // event_type
        '%d', // order_id
        '%s', // amount
        '%s', // protocol
        '%s', // excluded_reason
        '%s', // ip
        '%s', // user_agent
    ];

    if ( $event_type === 'engagement' && abtestkit_events_table_has_column( 'scroll_pct' ) ) {
        $data['scroll_pct'] = $scroll_pct;
        $format[]           = '%d';
    }

    if ( $event_type === 'engagement' && abtestkit_events_table_has_column( 'time_sec' ) ) {
        $data['time_sec'] = $time_sec;
        $format[]         = '%d';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->insert( ABTESTKIT_EVENTS_TABLE, $data, $format );

    if ( $event_type === 'engagement' ) {
        return;
    }

    // Invalidate per-post/test stats cache so dashboard + performance reflect new rows.
    wp_cache_delete( "stats:$post_id:$ab_test_id", 'abtestkit_stats' );
    wp_cache_delete( "stats_multi:$post_id", 'abtestkit_stats' );
    abtestkit_pt_flush_test_caches( (string) $ab_test_id );
}

// Save A/B test variants into post meta on save
add_action('save_post', function ($post_id) {
    if (wp_is_post_revision($post_id)) return;

    $content = get_post_field('post_content', $post_id);
    $blocks = parse_blocks($content);
    $variants = [];

    $extract_variants = function($blocks) use (&$extract_variants, &$variants) {
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['blockName'])) continue;

            $attrs = $block['attrs'] ?? [];
            $abTestId = $attrs['abTestId'] ?? null;
            $abTestVariants = $attrs['abTestVariants'] ?? null;

            if ($abTestId && $abTestVariants && isset($abTestVariants[$abTestId])) {
                $variants[$abTestId] = $abTestVariants[$abTestId];
                $locked = $abTestVariants[$abTestId]['locked'] ?? true;
                $variants[$abTestId]['running'] = !$locked;
            }

            // Recursively scan innerBlocks if needed
            if (!empty($block['innerBlocks'])) {
                $extract_variants($block['innerBlocks']);
            }
        }
    };

    $extract_variants($blocks);
    update_post_meta($post_id, '_abtestkit_variants', $variants);
});

// Frontend-only injection of data-ab-test-id
add_filter('render_block', function ($block_content, $block) {
    if (is_admin()) return $block_content; // don't touch editor

    $name = $block['blockName'] ?? '';
    $supported = ['core/button','core/heading','core/paragraph','core/image'];
    if (!in_array($name, $supported, true)) return $block_content;
    if (!$block_content) return $block_content;

    // Use existing abTestId if present; otherwise make a stable one
    $ab_id = $block['attrs']['abTestId'] ?? '';
    if (!$ab_id) {
        $post_id = get_the_ID() ?: 0;
        $seed = $post_id . '|' . $name . '|' . wp_json_encode($block['attrs'] ?? []);
        $ab_id = 'ab-' . substr(md5($seed), 0, 9);
    }

    // Inject attributes with DOMDocument
    $html = '<div id="__abtest_wrap__">'.$block_content.'</div>';
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $wrap = $dom->getElementById('__abtest_wrap__');
    if (!$wrap || !$wrap->firstChild) return $block_content;

    // Add data-ab-test-id on outermost element
    $root = $wrap->firstChild;
    if ($root instanceof DOMElement && !$root->hasAttribute('data-ab-test-id')) {
        $root->setAttribute('data-ab-test-id', $ab_id);
    }

        // 🔗 Group Sync: if this block is configured to sync, tag the DOM with data-ab-group
    $attrs = $block['attrs'] ?? [];
    if ( ! empty( $attrs['abSync'] ) && ! empty( $attrs['abGroupKey'] ) ) {
        $group = sanitize_key( (string) $attrs['abGroupKey'] );
        if ( $group && $root instanceof DOMElement && ! $root->hasAttribute('data-ab-group') ) {
            $root->setAttribute('data-ab-group', $group);
        }
    }

    // For buttons, also add it on <a.wp-block-button__link>
    if ($name === 'core/button') {
        $xpath = new DOMXPath($dom);
        $aList = $xpath->query('//*[@class and contains(concat(" ", normalize-space(@class), " "), " wp-block-button__link ")]');
        foreach ($aList as $a) {
            if ($a instanceof DOMElement && !$a->hasAttribute('data-ab-test-id')) {
                $a->setAttribute('data-ab-test-id', $ab_id);
            }
        }
    }

    // Return inner HTML
    $out = '';
    foreach ($wrap->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}, 10, 2);

/**
 * Dynamically extend ACF blocks to include abTestId and abTestVariants attributes.
 */
add_filter('register_block_type_args', function($args, $block_name) {
    if ($block_name === 'acf/bv-panel') {
        $args['attributes']['abTestId'] = [
            'type' => 'string',
            'default' => '',
        ];
        $args['attributes']['abTestVariants'] = [
            'type' => 'object',
            'default' => [],
        ];
        $args['attributes']['abTestEnabled'] = [
            'type' => 'boolean',
            'default' => false,
        ];
        $args['attributes']['abTestRunning'] = [
            'type' => 'boolean',
            'default' => false,
        ];
        $args['attributes']['abTestWinner'] = [
            'type' => 'string',
            'default' => '',
        ];
    }
    return $args;
}, 10, 2);


// 3️⃣ Beta + Gamma samplers
function abtestkit_sample_beta($alpha, $beta) {
  $x = abtestkit_sample_gamma($alpha, 1.0);
  $y = abtestkit_sample_gamma($beta,  1.0);
  return $x / ($x + $y);
}

function abtestkit_sample_gamma($shape, $scale) {
  if ($shape >= 1) {
    $d = $shape - 1/3;
    $c = 1 / sqrt(9 * $d);
    while (true) {
      do {
        $u = wp_rand(0, PHP_INT_MAX) / PHP_INT_MAX;
        $v = 1 + $c * abtestkit_normal_rand();
      } while ($v <= 0);
      $v = $v * $v * $v;
      $u2 = wp_rand(0, PHP_INT_MAX) / PHP_INT_MAX;
      if ($u2 < 1 - 0.0331 * pow(abtestkit_normal_rand(), 4)) break;
      if (log($u2) < 0.5 * pow(abtestkit_normal_rand(), 2) + $d * (1 - $v + log($v))) break;
    }
    return $d * $v * $scale;
  }
  $u = wp_rand(0, PHP_INT_MAX) / PHP_INT_MAX;
  return abtestkit_sample_gamma($shape + 1, $scale) * pow( max( $u, 1.0 / PHP_INT_MAX ), 1 / $shape );

}

function abtestkit_normal_rand() {
    static $useLast = false, $y2;

    if ( $useLast ) {
        $useLast = false;
        return $y2;
    }

    // Ensure u1 ∈ (0,1) to avoid log(0)
    do {
        $u1 = wp_rand(0, PHP_INT_MAX) / PHP_INT_MAX;
    } while ($u1 <= 0.0 || $u1 >= 1.0);

    $u2 = wp_rand(0, PHP_INT_MAX) / PHP_INT_MAX;

    $r  = sqrt(-2.0 * log($u1));
    $t  = 2.0 * pi() * $u2;

    $x1 = $r * cos($t);
    $y2 = $r * sin($t);

    $useLast = true;
    return $x1;
}

/**
 * Sample Revenue Per Visitor for purchase-goal tests.
 *
 * Model:
 * - purchase rate ~ Beta posterior
 * - average order value ~ Gamma approximation around observed AOV
 * - RPV sample = sampled purchase rate * sampled AOV
 *
 * This keeps purchase-goal auto-winning focused on money per visitor,
 * while still accounting for uncertainty in both order rate and order value.
 */
function abtestkit_sample_rpv( int $impressions, int $purchases, float $revenue ): float {
    $impressions = max( 0, $impressions );
    $purchases   = max( 0, $purchases );
    $revenue     = max( 0.0, $revenue );

    // Purchase-rate posterior
    $rate_sample = abtestkit_sample_beta(
        1 + $purchases,
        1 + max( 0, $impressions - $purchases )
    );

    // If there are no orders or no revenue, RPV is driven to zero.
    if ( $purchases <= 0 || $revenue <= 0 ) {
        return 0.0;
    }

    $aov = $revenue / $purchases;

    /*
     * Gamma approximation for AOV uncertainty:
     * mean  = AOV
     * var   = AOV^2 / purchases
     * shape = purchases
     * scale = AOV / purchases
     */
    $shape = max( 1, $purchases );
    $scale = max( 0.0001, $aov / $shape );

    $aov_sample = abtestkit_sample_gamma( $shape, $scale );

    return max( 0.0, $rate_sample * $aov_sample );
}

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * PHP-side variant rendering for core/image, core/heading, core/paragraph, core/button
 * Pattern: wrapper [data-ab-test-id], 2 children [data-ab-variant="A|B"] hidden by default
 * ─────────────────────────────────────────────────────────────────────────────
 */
function abtestkit_can_render_variants(array $block): bool {
    $attrs = $block['attrs'] ?? [];
    if (empty($attrs['abTestEnabled']) || empty($attrs['abTestId'])) return false;
    $id = $attrs['abTestId'];
    $all = $attrs['abTestVariants'][$id] ?? [];
    return is_array($all) && isset($all['A']) && isset($all['B']);
}

function abtestkit_pick($arr, $keys, $fallback = '') {
    foreach ((array)$keys as $k) {
        if (isset($arr[$k]) && $arr[$k] !== '') return $arr[$k];
    }
    return $fallback;
}

function abtestkit_clean_id($id) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
}

/**
 * core/image → outputs <figure data-ab-test-id> with two <img data-ab-variant>
 */
add_filter('render_block_core/image', function($html, $block){
    if (!abtestkit_can_render_variants($block)) return $html;

    $attrs   = $block['attrs'];
    $id      = abtestkit_clean_id($attrs['abTestId']);
    $varArr  = $attrs['abTestVariants'][$id];

    $A = $varArr['A'] ?? [];
    $B = $varArr['B'] ?? [];

    // Try common keys; fall back to existing markup’s <img src>
    $existing_src = '';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $existing_src = $m[1];
    }

    $srcA = abtestkit_pick($A, ['url','src','image','img'], $existing_src);
    $srcB = abtestkit_pick($B, ['url','src','image','img'], $existing_src);

    // Alt text if present in variants, otherwise try to scrape from existing
    $altExisting = '';
    if (preg_match('/<img[^>]+alt=["\']([^"\']*)["\']/i', $html, $m)) {
        $altExisting = $m[1];
    }
    $altA = abtestkit_pick($A, ['alt','alt_text'], $altExisting);
    $altB = abtestkit_pick($B, ['alt','alt_text'], $altExisting);

    // Preserve figcaption if present
    $caption = '';
    if (preg_match('/<figcaption[^>]*>.*?<\/figcaption>/is', $html, $m)) {
        $caption = $m[0];
    }

    $groupAttr = '';
    $attrsAll  = $block['attrs'] ?? [];
    if ( ! empty( $attrsAll['abSync'] ) && ! empty( $attrsAll['abGroupKey'] ) ) {
        $groupAttr = ' data-ab-group="' . esc_attr( sanitize_key( (string) $attrsAll['abGroupKey'] ) ) . '"';
    }

    $out  = '<figure class="wp-block-image" data-ab-test-id="' . esc_attr($id) . '"' . $groupAttr . '>';
    $out .= '<img data-ab-variant="A" style="display:none" src="' . esc_url($srcA) . '" alt="' . esc_attr($altA) . '" />';
    $out .= '<img data-ab-variant="B" style="display:none" src="' . esc_url($srcB) . '" alt="' . esc_attr($altB) . '" />';
    $out .= $caption;
    $out .= '</figure>';

    return $out;
}, 10, 2);

/**
 * core/heading → wraps one heading tag containing two spans with variant text
 */
add_filter('render_block_core/heading', function($html, $block){
    if (!abtestkit_can_render_variants($block)) return $html;

    $attrs   = $block['attrs'];
    $id      = abtestkit_clean_id($attrs['abTestId']);
    $varArr  = $attrs['abTestVariants'][$id];
    $A = $varArr['A'] ?? [];
    $B = $varArr['B'] ?? [];

    // Determine tag (h1..h6); fall back to h2
    $tag = 'h2';
    if (preg_match('/<(h[1-6])\b/i', $html, $m)) $tag = strtolower($m[1]);

    // Variant text: prefer 'content' then 'text'; fallback to stripped existing
    $existing = trim( wp_strip_all_tags( $html, true ) );
    $textA = abtestkit_pick($A, ['content','text','html'], $existing);
    $textB = abtestkit_pick($B, ['content','text','html'], $existing);

    $groupAttr = '';
    $attrsAll  = $block['attrs'] ?? [];
    if ( ! empty( $attrsAll['abSync'] ) && ! empty( $attrsAll['abGroupKey'] ) ) {
        $groupAttr = ' data-ab-group="' . esc_attr( sanitize_key( (string) $attrsAll['abGroupKey'] ) ) . '"';
    }
    $out  = '<' . $tag . ' data-ab-test-id="' . esc_attr($id) . '"' . $groupAttr . '>';
    $out .= '<span data-ab-variant="A" style="display:none">' . wp_kses_post($textA) . '</span>';
    $out .= '<span data-ab-variant="B" style="display:none">' . wp_kses_post($textB) . '</span>';
    $out .= '</' . $tag . '>';

    return $out;
}, 10, 2);

/**
 * core/paragraph → renders two spans with variant text inside a <p>
 */
add_filter('render_block_core/paragraph', function($html, $block){
    if (!abtestkit_can_render_variants($block)) return $html;

    $attrs   = $block['attrs'];
    $id      = abtestkit_clean_id($attrs['abTestId']);
    $varArr  = $attrs['abTestVariants'][$id];
    $A = $varArr['A'] ?? [];
    $B = $varArr['B'] ?? [];

    $existing = trim( wp_strip_all_tags( $html, true ) );
    $textA = abtestkit_pick($A, ['content','text','html'], $existing);
    $textB = abtestkit_pick($B, ['content','text','html'], $existing);

    $groupAttr = '';
    $attrsAll  = $block['attrs'] ?? [];
    if ( ! empty( $attrsAll['abSync'] ) && ! empty( $attrsAll['abGroupKey'] ) ) {
     $groupAttr = ' data-ab-group="' . esc_attr( sanitize_key( (string) $attrsAll['abGroupKey'] ) ) . '"';
    }
    $out  = '<p data-ab-test-id="' . esc_attr($id) . '"' . $groupAttr . '>';

    $out .= '<span data-ab-variant="A" style="display:none">' . wp_kses_post($textA) . '</span>';
    $out .= '<span data-ab-variant="B" style="display:none">' . wp_kses_post($textB) . '</span>';
    $out .= '</p>';

    return $out;
}, 10, 2);

/**
 * core/button → your plugin already did this; here’s a hardened version.
 * Keeps original attributes, injects two label spans. (URL can be variant too.)
 */
add_filter('render_block_core/button', function ($html, $block) {
    // Only touch when an AB test is actually running on this button
    if (empty($block['attrs']['abTestEnabled']) || empty($block['attrs']['abTestId'])) {
        return $html;
    }

    $id   = abtestkit_clean_id($block['attrs']['abTestId']);
    $vars = $block['attrs']['abTestVariants'][$id] ?? [];
    if (empty($vars['A']) || empty($vars['B'])) return $html;

    // Parse the existing <a> so we preserve ALL original attributes/classes (incl. Popup Maker)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $a = $dom->getElementsByTagName('a')->item(0);
    if (!$a) return $html;

    // Add AB markers, but DO NOT touch href/class/data-* from the original block
    $a->setAttribute('data-ab-test-id', $id);
    $a->setAttribute('data-ab-index', '0');

    $attrsAll = $block['attrs'] ?? [];
    if ( ! empty( $attrsAll['abSync'] ) && ! empty( $attrsAll['abGroupKey'] ) ) {
        $group = sanitize_key( (string) $attrsAll['abGroupKey'] );
        if ( $group ) {
            $a->setAttribute('data-ab-group', $group);
        }
    }

    // Current visible label (fallback)
    $existing = trim($a->textContent);

    // Compute variant labels (prefer content/text if provided)
    $labelA = '';
    $labelB = '';
    $labelA = isset($vars['A']['content']) ? $vars['A']['content'] :
              (isset($vars['A']['text']) ? $vars['A']['text'] : $existing);
    $labelB = isset($vars['B']['content']) ? $vars['B']['content'] :
              (isset($vars['B']['text']) ? $vars['B']['text'] : $existing);

    // Optional: attach variant URLs to the span as data-href (frontend.js already reads this)
    $urlA = '';
    foreach (['url','myButtonURL','href'] as $k) { if (!empty($vars['A'][$k])) { $urlA = $vars['A'][$k]; break; } }
    $urlB = '';
    foreach (['url','myButtonURL','href'] as $k) { if (!empty($vars['B'][$k])) { $urlB = $vars['B'][$k]; break; } }

    // Remove plain text nodes (keep icons/elements) before inserting our spans
    for ($i = $a->childNodes->length - 1; $i >= 0; $i--) {
        $n = $a->childNodes->item($i);
        if ($n->nodeType === XML_TEXT_NODE) {
            $a->removeChild($n);
        }
    }

    // Build hidden A & B spans (JS reveals the assigned one)
    $spanA = $dom->createElement('span');
    $spanA->setAttribute('data-ab-variant', 'A');
    $spanA->setAttribute('style', 'display:none');
    $spanA->appendChild($dom->createTextNode($labelA));
    if ($urlA !== '') $spanA->setAttribute('data-href', esc_url_raw($urlA));

    $spanB = $dom->createElement('span');
    $spanB->setAttribute('data-ab-variant', 'B');
    $spanB->setAttribute('style', 'display:none');
    $spanB->appendChild($dom->createTextNode($labelB));
    if ($urlB !== '') $spanB->setAttribute('data-href', esc_url_raw($urlB));

    $a->appendChild($spanA);
    $a->appendChild($spanB);

    // Return just the updated fragment
    return $dom->saveHTML($dom->documentElement);
}, 10, 2);

/**
 * ACF block (bv-panel) wrapper injection (minimal): wrap existing HTML with data-ab-test-id
 * and append two transparent markers if you need them later.
 */
add_filter('render_block_acf/bv-panel', function($html, $block){
    if (!abtestkit_can_render_variants($block)) return $html;
    $id = abtestkit_clean_id($block['attrs']['abTestId']);
    // If it already starts with a tag, inject attribute on the first tag.
    if (preg_match('/^<([a-z0-9:-]+)\b/i', $html, $m)) {
        $tag = $m[1];
        return preg_replace(
            '/^<' . preg_quote($tag, '/') . '\b(?![^>]*data-ab-test-id)/i',
            '<' . $tag . ' data-ab-test-id="' . esc_attr($id) . '"',
            $html,
            1
        );
    }
    // Fallback: wrap
    return '<div data-ab-test-id="' . esc_attr($id) . '">' . $html . '</div>';
}, 10, 2);


/**
 * Accept only requests coming from this site (same-origin).
 */
function abtestkit_safe_set_cookie( string $name, string $value, int $expires = 0 ): bool {
    $path     = COOKIEPATH ?: '/';
    $domain   = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure   = abtestkit_request_is_real_https();
    $httponly = true;

    if ( ! headers_sent() ) {
        return setcookie(
            $name,
            $value,
            [
                'expires'  => $expires,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax',
            ]
        );
    }

    // Fallback for WooCommerce flows when output has already started.
    // This avoids frontend warnings while still keeping assignment/click state alive.
    if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
        WC()->session->set( 'abtk_cookie_' . $name, [
            'value'   => $value,
            'expires' => $expires,
        ] );
        return false;
    }

    return false;
}

function abtestkit_safe_get_cookie_value( string $name ): string {
    if ( isset( $_COOKIE[ $name ] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
    }

    if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
        $stored = WC()->session->get( 'abtk_cookie_' . $name );
        if ( is_array( $stored ) && isset( $stored['value'] ) ) {
            return sanitize_text_field( (string) $stored['value'] );
        }
    }

    return '';
}

/**
 * Trackability must reflect the real frontend request, not a misleading proxy hint.
 *
 * Deliberately DO NOT trust HTTP_X_FORWARDED_PROTO / HTTP_X_FORWARDED_SSL here,
 * because this site is proving they can say "https" while the browser is on an
 * actual http:// page.
 */
function abtestkit_request_is_real_https(): bool {
    $allowed_server_keys = [
        'REQUEST_SCHEME',
        'HTTPS',
        'HTTP_X_FORWARDED_PROTO',
        'HTTP_X_FORWARDED_SCHEME',
        'HTTP_X_FORWARDED_SSL',
        'HTTP_FRONT_END_HTTPS',
        'HTTP_X_URL_SCHEME',
        'HTTP_CF_VISITOR',
        'SERVER_PORT',
    ];

    $server_value = static function ( string $key ) use ( $allowed_server_keys ): string {
        if ( ! in_array( $key, $allowed_server_keys, true ) ) {
            return '';
        }

        if ( ! isset( $_SERVER[ $key ] ) ) {
            return '';
        }

        return strtolower( trim( sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) ) );
    };

    $csv_server_value = static function ( string $key ) use ( $server_value ): string {
        $raw = $server_value( $key );
        if ( $raw === '' ) {
            return '';
        }

        $parts = array_map( 'trim', explode( ',', $raw ) );
        return (string) ( $parts[0] ?? '' );
    };

    $scheme            = $csv_server_value( 'REQUEST_SCHEME' );
    $https             = $csv_server_value( 'HTTPS' );
    $forwarded_proto   = $csv_server_value( 'HTTP_X_FORWARDED_PROTO' );
    $forwarded_scheme  = $csv_server_value( 'HTTP_X_FORWARDED_SCHEME' );
    $forwarded_ssl     = $csv_server_value( 'HTTP_X_FORWARDED_SSL' );
    $front_end_https   = $csv_server_value( 'HTTP_FRONT_END_HTTPS' );
    $url_scheme        = $csv_server_value( 'HTTP_X_URL_SCHEME' );
    $cf_visitor_raw    = $server_value( 'HTTP_CF_VISITOR' );
    $cf_visitor_scheme = '';

    if ( $cf_visitor_raw !== '' ) {
        $cf_visitor = json_decode( $cf_visitor_raw, true );
        if ( is_array( $cf_visitor ) && isset( $cf_visitor['scheme'] ) ) {
            $cf_visitor_scheme = strtolower( trim( sanitize_text_field( (string) $cf_visitor['scheme'] ) ) );
        }
    }

    $port_raw = $server_value( 'SERVER_PORT' );
    $port     = ctype_digit( $port_raw ) ? (int) $port_raw : 0;

    /*
     * Be deliberately strict.
     *
     * This site has already proven it can present a real browser HTTP request
     * while PHP still thinks HTTPS is on somewhere upstream.
     *
     * So explicit plain-HTTP signals must win first.
     */
    if ( $scheme === 'http' ) {
        return false;
    }

    if ( $forwarded_proto === 'http' ) {
        return false;
    }

    if ( $forwarded_scheme === 'http' ) {
        return false;
    }

    if ( $url_scheme === 'http' ) {
        return false;
    }

    if ( $forwarded_ssl === 'off' || $forwarded_ssl === '0' ) {
        return false;
    }

    if ( $front_end_https === 'off' || $front_end_https === '0' ) {
        return false;
    }

    if ( $https === 'off' || $https === '0' ) {
        return false;
    }

    if ( $cf_visitor_scheme === 'http' ) {
        return false;
    }

    if ( $port === 80 ) {
        return false;
    }

    if ( $scheme === 'https' ) {
        return true;
    }

    if ( $forwarded_proto === 'https' ) {
        return true;
    }

    if ( $forwarded_scheme === 'https' ) {
        return true;
    }

    if ( $url_scheme === 'https' ) {
        return true;
    }

    if ( $forwarded_ssl === 'on' || $forwarded_ssl === '1' ) {
        return true;
    }

    if ( $front_end_https === 'on' || $front_end_https === '1' ) {
        return true;
    }

    if ( $cf_visitor_scheme === 'https' ) {
        return true;
    }

    if ( $port === 443 ) {
        return true;
    }

    if ( $https === 'on' || $https === '1' ) {
        return true;
    }

    return false;
}

function abtestkit_is_same_origin_request(): bool {
    $origin  = isset( $_SERVER['HTTP_ORIGIN'] )  ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) )  : '';
    $referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';


    $host    = wp_parse_url( home_url(), PHP_URL_HOST );

    if ($origin) {
        $o = wp_parse_url($origin, PHP_URL_HOST);
        if ($o && $o === $host) return true;
    }
    if ($referer) {
        $r = wp_parse_url($referer, PHP_URL_HOST);
        if ($r && $r === $host) return true;
    }
    return false;
}

// Admins (or any role/cap decided via filter) are exempt from tests/tracking.
// They should always see the original (Version A / Control) and not be counted.
function abtestkit_is_exempt_viewer(): bool {
    $is_exempt_cap = is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) );
    /**
     * Filter: allow site owners to widen/narrow who is exempt.
     * Return true to exempt (no test, no logging, show original).
     */
    return (bool) apply_filters( 'abtestkit_is_exempt_viewer', $is_exempt_cap );
}

/**
 * True only for the Custom CSS wizard marker picker preview window.
 *
 * Custom CSS product marker selection now opens the real frontend URL in a
 * popup picker instead of embedding WooCommerce products in an admin iframe.
 */
function abtestkit_is_custom_css_picker_preview_request(): bool {
    return false;
}

add_action( 'template_redirect', function() {
    if ( ! abtestkit_is_custom_css_picker_preview_request() ) {
        return;
    }

    remove_action( 'template_redirect', 'redirect_canonical' );
    add_filter( 'show_admin_bar', '__return_false', 9999 );

    // These constants are deliberately the canonical names used by cache/minify plugins.
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTMINIFY' ) ) {
        define( 'DONOTMINIFY', true );
    }
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

    if ( ! headers_sent() ) {
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'X-Abtestkit-Custom-CSS-Popup-Picker: 1', true );
    }
}, 0 );

add_action( 'wp_footer', function() {
    if ( ! abtestkit_is_custom_css_picker_preview_request() ) {
        return;
    }
    ?>
    <style id="abtestkit-custom-css-popup-picker-style">
        .abtestkit-custom-css-popup-picker-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 2147483647;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 44px;
            padding: 8px 12px;
            background: #1d2327;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 13px;
            line-height: 1.4;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
        }

        .abtestkit-custom-css-popup-picker-toolbar strong {
            font-weight: 700;
        }

        .abtestkit-custom-css-popup-picker-toolbar button {
            min-height: 30px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 4px;
            background: transparent;
            color: #fff;
            cursor: pointer;
            padding: 0 10px;
            font: inherit;
        }

        .abtestkit-custom-css-popup-picker-toolbar button:hover,
        .abtestkit-custom-css-popup-picker-toolbar button:focus {
            border-color: #fff;
            background: rgba(255, 255, 255, 0.12);
        }

        .abtestkit-custom-css-popup-picker-highlight {
            position: fixed;
            z-index: 2147483646;
            pointer-events: none;
            outline: 3px solid #2271b1;
            background: rgba(34, 113, 177, 0.12);
            box-shadow: 0 0 0 99999px rgba(0, 0, 0, 0.04);
            transition: left 80ms ease, top 80ms ease, width 80ms ease, height 80ms ease;
        }

        body.abtestkit-custom-css-popup-picker-active {
            padding-top: 44px !important;
            cursor: crosshair !important;
        }
    </style>
    <script id="abtestkit-custom-css-popup-picker-script">
        (function () {
            if (window.__abtestkitCustomCssPopupPicker) {
                return;
            }

            window.__abtestkitCustomCssPopupPicker = true;

            if (!window.opener || window.opener.closed) {
                return;
            }

            document.body.classList.add("abtestkit-custom-css-popup-picker-active");

            var cssEscape = window.CSS && CSS.escape
                ? CSS.escape
                : function (value) {
                    return String(value || "").replace(/[^a-zA-Z0-9_-]/g, function (match) {
                        return "\\" + match.charCodeAt(0).toString(16) + " ";
                    });
                };

            var ignoredSelector = ".abtestkit-custom-css-popup-picker-toolbar, .abtestkit-custom-css-popup-picker-toolbar *, .abtestkit-custom-css-popup-picker-highlight";

            function nthOfType(el) {
                var index = 1;
                var sibling = el;

                while ((sibling = sibling.previousElementSibling)) {
                    if (sibling.tagName === el.tagName) {
                        index += 1;
                    }
                }

                return index;
            }

            function selectorSegment(el) {
                var tag = String(el.tagName || "").toLowerCase();
                var classes = Array.prototype.slice.call(el.classList || []).filter(function (className) {
                    return className && className.indexOf("abtestkit-") !== 0;
                });

                var preferred = classes.find(function (className) {
                    return /^(elementor-|wp-|woocommerce|wc-|product|single_|summary|entry-|cart|button|btn|price|tabs)/.test(className);
                }) || classes[0] || "";

                var segment = tag || "*";

                if (preferred) {
                    segment += "." + cssEscape(preferred);
                } else {
                    var nth = nthOfType(el);
                    if (nth > 1) {
                        segment += ":nth-of-type(" + nth + ")";
                    }
                }

                return segment;
            }

            function makeSelector(el) {
                if (!el || !el.tagName) {
                    return "";
                }

                if (el.id) {
                    return "#" + cssEscape(el.id);
                }

                var container = el.closest("[data-elementor-type], .elementor, main, article, section, .entry-content, .summary, .woocommerce, body") || document.body;
                var cur = el;
                var parts = [];

                while (cur && cur.nodeType === 1 && parts.length < 8) {
                    if (cur.id) {
                        parts.unshift("#" + cssEscape(cur.id));
                        break;
                    }

                    parts.unshift(selectorSegment(cur));

                    if (cur === container || cur === document.body) {
                        break;
                    }

                    cur = cur.parentElement;
                }

                var selector = parts.join(" > ");

                try {
                    if (selector && document.querySelectorAll(selector).length === 1) {
                        return selector;
                    }
                } catch (_) {}

                cur = el;
                parts = [];

                while (cur && cur.nodeType === 1 && cur !== document.body && parts.length < 10) {
                    if (cur.id) {
                        parts.unshift("#" + cssEscape(cur.id));
                        break;
                    }

                    parts.unshift(selectorSegment(cur));
                    cur = cur.parentElement;
                }

                return parts.join(" > ");
            }

            function textLabel(el) {
                var text = el && el.textContent ? String(el.textContent).replace(/\s+/g, " ").trim() : "";

                if (text) {
                    return text.slice(0, 80);
                }

                if (el && el.getAttribute) {
                    return String(el.getAttribute("aria-label") || el.getAttribute("title") || el.getAttribute("alt") || "").trim().slice(0, 80);
                }

                return "Selected element";
            }

            var toolbar = document.createElement("div");
            toolbar.className = "abtestkit-custom-css-popup-picker-toolbar";
            toolbar.innerHTML = '<span><strong>abtestkit marker picker</strong> — click the element you want to mark for Version B CSS.</span><button type="button">Cancel</button>';
            document.body.appendChild(toolbar);

            var highlight = document.createElement("div");
            highlight.className = "abtestkit-custom-css-popup-picker-highlight";
            highlight.style.display = "none";
            document.body.appendChild(highlight);

            function moveHighlight(el) {
                if (!el || !el.getBoundingClientRect || (el.closest && el.closest(ignoredSelector))) {
                    highlight.style.display = "none";
                    return;
                }

                var rect = el.getBoundingClientRect();

                if (!rect.width || !rect.height) {
                    highlight.style.display = "none";
                    return;
                }

                highlight.style.display = "block";
                highlight.style.left = rect.left + "px";
                highlight.style.top = rect.top + "px";
                highlight.style.width = rect.width + "px";
                highlight.style.height = rect.height + "px";
            }

            function cleanup() {
                document.removeEventListener("mousemove", onMove, true);
                document.removeEventListener("click", onClick, true);
                document.removeEventListener("keydown", onKeyDown, true);
                document.body.classList.remove("abtestkit-custom-css-popup-picker-active");

                if (toolbar && toolbar.parentNode) {
                    toolbar.parentNode.removeChild(toolbar);
                }

                if (highlight && highlight.parentNode) {
                    highlight.parentNode.removeChild(highlight);
                }
            }

            function onMove(event) {
                moveHighlight(event.target);
            }

            function onClick(event) {
                var target = event.target;

                if (target && target.closest && target.closest(ignoredSelector)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (event.stopImmediatePropagation) {
                    event.stopImmediatePropagation();
                }

                var selector = makeSelector(target);

                if (!selector) {
                    return;
                }

                window.opener.postMessage(
                    {
                        type: "abtestkit_custom_css_marker_pick",
                        selector: selector,
                        label: textLabel(target),
                        href: window.location.href
                    },
                    "*"
                );

                cleanup();
                window.setTimeout(function () {
                    window.close();
                }, 120);
            }

            function onKeyDown(event) {
                if (event.key !== "Escape") {
                    return;
                }

                cleanup();
                window.close();
            }

            toolbar.querySelector("button").addEventListener("click", function () {
                cleanup();
                window.close();
            });

            document.addEventListener("mousemove", onMove, true);
            document.addEventListener("click", onClick, true);
            document.addEventListener("keydown", onKeyDown, true);
        }());
    </script>
    <?php
}, PHP_INT_MAX );



// Quick existence check: does this Page Test ID belong to this post?
// Treat both control AND variant pages as valid owners of the test.
function abtestkit_pt_test_id_belongs_to_post( int $post_id, string $ab_test_id ): bool {
    foreach ( abtestkit_pt_all() as $t ) {
        if ( ! isset( $t['id'] ) || $t['id'] !== $ab_test_id ) {
            continue;
        }

        $control_id = isset( $t['control_id'] ) ? (int) $t['control_id'] : 0;
        $variant_id = isset( $t['variant_id'] ) ? (int) $t['variant_id'] : 0;

        // For page/product tests, a hit on either A or B is valid.
        if ( $control_id === (int) $post_id || $variant_id === (int) $post_id ) {
            return true;
        }
    }
    return false;
}

function abtestkit_test_id_exists_on_post( int $post_id, string $ab_test_id ): bool {
    // Allow Page-Test IDs that belong to this control post
    if ( abtestkit_pt_test_id_belongs_to_post( $post_id, $ab_test_id ) ) return true;

    $variants = get_post_meta( $post_id, '_abtestkit_variants', true );
    if ( is_array( $variants ) && isset( $variants[ $ab_test_id ] ) ) return true;

    $content = get_post_field( 'post_content', $post_id );
    if ( ! $content ) return false;
    $blocks = parse_blocks( $content );
    $found  = false;
    $scan = function( $blocks ) use ( &$scan, &$found, $ab_test_id ) {
        foreach ( $blocks as $b ) {
            if ( ! is_array( $b ) ) continue;
            $attrs = $b['attrs'] ?? [];
            if ( ( $attrs['abTestId'] ?? '' ) === $ab_test_id ) { $found = true; return; }
            if ( ! empty( $b['innerBlocks'] ) ) $scan( $b['innerBlocks'] );
        }
    };
    $scan( $blocks );
    return $found;
}


/**
 * Reusable Section Test v1.
 *
 * Intercepts [embed_page id="123"] before the site's own shortcode callback runs.
 * If page 123 is the source page for a running reusable section test, render A or B
 * according to the visitor's assigned variant.
 */
function abtestkit_reusable_section_get_active_map() : array {
    static $map = null;

    if ( is_array( $map ) ) {
        return $map;
    }

    $map = [];

    foreach ( abtestkit_pt_all() as $test ) {
        if ( ! is_array( $test ) ) {
            continue;
        }

        if ( ( $test['status'] ?? 'paused' ) !== 'running' ) {
            continue;
        }

        if ( ( $test['kind'] ?? '' ) !== 'reusable_section' ) {
            continue;
        }

        $source_id  = isset( $test['source_page_id'] ) ? (int) $test['source_page_id'] : (int) ( $test['control_id'] ?? 0 );
        $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        $tag        = isset( $test['shortcode_tag'] ) ? sanitize_key( (string) $test['shortcode_tag'] ) : 'embed_page';
        $attribute  = isset( $test['shortcode_attribute'] ) ? sanitize_key( (string) $test['shortcode_attribute'] ) : 'id';

        if ( $source_id <= 0 || $variant_id <= 0 || $tag === '' || $attribute === '' ) {
            continue;
        }

        if ( ! isset( $map[ $tag ] ) ) {
            $map[ $tag ] = [];
        }

        $map[ $tag ][ $source_id ] = [
            'test'      => $test,
            'source_id' => $source_id,
            'variant_id'=> $variant_id,
            'attribute' => $attribute,
        ];
    }

    return $map;
}

function abtestkit_reusable_section_assign_variant( array $test ) : string {
    $test_id = isset( $test['id'] ) ? abtestkit_sanitize_test_id( (string) $test['id'] ) : '';

    if ( $test_id === '' ) {
        return 'A';
    }

    // Admins/editors/exempt users should always see the original reusable section.
    // Do this before reading or setting the test cookie, otherwise an existing B
    // assignment can still cause the shortcode-rendered content to show Version B.
    if ( abtestkit_is_exempt_viewer() ) {
        return 'A';
    }

    // Never assign or track live B over HTTP.
    if ( ! abtestkit_request_is_real_https() ) {
        return 'A';
    }

    $cookie_name = 'abtestkit_pt_' . $test_id;
    $assigned    = abtestkit_safe_get_cookie_value( $cookie_name );

    if ( $assigned !== 'A' && $assigned !== 'B' ) {
        $split    = max( 0, min( 100, (int) ( $test['split'] ?? 50 ) ) );
        $ttl_days = max( 1, (int) ( $test['cookie_ttl_days'] ?? 30 ) );

        $assigned = ( wp_rand( 1, 100 ) <= $split ) ? 'B' : 'A';

        abtestkit_safe_set_cookie(
            $cookie_name,
            $assigned,
            time() + ( $ttl_days * DAY_IN_SECONDS )
        );

        $_COOKIE[ $cookie_name ] = $assigned;
    }

    return ( $assigned === 'B' ) ? 'B' : 'A';
}

function abtestkit_reusable_section_render_page( int $post_id ) : string {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 ) {
        return '';
    }

    $embedded_post = get_post( $post_id );

    if ( ! $embedded_post || $embedded_post->post_type !== 'page' ) {
        return '';
    }

    static $stack = [];

    if ( in_array( $post_id, $stack, true ) ) {
        return '';
    }

    $stack[] = $post_id;

    $content = '';

    /*
     * Important:
     * Elementor may be loaded on the site even when this specific page is a
     * normal Gutenberg/block page. Only use Elementor rendering when this exact
     * page was built with Elementor.
     */
    $is_elementor_page = (
        did_action( 'elementor/loaded' )
        && class_exists( '\Elementor\Plugin' )
        && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true )
        && isset( \Elementor\Plugin::$instance->frontend )
    );

    if ( $is_elementor_page ) {
        try {
            if ( method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content_for_display' ) ) {
                $content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
            } elseif ( method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content' ) ) {
                $content = \Elementor\Plugin::$instance->frontend->get_builder_content( $post_id, true );
            }
        } catch ( \Throwable $e ) {
            $content = '';
        }
    }

    /*
     * Gutenberg / normal WordPress fallback.
     *
     * If Elementor is not used for this exact page, or Elementor returns empty,
     * render through WordPress' normal content pipeline.
     */
    if ( '' === trim( (string) $content ) ) {
        global $post;

        $previous_post = $post;
        $post          = $embedded_post;

        setup_postdata( $post );

        try {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress content filter.
            $content = apply_filters( 'the_content', $embedded_post->post_content );
        } catch ( \Throwable $e ) {
            $content = '';
        }

        wp_reset_postdata();

        $post = $previous_post;
    }

    array_pop( $stack );

    return is_string( $content ) ? $content : '';
}

function abtestkit_reusable_section_log_impression_once( array $test, string $variant, int $source_id, int $rendered_id ) : void {
    if ( abtestkit_is_exempt_viewer() ) {
        return;
    }

    if ( ! abtestkit_request_is_real_https() ) {
        return;
    }

    $test_id = isset( $test['id'] ) ? abtestkit_sanitize_test_id( (string) $test['id'] ) : '';
    if ( $test_id === '' || ! in_array( $variant, [ 'A', 'B' ], true ) ) {
        return;
    }

    $host_id = (int) get_queried_object_id();
    if ( $host_id <= 0 ) {
        $host_id = $source_id;
    }

    /*
     * Do not write the impression directly here.
     *
     * Reusable sections render inside a host product/page, and the rest of
     * abtestkit's live impression model is JS -> REST /track. The shortcode
     * renderer only records the exposure cookie for Woo order attribution.
     * frontend.js logs the actual impression against the host object.
     */
    $exposure_cookie = 'abtestkit_reusable_seen_' . $test_id;
    abtestkit_safe_set_cookie(
        $exposure_cookie,
        wp_json_encode(
            [
                'variant'     => $variant,
                'source_id'   => $source_id,
                'rendered_id' => $rendered_id,
                'host_id'     => $host_id,
            ]
        ),
        time() + ( 30 * DAY_IN_SECONDS )
    );

    $_COOKIE[ $exposure_cookie ] = wp_json_encode(
        [
            'variant'     => $variant,
            'source_id'   => $source_id,
            'rendered_id' => $rendered_id,
            'host_id'     => $host_id,
        ]
    );
}

function abtestkit_intercept_reusable_section_shortcode( $output, $tag, $attr, $m ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $output;
    }

    $tag = sanitize_key( (string) $tag );

    if ( $tag === '' ) {
        return $output;
    }

    $map = abtestkit_reusable_section_get_active_map();

    if ( empty( $map[ $tag ] ) || ! is_array( $map[ $tag ] ) ) {
        return $output;
    }

    $raw_attr = is_array( $attr ) ? $attr : [];

    /*
     * Reusable Section Test v1 currently defaults to [embed_page id="123"],
     * but keep this flexible so future shortcode integrations can use a
     * different attribute name without breaking interception.
     */
    $source_id = 0;

    foreach ( $map[ $tag ] as $possible_source_id => $possible_entry ) {
        $attribute = isset( $possible_entry['attribute'] )
            ? sanitize_key( (string) $possible_entry['attribute'] )
            : 'id';

        if ( $attribute === '' ) {
            $attribute = 'id';
        }

        if ( isset( $raw_attr[ $attribute ] ) && absint( $raw_attr[ $attribute ] ) === (int) $possible_source_id ) {
            $source_id = (int) $possible_source_id;
            break;
        }
    }

    if ( $source_id <= 0 || empty( $map[ $tag ][ $source_id ] ) ) {
        return $output;
    }

    $entry = $map[ $tag ][ $source_id ];
    $test  = isset( $entry['test'] ) && is_array( $entry['test'] ) ? $entry['test'] : [];

    if ( empty( $test['id'] ) ) {
        return $output;
    }

    $variant     = abtestkit_reusable_section_assign_variant( $test );
    $rendered_id = ( $variant === 'B' ) ? (int) $entry['variant_id'] : (int) $entry['source_id'];

    abtestkit_reusable_section_log_impression_once(
        $test,
        $variant,
        (int) $entry['source_id'],
        $rendered_id
    );

    $content = abtestkit_reusable_section_render_page( $rendered_id );

    if ( $content === '' ) {
        return '';
    }

    $test_id = abtestkit_sanitize_test_id( (string) $test['id'] );

    $goal = isset( $test['goal'] )
        ? sanitize_key( (string) $test['goal'] )
        : '';

    $links = [];

    if ( ! empty( $test['links'] ) && is_array( $test['links'] ) ) {
        $links = array_values(
            array_filter(
                array_map( 'strval', $test['links'] )
            )
        );
    }

    return sprintf(
        '<div class="abtestkit-reusable-section" data-abtestkit-reusable-section="1" data-ab-test-id="%s" data-ab-variant="%s" data-ab-source-id="%d" data-ab-rendered-id="%d" data-ab-shortcode-tag="%s" data-ab-goal="%s" data-ab-links="%s" style="display:contents">%s</div>',
        esc_attr( $test_id ),
        esc_attr( $variant ),
        (int) $entry['source_id'],
        (int) $rendered_id,
        esc_attr( $tag ),
        esc_attr( $goal ),
        esc_attr( wp_json_encode( $links ) ),
        $content
    );
}
add_filter( 'pre_do_shortcode_tag', 'abtestkit_intercept_reusable_section_shortcode', 10, 4 );

function abtestkit_handle_track( WP_REST_Request $request ) {
    // Parse + sanitize JSON body
    $raw     = $request->get_body();
    $body    = is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : [];
    $type            = sanitize_text_field( $body['type'] ?? '' );
    $post_id         = absint( $body['postId'] ?? 0 );
    $index           = absint( $body['index'] ?? 0 );
    $variant         = $body['variant'] ?? '';
    $ab_id           = abtestkit_sanitize_test_id( $body['abTestId'] ?? '' );
    $protocol        = sanitize_key( (string) ( $body['protocol'] ?? '' ) );
    $excluded_reason = sanitize_key( (string) ( $body['excluded_reason'] ?? '' ) );
    $scroll_pct      = isset( $body['scroll'] ) && is_numeric( $body['scroll'] )
        ? max( 0, min( 100, (int) round( (float) $body['scroll'] ) ) )
        : 0;
    $time_sec        = isset( $body['seconds'] ) && is_numeric( $body['seconds'] )
        ? max( 0, min( 3600, (int) round( (float) $body['seconds'] ) ) )
        : 0;

    // Public tracking must come from a real same-origin browser request.
    $nonce_ok  = abtestkit_rest_check_nonce( $request );
    $origin_ok = abtestkit_is_same_origin_request();

    if ( ! $origin_ok && ! $nonce_ok ) {
        return rest_ensure_response( [
            'success' => false,
            'error'   => 'Unauthorised: same-origin browser request required.',
        ] );
    }

    $valid_types   = [ 'impression', 'click', 'engagement', 'decision', 'decision_applied', 'stale', 'protocol_warning' ];
    $needs_variant = in_array( $type, [ 'impression', 'click', 'engagement', 'decision', 'decision_applied', 'protocol_warning' ], true );

    if (
        empty( $ab_id ) ||
        ! in_array( $type, $valid_types, true ) ||
        ( $needs_variant && ! in_array( $variant, [ 'A', 'B' ], true ) ) ||
        $post_id <= 0
    ) {
        return rest_ensure_response( [
            'success' => false,
            'error'   => 'Invalid tracking payload',
        ] );
    }

    $pt = null;

    // For Page Tests: resolve the test once so protocol_warning can validate
    // against the real PT record without depending on block/post scan logic.
    if ( strpos( $ab_id, 'pt-' ) === 0 ) {
        $pt = abtestkit_pt_get( (string) $ab_id );

        if ( ! is_array( $pt ) ) {
            return rest_ensure_response( [ 'success' => true ] );
        }

        if ( ( $pt['status'] ?? 'paused' ) !== 'running' ) {
            return rest_ensure_response( [ 'success' => true ] );
        }
    }

    // Admins should never log events.
    if ( abtestkit_is_exempt_viewer() ) {
        return rest_ensure_response( [ 'success' => true ] );
    }

    // Allow a lightweight diagnostic row for excluded HTTP visits,
    // but continue blocking all normal tracking on HTTP.
    if ( $type === 'protocol_warning' ) {
        if ( $protocol !== 'http' || $excluded_reason !== 'http' ) {
            return rest_ensure_response( [ 'success' => true ] );
        }

        // For page/product tests, validate against the stored PT record directly.
        if ( is_array( $pt ) ) {
            $control_id = (int) ( $pt['control_id'] ?? 0 );
            $variant_id = (int) ( $pt['variant_id'] ?? 0 );

            if ( $post_id !== $control_id && ( $variant_id <= 0 || $post_id !== $variant_id ) ) {
                return rest_ensure_response( [ 'success' => true ] );
            }
        } elseif ( ! abtestkit_test_id_exists_on_post( $post_id, $ab_id ) ) {
            return rest_ensure_response( [ 'success' => true ] );
        }

        abtestkit_log_event_to_db(
            'protocol_warning',
            $post_id,
            $ab_id,
            in_array( $variant, [ 'A', 'B' ], true ) ? $variant : 'A',
            [
                'protocol'        => 'http',
                'excluded_reason' => 'http',
            ]
        );

        abtestkit_pt_flush_test_caches( (string) $ab_id );

        return rest_ensure_response( [ 'success' => true ] );
    }

    // Ensure the test actually exists on this post for normal tracking.
    // Reusable Section Tests are different: the tested page is rendered inside
    // a host product/page via shortcode, so the event should be allowed against
    // the current host object as long as the PT record is a running reusable test.
    $tracking_allowed = abtestkit_test_id_exists_on_post( $post_id, $ab_id );

    if (
        ! $tracking_allowed
        && is_array( $pt )
        && ( $pt['kind'] ?? '' ) === 'reusable_section'
        && ( $pt['status'] ?? 'paused' ) === 'running'
    ) {
        $tracking_allowed = true;
    }

    if ( ! $tracking_allowed ) {
        return rest_ensure_response( [
            'success' => false,
            'error'   => 'Unknown test on this post',
        ] );
    }

    // Respect the browser's reported page protocol when the JS tracker sends it.
    if ( $protocol === 'http' ) {
        return rest_ensure_response( [ 'success' => true ] );
    }

    // Belt-and-braces: reject normal event writes if the real frontend request is not HTTPS.
    if ( ! abtestkit_request_is_real_https() ) {
        return rest_ensure_response( [ 'success' => true ] );
    }

    // Simple rate limit: max 120 events/minute per IP + test.
    $ip_for_limit_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $ip_for_limit     = filter_var( $ip_for_limit_raw, FILTER_VALIDATE_IP ) ? $ip_for_limit_raw : 'unknown';

    $limit_key     = 'abtestkit_trk_' . md5( $ip_for_limit . '|' . $post_id . '|' . $ab_id . '|' . gmdate( 'YmdHi' ) );
    $current_count = (int) get_transient( $limit_key );
    if ( $current_count > 120 ) {
        return rest_ensure_response( [ 'success' => true ] );
    }
    set_transient( $limit_key, $current_count + 1, MINUTE_IN_SECONDS + 5 );

    // One click per session per variant.
    if ( $type === 'click' ) {
        $click_cookie_name = 'abtestkit_pt_click_' . $post_id . '_' . $ab_id . '_' . $variant;
        $already_clicked   = ( abtestkit_safe_get_cookie_value( $click_cookie_name ) === '1' );

        if ( $already_clicked ) {
            return rest_ensure_response( [ 'success' => true ] );
        }

        abtestkit_safe_set_cookie( $click_cookie_name, '1', 0 );
    }

    abtestkit_log_event_to_db(
        $type,
        $post_id,
        $ab_id,
        $variant,
        [
            'protocol'   => 'https',
            'scroll_pct' => $scroll_pct,
            'time_sec'   => $time_sec,
        ]
    );

    if ( $type === 'click' && strpos( $ab_id, 'pt-' ) === 0 ) {
        abtestkit_pt_maybe_lock_winner( (string) $ab_id );
    }

    return rest_ensure_response( [ 'success' => true ] );
}


// ─────────────────────────────────────────────────────────────────────────────
// Force no-cache headers on abtestkit REST responses (works with WP Rocket/CDNs)
// ─────────────────────────────────────────────────────────────────────────────
add_filter('rest_post_dispatch', function( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, '/abtestkit/' ) !== false ) {
        // Always a WP_REST_Response by here, but guard anyway
        if ( is_a( $result, WP_REST_Response::class ) ) {
            $result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
            $result->header( 'Pragma', 'no-cache' );
        } else {
            $resp = new WP_REST_Response( $result );
            $resp->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
            $resp->header( 'Pragma', 'no-cache' );
            return $resp;
        }
    }
    return $result;
}, 10, 3);

//Handler for /stats


function abtestkit_handle_stats( WP_REST_Request $request ) {
    global $wpdb;

    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( $post_id <= 0 ) {
        return rest_ensure_response([
            'A' => [ 'impressions' => 0, 'clicks' => 0 ],
            'B' => [ 'impressions' => 0, 'clicks' => 0 ],
        ]);
    }

    // Accept either a single abTestId or multiple abTestIds
    $single = $request->get_param( 'abTestId' );
    $many   = $request->get_param( 'abTestIds' );

    // Normalise to an array of sanitized IDs
    $ab_ids = [];
    if ( is_array( $many ) ) {
        foreach ( $many as $id ) {
            $clean = abtestkit_sanitize_test_id( $id );
            if ( $clean !== '' ) $ab_ids[] = $clean;
        }
    } elseif ( is_string( $many ) && $many !== '' ) {
        foreach ( preg_split( '/[,\|]/', $many ) as $id ) {
            $clean = abtestkit_sanitize_test_id( $id );
            if ( $clean !== '' ) $ab_ids[] = $clean;
        }
    } elseif ( $single ) {
        $clean = abtestkit_sanitize_test_id( $single );
        if ( $clean !== '' ) $ab_ids[] = $clean;
    }

    $ab_ids = array_values( array_unique( $ab_ids ) );

    if ( empty( $ab_ids ) ) {
        return rest_ensure_response([
            'A' => [ 'impressions' => 0, 'clicks' => 0 ],
            'B' => [ 'impressions' => 0, 'clicks' => 0 ],
        ]);
    }

    // ── Simple object cache for stats ───────────────────────────────────────────
    $cache_key = '';
    if ( count( $ab_ids ) === 1 ) {
      $cache_key = "stats:$post_id:{$ab_ids[0]}";
    } else {
     $cache_key = "stats_multi:$post_id";
    }
    $cached = wp_cache_get( $cache_key, 'abtestkit_stats' );
    if ( false !== $cached ) {
        return rest_ensure_response( $cached );
    }


    $table = ABTESTKIT_EVENTS_TABLE;

    if ( count( $ab_ids ) === 1 ) {
        $post_id_i = (int) $post_id;
        $ab_id     = isset( $ab_ids[0] ) ? sanitize_text_field( (string) $ab_ids[0] ) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ab_test_id, variant, event_type, COUNT(*) AS count ' .
                'FROM %i ' .
                'WHERE post_id = %d AND ab_test_id = %s ' .
                'GROUP BY ab_test_id, variant, event_type',
                $table,
                $post_id_i,
                $ab_id
            ),
            ARRAY_A
        );
    } else {
        $ab_id_placeholders = implode( ', ', array_fill( 0, count( $ab_ids ), '%s' ) );
        $query_args         = array_merge( [ $table, $post_id ], $ab_ids );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            /*
             * The fragment below contains generated %s tokens only. The argument
             * array supplies the table, post ID and one value for every token.
             */
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $wpdb->prepare(
                'SELECT ab_test_id, variant, event_type, COUNT(*) AS count ' .
                'FROM %i ' .
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                'WHERE post_id = %d AND ab_test_id IN (' . $ab_id_placeholders . ') ' .
                'GROUP BY ab_test_id, variant, event_type',
                $query_args
            ),
            ARRAY_A
        );
    }

    $default_pair = [ 'A' => [ 'impressions' => 0, 'clicks' => 0 ],
                      'B' => [ 'impressions' => 0, 'clicks' => 0 ] ];

    if ( count( $ab_ids ) === 1 && isset( $ab_ids[0] ) && $single ) {
        // Back-compat single shape
        $stats = $default_pair;
        foreach ( $rows as $r ) {
            $v = $r['variant'];
            $t = $r['event_type'];
            $c = (int) $r['count'];
            if ( isset( $stats[$v][$t . 's'] ) ) {
                $stats[$v][$t . 's'] += $c;
            }
        }
        wp_cache_set( $cache_key, $stats, 'abtestkit_stats', MINUTE_IN_SECONDS );
        return rest_ensure_response( $stats );
    }

    // Multi shape keyed by test id
    $out = [];
    foreach ( $ab_ids as $id ) {
        $out[$id] = $default_pair;
    }
    foreach ( $rows as $r ) {
        $id = $r['ab_test_id'];
        $v  = $r['variant'];
        $t  = $r['event_type'];
        $c  = (int) $r['count'];
        if ( isset( $out[$id][$v][$t . 's'] ) ) {
            $out[$id][$v][$t . 's'] += $c;
        }
    }

    wp_cache_set( $cache_key, $out, 'abtestkit_stats', MINUTE_IN_SECONDS );
    return rest_ensure_response( $out );
}


/**
 * Handler for /evaluate — Bayesian with Beta(5,5) prior + 5-impression floor.
 */
function abtestkit_handle_evaluate( WP_REST_Request $request ) {
    $abTestId = abtestkit_sanitize_test_id( $request->get_param('abTestId') );
    $post_id  = absint( $request->get_param('post_id') );

    if ( empty( $abTestId ) || empty( $post_id ) ) {
        return rest_ensure_response([
            'error'   => 'Missing abTestId or post_id',
            'message' => 'Evaluation requires abTestId and post_id.'
        ]);
    }

    // Parse post content to find if this test has grouped dependencies (conversionFrom)
    $content = get_post_field('post_content', $post_id );
    $blocks  = parse_blocks( $content );
    $grouped = [];

    $scan = function( $blocks ) use ( &$scan, $abTestId, &$grouped ) {
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) continue;
            $attrs = $block['attrs'] ?? [];
            if ( ( $attrs['abTestId'] ?? null ) === $abTestId && ! empty( $attrs['conversionFrom'] ) ) {
                $grouped = $attrs['conversionFrom'];
                return;
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $scan( $block['innerBlocks'] );
            }
        }
    };
    $scan( $blocks );

    $conversionEvent = 'click';

    // Defaults (legacy)
    $decisionMode   = 'auto';
    $minImpressions = 50;
    $minConversions = 5;

    // Page Test Wizard (pt-*) thresholds + manual mode
    if ( strpos( (string) $abTestId, 'pt-' ) === 0 ) {
        $pt_test = abtestkit_pt_get( (string) $abTestId );
        if ( is_array( $pt_test ) ) {
            $decisionMode = sanitize_key( (string) ( $pt_test['decision_mode'] ?? 'auto' ) );
            $decisionRule = sanitize_key( (string) ( $pt_test['decision_rule'] ?? 'balanced' ) );
            $goal         = sanitize_key( (string) ( $pt_test['goal'] ?? '' ) );

            if ( $goal === 'purchase' ) {
                $conversionEvent = 'purchase';
            }

            if ( $decisionMode === 'manual' || $decisionRule === 'manual' ) {
                $decisionMode   = 'manual';
                $minImpressions = 0;
                $minConversions = 0;
            } else {
                $mi = isset( $pt_test['min_impressions'] ) ? absint( $pt_test['min_impressions'] ) : 0;
                $mc = isset( $pt_test['min_conversions'] ) ? absint( $pt_test['min_conversions'] ) : 0;

                $minImpressions = in_array( $mi, [ 25, 50, 75 ], true ) ? $mi : 50;
                $minConversions = in_array( $mc, [ 3, 5, 10 ], true ) ? $mc : 5;
            }
        }
    }

    // Get current stats for this test.
    // Purchase-goal tests are evaluated on RPV, not just raw purchase count.
    $revA = 0.0;
    $revB = 0.0;

    if ( $conversionEvent === 'purchase' ) {
        global $wpdb;

        $table = ABTESTKIT_EVENTS_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT variant, event_type, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS revenue ' .
                'FROM %i ' .
                'WHERE ab_test_id = %s ' .
                "AND event_type IN ('impression','purchase') " .
                'GROUP BY variant, event_type',
                $table,
                (string) $abTestId
            ),
            ARRAY_A
        );

        $impA = 0;
        $clkA = 0;
        $impB = 0;
        $clkB = 0;

        foreach ( (array) $rows as $r ) {
            $v       = isset( $r['variant'] ) ? (string) $r['variant'] : '';
            $t       = isset( $r['event_type'] ) ? (string) $r['event_type'] : '';
            $c       = isset( $r['c'] ) ? (int) $r['c'] : 0;
            $revenue = isset( $r['revenue'] ) ? (float) $r['revenue'] : 0.0;

            if ( $v === 'A' && $t === 'impression' ) {
                $impA = $c;
            } elseif ( $v === 'A' && $t === 'purchase' ) {
                $clkA = $c;
                $revA = $revenue;
            } elseif ( $v === 'B' && $t === 'impression' ) {
                $impB = $c;
            } elseif ( $v === 'B' && $t === 'purchase' ) {
                $clkB = $c;
                $revB = $revenue;
            }
        }
    } else {
        $stats_resp = abtestkit_handle_stats( $request );
        $stats = $stats_resp instanceof WP_REST_Response
            ? $stats_resp->get_data()
            : (array) $stats_resp;

        $impA = (int) ( $stats['A']['impressions'] ?? 0 );
        $clkA = (int) ( $stats['A']['clicks'] ?? 0 );
        $impB = (int) ( $stats['B']['impressions'] ?? 0 );
        $clkB = (int) ( $stats['B']['clicks'] ?? 0 );
    }

    // Cache evaluation for this exact stats state (60s)
    $eval_cache_key = sprintf(
        'abtestkit_eval:%d:%s:%d:%d:%d:%d:%0.2f:%0.2f:%s:%d:%d',
        (int) $post_id,
        (string) $abTestId,
        $impA,
        $clkA,
        $impB,
        $clkB,
        (float) $revA,
        (float) $revB,
        (string) $decisionMode,
        (int) $minImpressions,
        (int) $minConversions
    );
    $cached_eval = wp_cache_get( $eval_cache_key, 'abtestkit_eval' );
    if ( false !== $cached_eval ) {
        return rest_ensure_response( $cached_eval );
    }

    // Early exits
    if ( $impA === 0 && $impB === 0 ) {
        $out = [ 'probA' => 0.5, 'probB' => 0.5, 'winner' => '', 'message' => 'No impressions recorded yet.' ];
        wp_cache_set( $eval_cache_key, $out, 'abtestkit_eval', 60 );
        return rest_ensure_response( $out );
    }
    if ( $impA === 0 || $impB === 0 ) {
        $out = [ 'probA' => 0.5, 'probB' => 0.5, 'winner' => '', 'message' => 'Only one variant has impressions — test needs more data.' ];
        wp_cache_set( $eval_cache_key, $out, 'abtestkit_eval', 60 );
        return rest_ensure_response( $out );
    }

    $totalImpressions = $impA + $impB;
    $totalConversions = $clkA + $clkB;

    // Auto mode gates
    if ( $decisionMode !== 'manual' ) {
        if ( $totalImpressions < (int) $minImpressions ) {
            $out = [
                'probA' => 0.5, 'probB' => 0.5, 'winner' => '',
                'message' => sprintf( 'Not enough data yet — %d/%d total impressions.', $totalImpressions, (int) $minImpressions ),
            ];
            wp_cache_set( $eval_cache_key, $out, 'abtestkit_eval', 60 );
            return rest_ensure_response( $out );
        }

        if ( $totalConversions < (int) $minConversions ) {
            $out = [
                'probA' => 0.5, 'probB' => 0.5, 'winner' => '',
                'message' => sprintf( 'Not enough conversions yet — %d/%d total conversions.', $totalConversions, (int) $minConversions ),
            ];
            wp_cache_set( $eval_cache_key, $out, 'abtestkit_eval', 60 );
            return rest_ensure_response( $out );
        }
    }

    // Bayesian sampling
    $numSamples = 8000;
    $countA = 0;
    $diffs  = [];

    if ( $conversionEvent === 'purchase' ) {
        for ( $i = 0; $i < $numSamples; $i++ ) {
            $sampA = abtestkit_sample_rpv( $impA, $clkA, $revA );
            $sampB = abtestkit_sample_rpv( $impB, $clkB, $revB );

            if ( $sampA > $sampB ) {
                $countA++;
            }

            $diffs[] = $sampA - $sampB;
        }
    } else {
        $priorN = 2;
        $alphaA = $priorN / 2 + $clkA;
        $betaA  = $priorN / 2 + max( 0, $impA - $clkA );
        $alphaB = $priorN / 2 + $clkB;
        $betaB  = $priorN / 2 + max( 0, $impB - $clkB );

        for ( $i = 0; $i < $numSamples; $i++ ) {
            $sampA = abtestkit_sample_beta( $alphaA, $betaA );
            $sampB = abtestkit_sample_beta( $alphaB, $betaB );

            if ( $sampA > $sampB ) {
                $countA++;
            }

            $diffs[] = $sampA - $sampB;
        }
    }

    $probA = $countA / $numSamples;
    $probB = 1 - $probA;

    // Confidence level by rule:
    // - Fast (25 impressions / 3 conversions): 90%
    // - Balanced + Precise: 95%
    $confidence = 0.95;
    if ( $decisionMode !== 'manual' ) {
        // Prefer the saved rule label if present, otherwise infer from thresholds.
        if (
            ( isset( $decisionRule ) && $decisionRule === 'fast' )
            || ( (int) $minImpressions === 25 && (int) $minConversions === 3 )
        ) {
            $confidence = 0.90;
        }
    }

    sort( $diffs );
    $tail   = ( 1 - $confidence ) / 2; // e.g. 0.05 for 90%, 0.025 for 95%
    $ciLower = $diffs[ (int) ( $tail * $numSamples ) ];
    $ciUpper = $diffs[ (int) ( ( 1 - $tail ) * $numSamples ) ];

    // Winner decision (never in manual mode)
    $winner = '';
    if ( $decisionMode !== 'manual' ) {
        if ( $probA > $confidence && $ciLower > 0 ) {
            $winner = 'A';
        } elseif ( $probB > $confidence && $ciUpper < 0 ) {
            $winner = 'B';
        }
    }

    $result = [
        'probA'   => round( $probA, 4 ),
        'probB'   => round( $probB, 4 ),
        'ciLower' => round( $ciLower, 4 ),
        'ciUpper' => round( $ciUpper, 4 ),
        'winner'  => $winner
    ];

    wp_cache_set( $eval_cache_key, $result, 'abtestkit_eval', 60 );
    return rest_ensure_response( $result );
}

/**
 * Handler for /reset — removes events for given post, index, and abTestId only.
 */
function abtestkit_handle_reset( WP_REST_Request $request ) {
    global $wpdb;

    $body     = json_decode( $request->get_body(), true );
    $post_id  = absint( $body['post_id'] ?? 0 );
    $abTestId = abtestkit_sanitize_test_id( $body['abTestId'] ?? '' );

    if ( empty( $post_id ) || empty( $abTestId ) ) {
        return rest_ensure_response( [
            'success' => false,
            'error'   => 'Missing post_id or abTestId'
        ] );
    }

    // Admin action: safe, parameterized delete scoped to this post/test.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $deleted = $wpdb->delete(
        ABTESTKIT_EVENTS_TABLE,
        [
            'post_id'    => $post_id,
            'ab_test_id' => $abTestId,
        ],
        [ '%d', '%s' ]
    );
    // Invalidate stats cache for this post/test.
    wp_cache_delete( "stats:$post_id:$abTestId", 'abtestkit_stats' );
    wp_cache_delete( "stats_multi:$post_id", 'abtestkit_stats' );

    return rest_ensure_response( [ 'success' => true, 'deleted' => $deleted ] );
}

/**
 * Enqueue block-editor scripts: ab-sidebar.js (plugin sidebar) + editor.js (inline-lock HOC).
 */
add_action( 'enqueue_block_editor_assets', function() {

    // Only on post editor screens for a specific post.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
    if ( ! $post_id ) {
        return;
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }

    $post_type = (string) $post->post_type;
    if ( $post_type !== 'post' && $post_type !== 'page' ) {
        return;
    }

    // Detect Version B states used by your plugin.
    $is_shadow    = (int) get_post_meta( $post_id, '_abtestkit_shadow', true ) === 1;
    $is_existingB = (int) get_post_meta( $post_id, '_abtestkit_variant_in_use', true ) === 1;

    if ( ! $is_shadow && ! $is_existingB ) {
        return;
    }

    $type_label = ( $post_type === 'page' ) ? 'Page' : 'Post';

    // Linked Version A ID differs depending on how B was created.
    $control_id = 0;
    if ( $is_shadow ) {
        $control_id = (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );
    } elseif ( $is_existingB ) {
        $control_id = (int) get_post_meta( $post_id, '_abtestkit_variant_of', true );
    }

    if ( $is_shadow ) {
        $msg = 'abtestkit: This is your Version B ' . $type_label . ' for this A/B test. Make your changes, save as draft, then close this tab to continue creating your test. This ' . strtolower( $type_label ) . ' cannot be published.';
    } else {
        $msg = 'abtestkit: You are editing Version B (' . $type_label . '). Make your changes, then Publish/Save and close this tab to continue creating your test.';
    }

    $show_blocked_notice = $is_shadow && abtestkit_peek_shadow_publish_blocked( $post_id );

    // Show a native Gutenberg notice (reliable in block editor).
    $js = "(function(){\n"
        . "  if(!window.wp || !wp.data || !wp.data.dispatch){ return; }\n"
        . "  var msg = " . wp_json_encode( $msg ) . ";\n"
        . "  try {\n"
        . "    wp.data.dispatch('core/notices').createNotice('warning', msg, { isDismissible: true });\n";

    if ( $show_blocked_notice ) {
        $js .= "    wp.data.dispatch('core/notices').createNotice('error', 'Publish blocked: Shadow " . strtolower( $type_label ) . "s are never allowed to go live.', { isDismissible: true });\n";
    }

    $js .= "  } catch(e) {}\n"
        . "})();\n";

    // wp-edit-post is loaded on the block editor screen.
    wp_add_inline_script( 'wp-edit-post', $js, 'after' );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Admin compatibility prompt (shown after the first successful test creation)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_notices', function () {

    if ( ! abtestkit_compatibility_prompt_is_target_screen() ) {
        return;
    }

    if ( ! abtestkit_compatibility_prompt_should_show() ) {
        return;
    }

    $return_url = abtestkit_admin_return_url_from_request();

    $yes_url = wp_nonce_url(
        add_query_arg(
            [
                'action'     => 'abtestkit_telemetry_optin',
                'v'          => '1',
                'return_url' => $return_url,
            ],
            admin_url( 'admin-post.php' )
        ),
        'abtestkit_optin'
    );

    $later_url = add_query_arg(
        [
            'action'     => 'abtestkit_compatibility_prompt',
            'do'         => 'later',
            '_wpnonce'   => wp_create_nonce( 'abtestkit_compatibility_prompt' ),
            'return_url' => $return_url,
        ],
        admin_url( 'admin-post.php' )
    );

    $no_url = wp_nonce_url(
        add_query_arg(
            [
                'action'     => 'abtestkit_telemetry_optin',
                'v'          => '0',
                'return_url' => $return_url,
            ],
            admin_url( 'admin-post.php' )
        ),
        'abtestkit_optin'
    );

    printf(
        '<div class="notice notice-info abtestkit-compatibility-prompt">
            <div style="margin:12px 0;max-width:900px;">
                <p style="margin:0 0 8px;"><strong>%1$s</strong></p>
                <p style="margin:0 0 12px;">%2$s</p>
                <p style="display:flex;flex-wrap:wrap;gap:8px;margin:0;">
                    <a class="button button-primary abtestkit-compatibility-prompt-return-link" href="%3$s">%4$s</a>
                    <a class="button abtestkit-compatibility-prompt-return-link" href="%5$s">%6$s</a>
                    <a class="button-link abtestkit-compatibility-prompt-return-link" style="align-self:center;" href="%7$s">%8$s</a>
                </p>
            </div>
        </div>',
        esc_html__( 'Help improve compatibility for sites like yours', 'abtestkit' ),
        esc_html__( 'You’ve created your first test. WordPress sites vary a lot across themes, builders, WooCommerce setups and caching plugins. Sharing anonymous compatibility information helps us see which setups need better support.', 'abtestkit' ),
        esc_url( $yes_url ),
        esc_html__( 'Share compatibility info', 'abtestkit' ),
        esc_url( $later_url ),
        esc_html__( 'Not now', 'abtestkit' ),
        esc_url( $no_url ),
        esc_html__( 'No thanks', 'abtestkit' )
    );
    ?>
    <script>
    (function () {
        var prompt = document.querySelector('.abtestkit-compatibility-prompt');
        if (!prompt || !window.URL || !window.location) {
            return;
        }

        prompt.querySelectorAll('a.abtestkit-compatibility-prompt-return-link').forEach(function (link) {
            try {
                var url = new URL(link.href, window.location.href);
                url.searchParams.set('return_url', window.location.href);
                link.href = url.toString();
            } catch (e) {}
        });
    }());
    </script>
    <?php
} );

add_action( 'admin_post_abtestkit_telemetry_optin', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'forbidden' );
    }

    check_admin_referer( 'abtestkit_optin' );

    $v = isset( $_GET['v'] ) && 1 === absint( wp_unslash( $_GET['v'] ) );
    abtestkit_set_telemetry_optin( (bool) $v );

    wp_safe_redirect( abtestkit_admin_get_safe_return_url() );
    exit;
} );

add_action( 'admin_post_abtestkit_compatibility_prompt', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'forbidden' );
    }

    check_admin_referer( 'abtestkit_compatibility_prompt' );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
    $do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : 'later';

    $state = abtestkit_compatibility_prompt_get_state();

    if ( $do === 'never' ) {
        $state['dismissed']    = 1;
        $state['snooze_until'] = 0;
    } else {
        $state['snooze_until'] = time() + ( 30 * DAY_IN_SECONDS );
    }

    abtestkit_compatibility_prompt_save_state( $state );

    wp_safe_redirect( abtestkit_admin_get_safe_return_url() );
    exit;
});

add_action( 'admin_post_abtestkit_review_prompt', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'forbidden' );
    }

    check_admin_referer( 'abtestkit_review_prompt' );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
    $do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

    $state = abtestkit_review_prompt_get_state();

    if ( $do === 'later' ) {
        $state['snooze_until'] = time() + ( 30 * DAY_IN_SECONDS );
    } elseif ( in_array( $do, [ 'review', 'never' ], true ) ) {
        $state['dismissed']    = 1;
        $state['snooze_until'] = 0;
    }

    abtestkit_review_prompt_save_state( $state );

    if ( $do === 'review' ) {
        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        wp_redirect( esc_url_raw( ABTESTKIT_REVIEW_URL ) );
        exit;
    }

    wp_safe_redirect( abtestkit_admin_get_safe_return_url() );
    exit;
} );

add_action( 'admin_footer', function () {
    if ( ! abtestkit_review_prompt_is_dashboard_screen() ) {
        return;
    }

    if ( ! abtestkit_review_prompt_should_show() ) {
        return;
    }

    $review_url = add_query_arg(
        [
            'action'   => 'abtestkit_review_prompt',
            'do'       => 'review',
            '_wpnonce' => wp_create_nonce( 'abtestkit_review_prompt' ),
        ],
        admin_url( 'admin-post.php' )
    );

    $later_url = add_query_arg(
        [
            'action'   => 'abtestkit_review_prompt',
            'do'       => 'later',
            '_wpnonce' => wp_create_nonce( 'abtestkit_review_prompt' ),
        ],
        admin_url( 'admin-post.php' )
    );

    $never_url = add_query_arg(
        [
            'action'   => 'abtestkit_review_prompt',
            'do'       => 'never',
            '_wpnonce' => wp_create_nonce( 'abtestkit_review_prompt' ),
        ],
        admin_url( 'admin-post.php' )
    );
    ?>
    <style>
        .abtestkit-review-overlay {
            position: fixed;
            inset: 0;
            background: rgba(9, 43, 43, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            padding: 24px;
        }

        .abtestkit-review-modal {
            position: relative;
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 48px rgba(0,0,0,0.18);
            padding: 28px 28px 24px;
        }

        .abtestkit-review-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #646970;
            font-size: 20px;
            line-height: 32px;
            cursor: pointer;
        }

        .abtestkit-review-close:hover {
            background: #f6f7f7;
            color: #1d2327;
        }

        .abtestkit-review-stars {
            margin: 0 0 14px;
            font-size: 20px;
            line-height: 1;
            letter-spacing: 2px;
            color: #fc510b;
        }

        .abtestkit-review-title {
            margin: 0 0 10px;
            font-size: 22px;
            line-height: 1.3;
            color: #1d2327;
        }

        .abtestkit-review-copy {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #50575e;
        }

        .abtestkit-review-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .abtestkit-review-actions .button.button-primary {
            background: #fc510b;
            border-color: #fc510b;
        }

        .abtestkit-review-actions .button.button-primary:hover,
        .abtestkit-review-actions .button.button-primary:focus {
            background: #e74807;
            border-color: #e74807;
        }
    </style>

    <div class="abtestkit-review-overlay" id="abtestkit-review-overlay">
        <div class="abtestkit-review-modal" role="dialog" aria-modal="true" aria-labelledby="abtestkit-review-title">
            <button type="button" class="abtestkit-review-close" id="abtestkit-review-close" aria-label="<?php echo esc_attr__( 'Maybe later', 'abtestkit' ); ?>">×</button>

            <div class="abtestkit-review-stars" aria-hidden="true">★★★★★</div>

            <h2 class="abtestkit-review-title" id="abtestkit-review-title">
                <?php echo esc_html__( 'You’ve now successfully launched multiple tests with abtestkit', 'abtestkit' ); ?>
            </h2>

            <p class="abtestkit-review-copy">
                <?php echo esc_html__( 'Thanks for using abtestkit to run live A/B tests on your site. Would you mind taking a minute to leave us a quick review? It really helps more people discover the plugin.', 'abtestkit' ); ?>
            </p>

            <div class="abtestkit-review-actions">
                <a
                    href="<?php echo esc_url( $review_url ); ?>"
                    class="button button-primary"
                    id="abtestkit-review-leave"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php echo esc_html__( 'Leave a review', 'abtestkit' ); ?>
                </a>

                <a href="<?php echo esc_url( $later_url ); ?>" class="button">
                    <?php echo esc_html__( 'Maybe later', 'abtestkit' ); ?>
                </a>

                <a href="<?php echo esc_url( $never_url ); ?>" class="button">
                    <?php echo esc_html__( 'Don’t ask again', 'abtestkit' ); ?>
                </a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var overlay = document.getElementById('abtestkit-review-overlay');
        var closeButton = document.getElementById('abtestkit-review-close');
        var leaveButton = document.getElementById('abtestkit-review-leave');
        var laterUrl = <?php echo wp_json_encode( esc_url_raw( $later_url ) ); ?>;

        if (!overlay) {
            return;
        }

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                window.location.href = laterUrl;
            });
        }

        if (leaveButton) {
            leaveButton.addEventListener('click', function () {
                overlay.style.display = 'none';
            });
        }
    }());
    </script>
    <?php
} );

/**
 * Enqueue frontend tracking script on single posts (and inject live variants directly from saved block content).
 */
add_action('wp_enqueue_scripts', function () {


// Load on single products/pages + Woo shop, category, tag, archives
    $is_woo_catalog = false;

    // Only call WooCommerce conditionals if WooCommerce is active.
    if ( function_exists( 'is_shop' ) ) {
        $is_woo_catalog =
            is_shop()
            || ( function_exists( 'is_product_category' ) && is_product_category() )
            || ( function_exists( 'is_product_tag' ) && is_product_tag() )
            || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
    }

    // Bail early unless we're on a single post/page OR a Woo shop/category/tag/tax page.
    if ( ! is_singular() && ! $is_woo_catalog ) {
        return;
    }

    if ( abtestkit_is_custom_css_picker_preview_request() ) {
        return;
    }

    if ( abtestkit_is_exempt_viewer() ) return;

    $plugin_dir = plugin_dir_url(__FILE__);

    wp_enqueue_script(
        'abtestkit-frontend',
        $plugin_dir . 'assets/js/frontend.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/frontend.js'),
        true
    );

    $post_id = get_the_ID();

    // Parse the saved block tree so we can mirror test payloads into JS
    $content = get_post_field('post_content', $post_id);
    $blocks  = parse_blocks($content);

    // Base config sent to the browser (WP REST + HMAC fallback)
    $frontendConfig = [
        'postId'  => $post_id,
        'index'   => 0,
        'nonce'   => wp_create_nonce('wp_rest'),
        'restUrl' => esc_url_raw( rest_url( 'abtestkit/v1' ) ),
    ];

    // If this page is part of a full-page/product test, expose the REAL request state to JS.
    // Forced previews must resolve paused/previewable tests too.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $preview_flag_js = isset( $_GET['abtestkit_preview'] )
        ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_preview'] ) )
        : '';

    $raw_force_js = isset( $_GET['abtestkit_force'] )
        ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_force'] ) ) )
        : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $force_variant_js = ( $preview_flag_js === '1' && in_array( $raw_force_js, [ 'A', 'B' ], true ) )
        ? $raw_force_js
        : '';

    if ( $force_variant_js !== '' ) {
        list( $pt, $pt_role ) = abtestkit_pt_find_by_post_any_status( (int) $post_id );
    } else {
        list( $pt, $pt_role ) = abtestkit_pt_find_by_post( (int) $post_id );
    }

    if ( $pt && isset( $pt['id'] ) ) {

        $variant_letter    = '';
        $request_is_https  = abtestkit_request_is_real_https();
        $protocol_label    = $request_is_https ? 'https' : 'http';
        $no_track          = ! $request_is_https;
        $http_excluded     = ! $request_is_https;

        // 1) First choice: the assignment already decided earlier in this request.
        if (
            isset( $GLOBALS['abtestkit_current_pt_assignment'] )
            && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
        ) {
            $ctx      = $GLOBALS['abtestkit_current_pt_assignment'];
            $ctx_test = $ctx['test'] ?? null;
            $ctx_id   = ( is_array( $ctx_test ) && isset( $ctx_test['id'] ) ) ? (string) $ctx_test['id'] : '';

            if ( $ctx_id !== '' && $ctx_id === (string) $pt['id'] ) {
                $ctx_variant = isset( $ctx['variant'] ) ? (string) $ctx['variant'] : '';
                if ( $ctx_variant === 'A' || $ctx_variant === 'B' ) {
                    $variant_letter = $ctx_variant;
                }

                if ( isset( $ctx['protocol'] ) ) {
                    $protocol_label = (string) $ctx['protocol'];
                }

                if ( isset( $ctx['no_track'] ) ) {
                    $no_track = (bool) $ctx['no_track'];
                }

                if ( $protocol_label === 'http' ) {
                    $http_excluded = true;
                }
            }
        }

        // 2) Fallback for product tests.
        if ( $variant_letter === '' && isset( $pt['kind'] ) && $pt['kind'] === 'product' ) {
            list( $prod_test, $prod_variant ) = abtestkit_get_active_product_test_for_product( (int) $post_id );
            if ( $prod_test && isset( $prod_test['id'] ) && (string) $prod_test['id'] === (string) $pt['id'] ) {
                if ( $prod_variant === 'A' || $prod_variant === 'B' ) {
                    $variant_letter = $prod_variant;
                }
            }
        }

        // 3) Final fallback.
        if ( $variant_letter === '' ) {
            if ( ! $request_is_https ) {
                $variant_letter = 'A';
            } else {
                $variant_letter = ( $pt_role === 'control' ) ? 'A' : ( ( $pt_role === 'variant' ) ? 'B' : '' );
            }
        }

        if ( $variant_letter === 'A' || $variant_letter === 'B' ) {

            $links = [];
            if ( ! empty( $pt['links'] ) && is_array( $pt['links'] ) ) {
                $links = array_values(
                    array_map( 'strval', $pt['links'] )
                );
            }

            $frontendConfig['__pageTest'] = [
                'id'           => (string) $pt['id'],
                'variant'      => $variant_letter,
                'goal'         => isset( $pt['goal'] ) ? (string) $pt['goal'] : '',
                'links'        => $links,
                'protocol'     => $protocol_label,
                'noTrack'      => $no_track,
                'httpExcluded' => $http_excluded,
            ];
        }
    }

    // On Woo product listing views (shop, category, tag, taxonomy archives),
    // surface any active PRODUCT tests for the products in the main loop so
    // frontend JS can attribute add_to_cart events from cards correctly.
    if (
        function_exists( 'is_shop' )
        && function_exists( 'wc_get_product' )
        && ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() )
    ) {
        global $wp_query;

        if ( $wp_query && ! empty( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
            $productTests = [];

            foreach ( $wp_query->posts as $loop_post ) {
                $product_id = 0;

                if ( is_object( $loop_post ) && isset( $loop_post->ID ) ) {
                    $product_id = (int) $loop_post->ID;
                } elseif ( is_numeric( $loop_post ) ) {
                    $product_id = (int) $loop_post;
                }

                if ( $product_id <= 0 ) {
                    continue;
                }

                // Ask the existing helper which PRODUCT test (if any) applies to this product
                // and which variant (A/B) this viewer is assigned to.
                [ $prod_test, $prod_variant ] = abtestkit_get_active_product_test_for_product( $product_id );

                if ( ! $prod_test || ! isset( $prod_test['id'] ) ) {
                    continue;
                }

                $goal = isset( $prod_test['goal'] ) ? (string) $prod_test['goal'] : '';

                $productTests[ (string) $product_id ] = [
                    'id'      => (string) $prod_test['id'],
                    'variant' => ( $prod_variant === 'B' ? 'B' : 'A' ),
                    'goal'    => $goal,
                ];
            }

            if ( ! empty( $productTests ) ) {
                $frontendConfig['__productTests'] = $productTests;
            }
        }
    }

    // For “button converts other tests” relations
    $clickTargetMap = [];

    $extract_variants = function(array $blocks) use (&$extract_variants, &$frontendConfig, &$clickTargetMap) {
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['blockName'])) continue;

            $attrs            = $block['attrs'] ?? [];
            $abTestId         = $attrs['abTestId'] ?? null;
            $abTestVariants   = $attrs['abTestVariants'] ?? null;
            $conversionFrom   = $attrs['conversionFrom'] ?? [];

            // If the block lists tests it “converts”, remember that mapping
            foreach ((array) $conversionFrom as $sourceId) {
                if (!isset($clickTargetMap[$sourceId])) $clickTargetMap[$sourceId] = [];
                $clickTargetMap[$sourceId][] = $abTestId;
            }

            // Only build a payload when this block actually hosts a test
            if ($abTestId && is_array($abTestVariants) && isset($abTestVariants[$abTestId])) {
                // Prefer values already stored inside abTestVariants[abTestId] (they persist across reloads),
                // then fall back to the loose block attributes, then defaults.
                $stored = $abTestVariants[$abTestId];

                // Goal: prefer stored → then loose attribute → default
                $goal = $stored['conversionGoalType'] ?? ($attrs['conversionGoalType'] ?? 'button');
                // Allow clicks, forms and Woo "add_to_cart" goals (treated like form submit on the frontend)
                $goal = in_array( $goal, [ 'button', 'link', 'form', 'add_to_cart' ], true ) ? $goal : 'button';

                // Links: prefer stored → then loose attribute → []
                $links = [];
                if (!empty($stored['conversionLinks']) && is_array($stored['conversionLinks'])) {
                    $links = array_values(array_filter(array_map('strval', $stored['conversionLinks'])));
                } elseif (!empty($attrs['conversionLinks']) && is_array($attrs['conversionLinks'])) {
                    $links = array_values(array_filter(array_map('strval', $attrs['conversionLinks'])));
                }

                // Attach canonical values back to the per-test object
                $abTestVariants[$abTestId]['conversionGoalType'] = $goal;
                $abTestVariants[$abTestId]['conversionLinks']    = $links;

                // “This button converts these tests”: prefer loose attr (UI is source of truth), else stored
                $from = [];
                if (!empty($attrs['conversionFrom']) && is_array($attrs['conversionFrom'])) {
                    $from = array_values(array_unique(array_filter(array_map('strval', $attrs['conversionFrom']))));
                } elseif (!empty($stored['conversionFrom']) && is_array($stored['conversionFrom'])) {
                    $from = array_values(array_unique(array_filter(array_map('strval', $stored['conversionFrom']))));
                }
                $abTestVariants[$abTestId]['conversionFrom'] = $from;

                // Running flag: unlocked tests are “running”
                $locked = $abTestVariants[$abTestId]['locked'] ?? true;
                $abTestVariants[$abTestId]['running'] = !$locked;

                // Merge grouped tests from whatever we calculated plus anything already saved
                $storedGrouped     = $abTestVariants[$abTestId]['groupedAbTests'] ?? [];
                $calculatedGrouped = $clickTargetMap[$abTestId] ?? [];
                $mergedGrouped     = array_values(array_unique(array_merge($storedGrouped, $calculatedGrouped)));

                $abTestVariants[$abTestId]['groupedAbTests'] = $mergedGrouped;
                // Keep the earlier-resolved $from (attrs preferred, else stored), do not overwrite it here.
                $abTestVariants[$abTestId]['conversionFrom'] = $from;


                // Finally, expose this test on window.abTestConfig under its ID
                $frontendConfig[$abTestId] = $abTestVariants[$abTestId];
            }

            // Recurse
            if (!empty($block['innerBlocks'])) {
                $extract_variants($block['innerBlocks']);
            }
        }
    };

    $extract_variants($blocks);

    wp_localize_script('abtestkit-frontend', 'abTestConfig', $frontendConfig);
});


register_activation_hook(__FILE__, function () {
    abtestkit_create_event_table();

    if ( function_exists( 'abtestkit_telemetry_schedule_heartbeat' ) ) {
        abtestkit_telemetry_schedule_heartbeat();
    }
    if ( ! get_option( ABTESTKIT_TELEMETRY_INSTALL_OPTION ) ) {
        update_option( ABTESTKIT_TELEMETRY_INSTALL_OPTION, time() );
    }
    if ( abtestkit_is_telemetry_opted_in() && ! abtestkit_flag_is_set('installed_sent') ) {
        abtestkit_send_telemetry( 'plugin_installed', [
            'installed_at' => (int) get_option( ABTESTKIT_TELEMETRY_INSTALL_OPTION ),
        ] );
        abtestkit_mark_flag('installed_sent');
    }
});

/**
 * Delete an ABTestKit-owned shadow Version B post/product/page/reusable section.
 *
 * Safety rule:
 * - Only deletes posts explicitly marked with _abtestkit_shadow = 1.
 * - Never deletes user-owned "existing page/product as Version B" content.
 */
function abtestkit_delete_owned_shadow_variant( int $post_id ) : bool {
    $post_id = (int) $post_id;

    if ( $post_id <= 0 || ! get_post( $post_id ) ) {
        return false;
    }

    if ( (int) get_post_meta( $post_id, '_abtestkit_shadow', true ) !== 1 ) {
        return false;
    }

    $post_type = get_post_type( $post_id );

    // Variable-product shadows can have shadow child variations. Delete children first.
    if ( $post_type === 'product' ) {
        $child_ids = get_children(
            [
                'post_parent'    => $post_id,
                'post_type'      => 'product_variation',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]
        );

        if ( is_array( $child_ids ) && ! empty( $child_ids ) ) {
            foreach ( $child_ids as $child_id ) {
                $child_id = (int) $child_id;

                if ( $child_id > 0 ) {
                    wp_delete_post( $child_id, true );
                    clean_post_cache( $child_id );
                }
            }
        }
    }

    $deleted = wp_delete_post( $post_id, true );

    if ( $deleted ) {
        clean_post_cache( $post_id );

        if ( function_exists( 'abtestkit_clear_shadow_counts_cache' ) ) {
            abtestkit_clear_shadow_counts_cache( (string) $post_type );
        }

        return true;
    }

    return false;
}

/**
 * On deactivation, stop tests from silently resuming if the user reactivates.
 *
 * We intentionally do NOT delete user content on deactivation.
 * Full deletion happens on uninstall.
 */
function abtestkit_pause_tests_for_safe_deactivation() : void {
    $option_name = defined( 'ABTESTKIT_PAGE_TESTS_OPTION' ) ? ABTESTKIT_PAGE_TESTS_OPTION : 'abtestkit_page_tests';
    $tests       = get_option( $option_name, [] );

    if ( ! is_array( $tests ) || empty( $tests ) ) {
        return;
    }

    $changed = false;
    $now     = time();

    foreach ( $tests as &$test ) {
        if ( ! is_array( $test ) ) {
            continue;
        }

        if ( (string) ( $test['status'] ?? '' ) === 'running' ) {
            $test['status'] = 'paused';

            if ( empty( $test['paused_at'] ) ) {
                $test['paused_at'] = $now;
            }

            if ( ! isset( $test['paused_total'] ) ) {
                $test['paused_total'] = 0;
            }

            $changed = true;
        }
    }
    unset( $test );

    if ( $changed ) {
        update_option( $option_name, $tests, false );
    }
}

/**
 * Full ABTestKit uninstall cleanup.
 *
 * Deletes:
 * - ABTestKit-created shadow Version B objects.
 * - Stored tests.
 * - ABTestKit options/transients.
 * - ABTestKit post meta markers.
 *
 * Does NOT delete user-owned existing content used as Version B.
 */
function abtestkit_nuke_owned_plugin_state() : void {
    global $wpdb;

    $option_name = defined( 'ABTESTKIT_PAGE_TESTS_OPTION' ) ? ABTESTKIT_PAGE_TESTS_OPTION : 'abtestkit_page_tests';
    $tests       = get_option( $option_name, [] );
    $tests       = is_array( $tests ) ? $tests : [];

    $shadow_ids = [];

    foreach ( $tests as $test ) {
        if ( ! is_array( $test ) ) {
            continue;
        }

        $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        $test_id    = isset( $test['id'] ) ? (string) $test['id'] : '';

        if ( $variant_id <= 0 ) {
            continue;
        }

        if ( (int) get_post_meta( $variant_id, '_abtestkit_shadow', true ) === 1 ) {
            $shadow_ids[] = $variant_id;
        } else {
            // Existing user-owned B content: only remove ABTestKit markers.
            if ( function_exists( 'abtestkit_pt_unmark_existing_variant_in_use' ) ) {
                abtestkit_pt_unmark_existing_variant_in_use( $variant_id, $test_id );
            } else {
                delete_post_meta( $variant_id, '_abtestkit_variant_in_use' );
                delete_post_meta( $variant_id, '_abtestkit_variant_of' );
                delete_post_meta( $variant_id, '_abtestkit_variant_test_id' );
            }
        }
    }

    // Also catch any orphan shadow objects not present in the test registry.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $orphan_shadow_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT post_id
             FROM {$wpdb->postmeta}
             WHERE meta_key = %s
             AND meta_value = %s",
            '_abtestkit_shadow',
            '1'
        )
    );

    if ( is_array( $orphan_shadow_ids ) ) {
        foreach ( $orphan_shadow_ids as $orphan_shadow_id ) {
            $shadow_ids[] = (int) $orphan_shadow_id;
        }
    }

    $shadow_ids = array_values( array_unique( array_filter( array_map( 'absint', $shadow_ids ) ) ) );

    foreach ( $shadow_ids as $shadow_id ) {
        abtestkit_delete_owned_shadow_variant( $shadow_id );
    }

    // Remove ABTestKit meta mirrors/markers from any remaining user-owned posts.
    $meta_keys = [
        '_abtestkit_variants',
        '_abtestkit_variant_in_use',
        '_abtestkit_variant_of',
        '_abtestkit_variant_test_id',
    ];

    foreach ( $meta_keys as $meta_key ) {
        if ( function_exists( 'delete_post_meta_by_key' ) ) {
            delete_post_meta_by_key( $meta_key );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $meta_key
                )
            );
        }
    }

    // Delete known ABTestKit options.
    $options = [
        $option_name,
        'abtestkit_do_activation_redirect',
        'abtestkit_events_schema_version',
        'abtestkit_fixed_version_b_titles',
        'abtestkit_onboarding_done',
        'abtestkit_telemetry_opted_in',
        'abtestkit_telemetry_flags',
        'abtestkit_telemetry_installed_at',
        'abtestkit_telemetry_tests_created',
        'abtestkit_review_prompt_state',
    ];

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Delete ABTestKit transients and transient timeouts.
    $like_patterns = [
        '_transient_abtestkit_%',
        '_transient_timeout_abtestkit_%',
    ];

    foreach ( $like_patterns as $pattern ) {
        $like = $wpdb->esc_like( $pattern );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
    }
}

// Deactivation: pause running tests, remove rate-limit transients and flush page/object caches
register_deactivation_hook(__FILE__, 'abtestkit_on_deactivate');
function abtestkit_on_deactivate() {
    if ( function_exists( 'abtestkit_telemetry_unschedule_heartbeat' ) ) {
        abtestkit_telemetry_unschedule_heartbeat();
    }

    // Safety: if a site owner deactivates because something broke, do not let
    // a running test silently resume if they reactivate/reinstall.
    abtestkit_pause_tests_for_safe_deactivation();

    global $wpdb;

    // Maintenance: clear this plugin’s rate-limit transients on deactivation.
    $patterns = [
        '_transient_abtestkit_%',
        '_transient_timeout_abtestkit_%',
    ];

    foreach ( $patterns as $pattern ) {
        $like      = $wpdb->esc_like( $pattern );
        $cache_key = 'abtestkit_deactivate_option_names_' . md5( $like );
        $option_names = wp_cache_get( $cache_key, 'abtestkit' );

        if ( false === $option_names ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $option_names = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $like
                )
            );

            wp_cache_set( $cache_key, $option_names, 'abtestkit', MINUTE_IN_SECONDS );
        }

        if ( empty( $option_names ) || ! is_array( $option_names ) ) {
            continue;
        }

        foreach ( $option_names as $option_name ) {
            $option_name = (string) $option_name;

            if ( strpos( $option_name, '_transient_timeout_' ) === 0 ) {
                delete_transient( substr( $option_name, strlen( '_transient_timeout_' ) ) );
            } elseif ( strpos( $option_name, '_transient_' ) === 0 ) {
                delete_transient( substr( $option_name, strlen( '_transient_' ) ) );
            }
        }
    }


    // Best-effort cache flushers (object cache + common caching plugins)
    if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
    if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); }
    if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); }
    if (function_exists('autoptimize_flush_cache')) { autoptimize_flush_cache(); }
    // Purge LiteSpeed Cache via documented external hook.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (class_exists('LiteSpeed_Cache')) { do_action('litespeed_purge_all'); }
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) { wp_cache_clear_cache(); }
}

// ---  Helper to strip AB attributes from block trees ---
    function abtestkit_strip_ab_attrs_from_blocks(array $blocks, &$changed = false) {
        $out = [];
        foreach ($blocks as $b) {
         if (!is_array($b)) { $out[] = $b; continue; }

            // Remove all AB-related attributes the plugin added
         $attrs = isset($b['attrs']) && is_array($b['attrs']) ? $b['attrs'] : [];
         $keys  = [
            'abTestEnabled','abTestVariants','abTestId','abTestRunning','abTestWinner',
            'abTestResultsViewed','conversionFrom','abTestLastUnlocked','abTestStartedAt',
            'abTestFinishedAt','abSync','abGroupKey'
        ];
        $touched = false;
        foreach ($keys as $k) {
            if (isset($attrs[$k])) { unset($attrs[$k]); $touched = true; }
        }
        if ($touched) { $changed = true; }
        $b['attrs'] = $attrs;

        // Recurse innerBlocks
        if (!empty($b['innerBlocks']) && is_array($b['innerBlocks'])) {
            $b['innerBlocks'] = abtestkit_strip_ab_attrs_from_blocks($b['innerBlocks'], $changed);
        }
        $out[] = $b;
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// abtestkit – Page Duplicator A/B Tests (MVP Dashboard)
// ─────────────────────────────────────────────────────────────────────────────

if ( ! defined( 'ABTESTKIT_PAGE_TESTS_OPTION' ) ) {
    define( 'ABTESTKIT_PAGE_TESTS_OPTION', 'abtestkit_page_tests' );
}

/**
 * Automatically pause a running test when its health check says it is broken.
 *
 * This is intentionally based on the same health summary used in the dashboard
 * and performance screen, so the automatic safety behaviour matches what the
 * user can already see in the UI.
 *
 * @return array{0: array, 1: bool}
 */
function abtestkit_pt_maybe_auto_pause_broken_test( array $test ) : array {
    $status = isset( $test['status'] ) ? (string) $test['status'] : 'paused';

    if ( $status !== 'running' ) {
        return [ $test, false ];
    }

    if ( ! function_exists( 'abtestkit_pt_health_summary' ) ) {
        return [ $test, false ];
    }

    $health = abtestkit_pt_health_summary( $test, abtestkit_pt_stats_default() );

    if ( ! is_array( $health ) || ( (string) ( $health['status'] ?? '' ) ) !== 'broken' ) {
        return [ $test, false ];
    }

    $now = current_time( 'timestamp' );

    $test['status']             = 'paused';
    $test['paused_at']          = empty( $test['paused_at'] ) ? $now : (int) $test['paused_at'];
    $test['paused_total']       = isset( $test['paused_total'] ) ? max( 0, (int) $test['paused_total'] ) : 0;
    $test['auto_paused_broken'] = 1;

    if ( empty( $test['auto_paused_broken_at'] ) ) {
        $test['auto_paused_broken_at'] = $now;
    }

    $test['auto_paused_broken_summary'] = sanitize_text_field( (string) ( $health['summary'] ?? '' ) );

    $broken_checks = [];
    foreach ( (array) ( $health['checks'] ?? [] ) as $check ) {
        if ( ! is_array( $check ) || ( (string) ( $check['status'] ?? '' ) ) !== 'broken' ) {
            continue;
        }

        $title = sanitize_text_field( (string) ( $check['title'] ?? '' ) );
        if ( $title !== '' ) {
            $broken_checks[] = $title;
        }
    }

    $test['auto_paused_broken_checks'] = array_slice( array_values( array_unique( $broken_checks ) ), 0, 5 );

    if ( ! empty( $test['id'] ) && function_exists( 'abtestkit_pt_flush_test_caches' ) ) {
        abtestkit_pt_flush_test_caches( (string) $test['id'] );
    }

    return [ $test, true ];
}

/**
 * Registry helpers (stored as a single option).
 * Test shape: id, title, control_id, variant_id, status(paused|running|complete|draft),
 * split (0..100 => % to B), cookie_ttl_days, started_at, finished_at
 */
function abtestkit_pt_all() : array {
    $tests = get_option( ABTESTKIT_PAGE_TESTS_OPTION, [] );

    if ( ! is_array( $tests ) ) {
        return [];
    }

    /*
     * Registry reads happen from frontend hooks, WooCommerce data stores and
     * health checks. Never let a nested product/filter callback normalise the
     * registry again while the outer read is still in progress.
     */
    static $normalising = false;

    if ( $normalising ) {
        return $tests;
    }

    $normalising = true;

    $changed = false;

    foreach ( $tests as &$t ) {
        if ( ! is_array( $t ) || empty( $t['id'] ) ) {
            continue;
        }

        // Normalise status – default to "paused"
        if ( empty( $t['status'] ) ) {
            $t['status'] = 'paused';
            $changed     = true;
        } else {
            $valid_statuses = [ 'paused', 'running', 'winner', 'complete', 'draft' ];
            if ( ! in_array( $t['status'], $valid_statuses, true ) ) {
                $t['status'] = 'paused';
                $changed     = true;
            }
        }

        // If a test has a finished_at timestamp but is still "running", mark it complete.
        $finished_at = isset( $t['finished_at'] ) ? (int) $t['finished_at'] : 0;
        if ( $finished_at > 0 && $t['status'] === 'running' ) {
            $t['status'] = 'complete';
            $changed     = true;
        }

        // Normalise pause bookkeeping fields so older installs don't break.
        $paused_at = isset( $t['paused_at'] ) ? (int) $t['paused_at'] : 0;
        if ( $paused_at < 0 ) { $paused_at = 0; }
        if ( ! isset( $t['paused_at'] ) || (int) $t['paused_at'] !== $paused_at ) {
            $t['paused_at'] = $paused_at;
            $changed        = true;
        }

        $paused_total = isset( $t['paused_total'] ) ? (int) $t['paused_total'] : 0;
        if ( $paused_total < 0 ) { $paused_total = 0; }
        if ( ! isset( $t['paused_total'] ) || (int) $t['paused_total'] !== $paused_total ) {
            $t['paused_total'] = $paused_total;
            $changed           = true;
        }

        $started_at = isset( $t['started_at'] ) ? (int) $t['started_at'] : 0;
        if ( $started_at < 0 ) { $started_at = 0; }
        if ( ! isset( $t['started_at'] ) || (int) $t['started_at'] !== $started_at ) {
            $t['started_at'] = $started_at;
            $changed         = true;
        }

        // Safety: if a "running" test has lost both page references, pause it.
        if ( $t['status'] === 'running' ) {
            $control_id = isset( $t['control_id'] ) ? (int) $t['control_id'] : 0;
            $variant_id = isset( $t['variant_id'] ) ? (int) $t['variant_id'] : 0;

            if ( $control_id <= 0 && $variant_id <= 0 ) {
                $t['status'] = 'paused';
                $changed     = true;
            }
        }

        if ( $t['status'] === 'running' && function_exists( 'abtestkit_pt_maybe_auto_pause_broken_test' ) ) {
            list( $maybe_paused_test, $was_auto_paused ) = abtestkit_pt_maybe_auto_pause_broken_test( $t );

            if ( $was_auto_paused ) {
                $t       = $maybe_paused_test;
                $changed = true;
            }
        }

        // Infer missing test kind for older installs/tests.
        if ( empty( $t['kind'] ) ) {
            $control_id = isset( $t['control_id'] ) ? (int) $t['control_id'] : 0;
            $control_type = $control_id > 0 ? get_post_type( $control_id ) : '';

            if ( $control_type === 'product' ) {
                $t['kind'] = 'product';
                $changed   = true;
            } elseif ( $control_type === 'post' ) {
                $t['kind'] = 'post';
                $changed   = true;
            } else {
                $t['kind'] = 'page';
                $changed   = true;
            }
        }

        // Decision settings (new) — defaults for older installs/tests.
        if ( empty( $t['decision_rule'] ) ) {
            $t['decision_rule'] = 'balanced';
            $changed            = true;
        }
        if ( empty( $t['decision_mode'] ) ) {
            // If rule is manual, mode must be manual.
            $t['decision_mode'] = ( (string) $t['decision_rule'] === 'manual' ) ? 'manual' : 'auto';
            $changed            = true;
        }

        // Normalise thresholds.
        $rule = (string) $t['decision_rule'];
        $mode = (string) $t['decision_mode'];

        // Manual mode: never auto-declare winner => allow 0/0 thresholds.
        if ( $mode === 'manual' || $rule === 'manual' ) {
            if ( (int) ( $t['min_impressions'] ?? -1 ) !== 0 ) {
                $t['min_impressions'] = 0;
                $changed              = true;
            }
            if ( (int) ( $t['min_conversions'] ?? -1 ) !== 0 ) {
                $t['min_conversions'] = 0;
                $changed              = true;
            }
        } else {
            // Auto mode: only allow preset thresholds.
            $allowed_mi = [ 25, 50, 75 ];
            $allowed_mc = [ 3, 5, 10 ];

            $mi = isset( $t['min_impressions'] ) ? (int) $t['min_impressions'] : 0;
            $mc = isset( $t['min_conversions'] ) ? (int) $t['min_conversions'] : 0;

            if ( ! in_array( $mi, $allowed_mi, true ) ) {
                $t['min_impressions'] = 50;
                $changed              = true;
            }
            if ( ! in_array( $mc, $allowed_mc, true ) ) {
                $t['min_conversions'] = 5;
                $changed              = true;
            }

            // Keep rule presets consistent even if data got edited.
            if ( $rule === 'fast' )    { $t['min_impressions'] = 25; $t['min_conversions'] = 3; $changed = true; }
            if ( $rule === 'balanced' ){ $t['min_impressions'] = 50; $t['min_conversions'] = 5; $changed = true; }
            if ( $rule === 'precise' ) { $t['min_impressions'] = 75; $t['min_conversions'] = 10; $changed = true; }
        }
    }
    unset( $t ); // break reference

    if ( $changed ) {
        // Persist the normalised test list back to the option so stale
        // "running" tests stop blocking new ones.
        update_option( ABTESTKIT_PAGE_TESTS_OPTION, $tests, false );
    }

    $normalising = false;

    return $tests;
}

function abtestkit_pt_save( array $tests ) {
    update_option( ABTESTKIT_PAGE_TESTS_OPTION, $tests, false );
}
function abtestkit_pt_get( string $id ) : ?array {
    foreach ( abtestkit_pt_all() as $t ) {
        if ( isset( $t['id'] ) && $t['id'] === $id ) return $t;
    }
    return null;
}
function abtestkit_pt_put( array $test ) {
    $tests = abtestkit_pt_all();
    $found = false;
    foreach ( $tests as &$t ) {
        if ( $t['id'] === $test['id'] ) { $t = $test; $found = true; break; }
    }
    if ( ! $found ) $tests[] = $test;
    abtestkit_pt_save( $tests );

    if ( ! empty( $test['id'] ) ) {
        abtestkit_pt_flush_test_caches( (string) $test['id'] );
    }
}
function abtestkit_pt_delete( string $id ) {
    $all = abtestkit_pt_all();

    // Find the test before deleting it (so we can clean up any "existing B" SEO markers).
    $test = null;
    foreach ( $all as $t ) {
        if ( ( $t['id'] ?? '' ) === $id ) {
            $test = $t;
            break;
        }
    }

    if ( is_array( $test ) ) {
        $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;

        // Only unmark existing variants (never touch shadow duplicates).
        if ( $variant_id > 0 && ! abtestkit_is_shadow_variant( $variant_id ) ) {
            abtestkit_pt_unmark_existing_variant_in_use( $variant_id, (string) $id );
        }
    }

    $tests = array_values(
        array_filter(
            $all,
            fn( $t ) => ( $t['id'] ?? '' ) !== $id
        )
    );

    if ( is_array( $test ) && ! empty( $test['id'] ) ) {
        abtestkit_pt_flush_test_caches( (string) $test['id'] );
    }

    if ( function_exists( 'abtestkit_html_runtime_health_delete' ) ) {
        abtestkit_html_runtime_health_delete( $id );
    }

    abtestkit_pt_save( $tests );
}

/**
 * Wizard safety: track the last "duplicate" created for a user+control so we don't
 * accidentally create multiple Version B posts (especially for products).
 */
function abtestkit_pt_last_duplicate_key( int $control_id, int $user_id ) : string {
    return 'abtestkit_pt_last_dup_' . (int) $user_id . '_' . (int) $control_id;
}

/** True if a post ID is already the variant_id of any stored test (any status). */
function abtestkit_pt_variant_id_in_any_test( int $variant_id ) : bool {
    $variant_id = (int) $variant_id;
    if ( $variant_id <= 0 ) return false;

    foreach ( abtestkit_pt_all() as $t ) {
        if ( (int) ( $t['variant_id'] ?? 0 ) === $variant_id ) {
            return true;
        }
    }
    return false;
}

/**
 * Return the last duplicate for this user+control if it still looks valid and isn't already in use.
 * Also validates shadow relationship for products.
 */
function abtestkit_pt_get_last_duplicate_for_user( int $control_id, int $user_id ) : int {
    $control_id = (int) $control_id;
    $user_id    = (int) $user_id;
    if ( $control_id <= 0 || $user_id <= 0 ) return 0;

    $key = abtestkit_pt_last_duplicate_key( $control_id, $user_id );
    $pid = (int) get_transient( $key );
    if ( $pid <= 0 ) return 0;

    $p = get_post( $pid );
    if ( ! $p ) return 0;

    $control_type = get_post_type( $control_id );
    if ( ! $control_type || $p->post_type !== $control_type ) return 0;

    // Don't reuse if it is already used by any test.
    if ( abtestkit_pt_variant_id_in_any_test( $pid ) ) return 0;

    // Product safety: must be a shadow of this control.
    if ( $control_type === 'product' ) {
        if ( ! function_exists( 'abtestkit_is_shadow_product' ) || ! abtestkit_is_shadow_product( $pid ) ) return 0;
        $shadow_of = (int) get_post_meta( $pid, '_abtestkit_shadow_of', true );
        if ( $shadow_of !== $control_id ) return 0;
    }

    return $pid;
}

function abtestkit_pt_set_last_duplicate_for_user( int $control_id, int $variant_id, int $user_id, int $ttl_seconds = 1800 ) : void {
    $control_id = (int) $control_id;
    $variant_id = (int) $variant_id;
    $user_id    = (int) $user_id;

    if ( $control_id <= 0 || $variant_id <= 0 || $user_id <= 0 ) return;

    $ttl_seconds = max( 60, (int) $ttl_seconds );
    set_transient( abtestkit_pt_last_duplicate_key( $control_id, $user_id ), $variant_id, $ttl_seconds );
}

function abtestkit_pt_clear_last_duplicate_for_user( int $control_id, int $user_id ) : void {
    $control_id = (int) $control_id;
    $user_id    = (int) $user_id;

    if ( $control_id <= 0 || $user_id <= 0 ) return;
    delete_transient( abtestkit_pt_last_duplicate_key( $control_id, $user_id ) );
}

/**
 * Find the best RUNNING test by page id.
 * Returns [test, "control"|"variant"].
 * If multiple running tests match, pick the newest (highest started_at).
 */
function abtestkit_pt_find_by_post( int $post_id ) : array {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) return [ null, '' ];

    $variant_matches = [];
    $control_matches = [];

    foreach ( abtestkit_pt_all() as $t ) {
        if ( ! is_array( $t ) ) continue;
        if ( ( $t['status'] ?? 'paused' ) !== 'running' ) continue;
        if ( ( $t['kind'] ?? '' ) === 'reusable_section' ) continue;

        if ( (int) ( $t['variant_id'] ?? 0 ) === $post_id ) {
            $variant_matches[] = $t;
            continue;
        }

        if ( (int) ( $t['control_id'] ?? 0 ) === $post_id ) {
            $control_matches[] = $t;
        }
    }

    // Variant match should win immediately (unique URL for PAGE tests).
    if ( count( $variant_matches ) === 1 ) {
        return [ $variant_matches[0], 'variant' ];
    }
    if ( count( $variant_matches ) > 1 ) {
        if ( ! headers_sent() ) header( 'X-Abtestkit-Conflict: variant-in-multiple-tests' );
        return [ null, '' ];
    }

    // Control match: if multiple, pick newest by started_at.
    if ( empty( $control_matches ) ) return [ null, '' ];

    usort( $control_matches, function( $a, $b ) {
        $sa = (int) ( $a['started_at'] ?? 0 );
        $sb = (int) ( $b['started_at'] ?? 0 );
        if ( $sa !== $sb ) return $sb <=> $sa; // newest first
        return strcmp( (string) ( $b['id'] ?? '' ), (string) ( $a['id'] ?? '' ) );
    } );

    return [ $control_matches[0], 'control' ];
}

/**
 * Like abtestkit_pt_find_by_post() but allows resolving tests that are paused/complete/etc.
 * Used for preview forcing (?abtestkit_preview=1&abtestkit_force=A|B).
 */
function abtestkit_pt_find_by_post_any_status( int $post_id ) : array {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) return [ null, '' ];

    $variant_matches = [];
    $control_matches = [];

    foreach ( abtestkit_pt_all() as $t ) {
        if ( ! is_array( $t ) ) continue;

        // Skip drafts; allow paused/running/complete
        if ( ( $t['status'] ?? 'paused' ) === 'draft' ) continue;
        if ( ( $t['kind'] ?? '' ) === 'reusable_section' ) continue;

        if ( (int) ( $t['variant_id'] ?? 0 ) === $post_id ) {
            $variant_matches[] = $t;
            continue;
        }

        if ( (int) ( $t['control_id'] ?? 0 ) === $post_id ) {
            $control_matches[] = $t;
        }
    }

    // Variant match wins if unique.
    if ( count( $variant_matches ) === 1 ) {
        return [ $variant_matches[0], 'variant' ];
    }
    if ( count( $variant_matches ) > 1 ) {
        if ( ! headers_sent() ) header( 'X-Abtestkit-Conflict: variant-in-multiple-tests' );
        return [ null, '' ];
    }

    if ( empty( $control_matches ) ) return [ null, '' ];

    // Prefer running > paused > complete, then newest started_at.
    usort( $control_matches, function( $a, $b ) {
        $pa = function_exists( 'abtestkit_pt_status_priority' ) ? abtestkit_pt_status_priority( (string) ( $a['status'] ?? '' ) ) : 5;
        $pb = function_exists( 'abtestkit_pt_status_priority' ) ? abtestkit_pt_status_priority( (string) ( $b['status'] ?? '' ) ) : 5;

        if ( $pa !== $pb ) return $pa <=> $pb;

        $sa = (int) ( $a['started_at'] ?? 0 );
        $sb = (int) ( $b['started_at'] ?? 0 );
        if ( $sa !== $sb ) return $sb <=> $sa;

        return strcmp( (string) ( $b['id'] ?? '' ), (string) ( $a['id'] ?? '' ) );
    } );

    return [ $control_matches[0], 'control' ];
}

/**
 * Extract [embed_page id="123"] source IDs from a post-like object.
 *
 * We scan post_content, post_excerpt, and common builder meta. This avoids
 * running shortcodes or rendering builders during conflict checks.
 */
function abtestkit_pt_embedded_shortcode_source_ids_for_post( int $post_id, string $tag = 'embed_page', string $attribute = 'id' ) : array {
    static $cache = [];

    $post_id   = absint( $post_id );
    $tag       = sanitize_key( $tag );
    $attribute = sanitize_key( $attribute );

    if ( $post_id <= 0 || $tag === '' || $attribute === '' ) {
        return [];
    }

    $cache_key = $post_id . '|' . $tag . '|' . $attribute;

    if ( isset( $cache[ $cache_key ] ) ) {
        return $cache[ $cache_key ];
    }

    $post = get_post( $post_id );

    if ( ! $post ) {
        $cache[ $cache_key ] = [];
        return [];
    }

    $haystack = '';

    $haystack .= "\n" . (string) $post->post_content;
    $haystack .= "\n" . (string) $post->post_excerpt;

    /*
     * Builder/meta fields where shortcode-like content is commonly stored.
     * Kept intentionally small to avoid expensive whole-meta scans in the wizard.
     */
    $meta_keys = apply_filters(
        'abtestkit_reusable_section_conflict_meta_keys',
        [
            '_elementor_data',
            '_fl_builder_data',
            '_bricks_page_content',
            '_oxygen_builder_shortcodes',
            '_ct_builder_shortcodes',
        ],
        $post_id
    );

    foreach ( (array) $meta_keys as $meta_key ) {
        $meta_key = sanitize_key( (string) $meta_key );

        if ( $meta_key === '' ) {
            continue;
        }

        $values = get_post_meta( $post_id, $meta_key, false );

        foreach ( (array) $values as $value ) {
            if ( is_scalar( $value ) ) {
                $haystack .= "\n" . (string) $value;
            } elseif ( is_array( $value ) || is_object( $value ) ) {
                $encoded = wp_json_encode( $value );
                if ( is_string( $encoded ) ) {
                    $haystack .= "\n" . $encoded;
                }
            }
        }
    }

    if ( $haystack === '' ) {
        $cache[ $cache_key ] = [];
        return [];
    }

    $charset  = get_bloginfo( 'charset' );
    $haystack = html_entity_decode( $haystack, ENT_QUOTES, $charset ? $charset : 'UTF-8' );

    if ( strpos( $haystack, '[' . $tag ) === false ) {
        $cache[ $cache_key ] = [];
        return [];
    }

    $ids     = [];
    $pattern = get_shortcode_regex( [ $tag ] );

    if ( preg_match_all( '/' . $pattern . '/s', $haystack, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $found_tag = isset( $match[2] ) ? sanitize_key( (string) $match[2] ) : '';

            if ( $found_tag !== $tag ) {
                continue;
            }

            $raw_atts = isset( $match[3] ) ? (string) $match[3] : '';
            $atts     = shortcode_parse_atts( $raw_atts );

            if ( ! is_array( $atts ) ) {
                continue;
            }

            if ( isset( $atts[ $attribute ] ) ) {
                $id = absint( $atts[ $attribute ] );

                if ( $id > 0 ) {
                    $ids[] = $id;
                }
            }
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

    $cache[ $cache_key ] = $ids;

    return $ids;
}

/**
 * Does a host post/product/page embed a given reusable source page?
 */
function abtestkit_pt_post_embeds_reusable_source( int $host_id, int $source_id ) : bool {
    $host_id   = absint( $host_id );
    $source_id = absint( $source_id );

    if ( $host_id <= 0 || $source_id <= 0 ) {
        return false;
    }

    $ids = abtestkit_pt_embedded_shortcode_source_ids_for_post( $host_id, 'embed_page', 'id' );

    return in_array( $source_id, $ids, true );
}

/**
 * Return IDs of running tests that conflict with a proposed test.
 *
 * Normal direct conflict:
 * - A/B page IDs cannot be reused in another running test.
 *
 * Reusable-section conflict:
 * - A reusable-section source cannot be tested while a running page/product/post
 *   test embeds that source with [embed_page id="123"].
 * - A page/product/post cannot be tested while it embeds a source that already
 *   has a running reusable-section test.
 */
function abtestkit_pt_conflicts_for_pages( int $control_id, int $variant_id = 0, string $exclude_id = '', string $new_kind = '' ) : array {
    $control_id = absint( $control_id );
    $variant_id = absint( $variant_id );
    $new_kind   = sanitize_key( $new_kind );

    $conflicts = [];

    foreach ( abtestkit_pt_all() as $t ) {
        if ( ! is_array( $t ) ) {
            continue;
        }

        if ( ( $t['status'] ?? 'paused' ) !== 'running' ) {
            continue;
        }

        $existing_id = isset( $t['id'] ) ? (string) $t['id'] : '';

        if ( $exclude_id && $existing_id !== '' && $existing_id === $exclude_id ) {
            continue;
        }

        $existing_control = isset( $t['control_id'] ) ? absint( $t['control_id'] ) : 0;
        $existing_variant = isset( $t['variant_id'] ) ? absint( $t['variant_id'] ) : 0;
        $existing_kind    = isset( $t['kind'] ) ? sanitize_key( (string) $t['kind'] ) : '';

        // Existing direct guardrail: same A/B IDs cannot be reused.
        $uses_control = $existing_control === $control_id || ( $variant_id && $existing_control === $variant_id );
        $uses_variant = $existing_variant === $control_id || ( $variant_id && $existing_variant === $variant_id );

        if ( $uses_control || $uses_variant ) {
            $conflicts[] = $existing_id;
            continue;
        }

        /*
         * Direction 1:
         * New test is a reusable-section source page.
         * Block it if any existing running page/product/post test embeds that source.
         */
        if ( $new_kind === 'reusable_section' && $control_id > 0 && $existing_kind !== 'reusable_section' ) {
            $existing_hosts = array_values(
                array_filter(
                    array_unique(
                        [
                            $existing_control,
                            $existing_variant,
                        ]
                    )
                )
            );

            foreach ( $existing_hosts as $host_id ) {
                if ( abtestkit_pt_post_embeds_reusable_source( (int) $host_id, $control_id ) ) {
                    $conflicts[] = $existing_id;
                    continue 2;
                }
            }
        }

        /*
         * Direction 2:
         * New test is a normal page/post/product.
         * Block it if it embeds a source page already under a running reusable-section test.
         */
        if ( $new_kind !== 'reusable_section' && $existing_kind === 'reusable_section' ) {
            $existing_source = isset( $t['source_page_id'] )
                ? absint( $t['source_page_id'] )
                : $existing_control;

            if ( $existing_source <= 0 ) {
                continue;
            }

            $new_hosts = array_values(
                array_filter(
                    array_unique(
                        [
                            $control_id,
                            $variant_id,
                        ]
                    )
                )
            );

            foreach ( $new_hosts as $host_id ) {
                if ( abtestkit_pt_post_embeds_reusable_source( (int) $host_id, $existing_source ) ) {
                    $conflicts[] = $existing_id;
                    continue 2;
                }
            }
        }
    }

    return array_values( array_filter( array_unique( $conflicts ) ) );
}

/**
 * Integration layer: regenerate CSS/assets for common page builders
 * when we duplicate a page (Version B).
 *
 * @param int $new_id    The ID of the duplicated page.
 * @param int $source_id The original/control page ID.
 */
function abtestkit_regenerate_builder_assets( int $new_id, int $source_id = 0 ) : void {
    $new_id    = (int) $new_id;
    $source_id = (int) $source_id;

    if ( $new_id <= 0 ) {
        return;
    }

    // ─────────────────────────────────────────────────────
    // Elementor
    // ─────────────────────────────────────────────────────
    if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
        try {
            // Clear any old CSS and build fresh CSS for this post
            if ( isset( \Elementor\Plugin::$instance->files_manager )
                && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' )
            ) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }

            // Ask Elementor to clear generated CSS for the post (external hook).
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action( 'elementor/css-file/post/clear', $new_id );

            // Newer Elementor has a per-post CSS object.
            if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
                $css = new \Elementor\Core\Files\CSS\Post( $new_id );
                if ( method_exists( $css, 'update' ) ) {
                    $css->update();
                }
            }
        } catch ( \Throwable $e ) {
            // Fail silently – never break the site if Elementor changes APIs.
        }
    }

    // ─────────────────────────────────────────────────────
    // Breakdance
    // ─────────────────────────────────────────────────────
    if ( function_exists( 'breakdance' ) ) {
        try {
            // Official per-post CSS regeneration.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action( 'breakdance_generate_css_for_post', $new_id );

            // Optional: full CSS cache clear (heavier; keep only if needed).
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            // do_action( 'breakdance_clear_css_cache' );
        } catch ( \Throwable $e ) {
        }
    }

    // ─────────────────────────────────────────────────────
    // Beaver Builder
    // ─────────────────────────────────────────────────────
    if ( class_exists( 'FLBuilderModel' ) ) {
        try {
            if ( method_exists( 'FLBuilderModel', 'delete_asset_cache_for_post' ) ) {
                \FLBuilderModel::delete_asset_cache_for_post( $new_id );
            }
            if ( function_exists( 'fl_builder_flush_caches' ) ) {
                fl_builder_flush_caches();
            }
        } catch ( \Throwable $e ) {
        }
    }

    // ─────────────────────────────────────────────────────
    // Divi
    // ─────────────────────────────────────────────────────
    if ( function_exists( 'et_core_cache_flush' ) ) {
        try {
            et_core_cache_flush();
        } catch ( \Throwable $e ) {
        }
    }

    // ─────────────────────────────────────────────────────
    // Bricks
    // ─────────────────────────────────────────────────────
    if ( defined( 'BRICKS_VERSION' ) ) {
        try {
            // External hook – you or Bricks can hook into this.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action( 'bricks/abtestkit/regenerate_css', $new_id, $source_id );
        } catch ( \Throwable $e ) {
        }
    }

    // ─────────────────────────────────────────────────────
    // Oxygen
    // ─────────────────────────────────────────────────────
    if ( function_exists( 'ct_cssbuffer_update' ) ) {
        try {
            ct_cssbuffer_update();
        } catch ( \Throwable $e ) {
        }
    }

    // ─────────────────────────────────────────────────────
    // WPBakery / Visual Composer: shortcode-based
    // ─────────────────────────────────────────────────────
    // No direct API needed, but expose a hook for add-ons.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    do_action( 'abtestkit/after_duplicate/wpbakery', $new_id, $source_id );

    // ─────────────────────────────────────────────────────
    // Generic caches (object cache / page caches)
    // ─────────────────────────────────────────────────────
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
}

/**
 * Keep a shadow product's canonical WooCommerce type identical to Version A.
 *
 * WooCommerce resolves a product class from the single `product_type` taxonomy
 * term. A broad taxonomy clone is normally enough, but explicitly replacing and
 * verifying this term prevents a newly inserted shadow from being read as the
 * default simple type by either the classic or block-based product editor.
 */
function abtestkit_pt_sync_shadow_product_type( int $control_product_id, int $shadow_product_id ) : string {
    if (
        $control_product_id <= 0
        || $shadow_product_id <= 0
        || get_post_type( $control_product_id ) !== 'product'
        || get_post_type( $shadow_product_id ) !== 'product'
        || ! taxonomy_exists( 'product_type' )
    ) {
        return '';
    }

    $source_type = '';

    if ( function_exists( 'wc_get_product' ) ) {
        $control_product = wc_get_product( $control_product_id );

        if ( $control_product instanceof WC_Product ) {
            $source_type = sanitize_title( (string) $control_product->get_type() );
        }
    }

    if ( $source_type === '' ) {
        $source_types = wp_get_object_terms(
            $control_product_id,
            'product_type',
            [ 'fields' => 'slugs' ]
        );

        if ( ! is_wp_error( $source_types ) && ! empty( $source_types ) ) {
            $source_type = sanitize_title( (string) reset( $source_types ) );
        }
    }

    if ( $source_type === '' || ! term_exists( $source_type, 'product_type' ) ) {
        return '';
    }

    $shadow_types = wp_get_object_terms(
        $shadow_product_id,
        'product_type',
        [ 'fields' => 'slugs' ]
    );

    $shadow_types = is_wp_error( $shadow_types )
        ? []
        : array_values( array_unique( array_map( 'sanitize_title', (array) $shadow_types ) ) );

    if ( count( $shadow_types ) !== 1 || $shadow_types[0] !== $source_type ) {
        $updated = wp_set_object_terms( $shadow_product_id, $source_type, 'product_type', false );

        if ( is_wp_error( $updated ) ) {
            return '';
        }
    }

    clean_object_term_cache( $shadow_product_id, 'product' );
    clean_post_cache( $shadow_product_id );

    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $shadow_product_id );
    }

    $verified_types = wp_get_object_terms(
        $shadow_product_id,
        'product_type',
        [ 'fields' => 'slugs' ]
    );

    if (
        is_wp_error( $verified_types )
        || count( $verified_types ) !== 1
        || sanitize_title( (string) reset( $verified_types ) ) !== $source_type
    ) {
        return '';
    }

    return $source_type;
}

/**
 * Repair an already-created shadow before WooCommerce chooses its editor form.
 *
 * WooCommerce's beta editor redirects on `current_screen` at priority 30, so
 * this runs earlier and leaves editor selection to WooCommerce itself.
 */
function abtestkit_pt_sync_shadow_product_type_before_edit( $screen ) : void {
    if (
        ! is_object( $screen )
        || (string) ( $screen->base ?? '' ) !== 'post'
        || (string) ( $screen->post_type ?? '' ) !== 'product'
    ) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing values select an authorised product repair.
    $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing values select an authorised product repair.
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

    if (
        $post_id <= 0
        || $action !== 'edit'
        || ! current_user_can( 'edit_post', $post_id )
        || ! function_exists( 'abtestkit_is_shadow_product' )
        || ! abtestkit_is_shadow_product( $post_id )
    ) {
        return;
    }

    $control_product_id = (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );
    abtestkit_pt_sync_shadow_product_type( $control_product_id, $post_id );
}
add_action( 'current_screen', 'abtestkit_pt_sync_shadow_product_type_before_edit', 5 );

/** Duplicate a page or product (content + meta + taxonomies). */
function abtestkit_duplicate_post_deep( int $post_id ) : int {
    $orig = get_post( $post_id );
    if ( ! $orig ) {
        return 0;
    }

    // Only allow types we deliberately support in the wizard.
    $allowed_types = [ 'page', 'post', 'product' ];
    if ( ! in_array( $orig->post_type, $allowed_types, true ) ) {
        return 0;
    }

    $is_product = ( $orig->post_type === 'product' );

    $new_postarr = [
        // Keep the original title (we’ll show “Shadow / Variant B” via admin UI instead).
        'post_title'   => $orig->post_title,
        'post_content' => $orig->post_content,

        // Always create a draft clone from the wizard so it's not publicly accessible.
        'post_status'  => 'draft',

        'post_type'    => $orig->post_type,
        'post_author'  => get_current_user_id() ?: $orig->post_author,
        'post_parent'  => $orig->post_parent,
        'menu_order'   => $orig->menu_order,
        'post_excerpt' => $orig->post_excerpt,

        // Keep a distinct slug
        'post_name'    => sanitize_title( $orig->post_name . '-variant-b' ),
    ];


    $new_id = wp_insert_post( wp_slash( $new_postarr ), true );
    if ( is_wp_error( $new_id ) || ! $new_id ) {
        return 0;
    }

    // Copy taxonomies (works for products and pages)
    $taxes = get_object_taxonomies( $orig->post_type );
    foreach ( $taxes as $tx ) {
        $terms = wp_get_object_terms( $post_id, $tx, [ 'fields' => 'ids' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            wp_set_object_terms( $new_id, $terms, $tx, false );
        }
    }

    if ( $is_product ) {
        abtestkit_pt_sync_shadow_product_type( (int) $post_id, (int) $new_id );
    }

    // Copy meta (FAST + low memory): do it in SQL (do not load all meta into PHP).
    global $wpdb;

    // Exclude a small, fixed set of meta keys from the fast SQL clone.
    // Keeping placeholders fixed avoids PHPCS false-positives around dynamic placeholder lists.
    $exclude = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        $is_product ? '_sku' : '__abtestkit_noop__',
    ];

    // Direct INSERT is intentional here (fast meta clone). Caching is not applicable for writes.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "
            INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
            SELECT %d, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id = %d
              AND meta_key NOT IN ( %s, %s, %s, %s )
            ",
            (int) $new_id,
            (int) $post_id,
            $exclude[0],
            $exclude[1],
            $exclude[2],
            $exclude[3]
        )
    );

    // ───────────────────────────────────────────────────────────
    // WooCommerce-specific hygiene for Version B products
    // ───────────────────────────────────────────────────────────
    if ( $orig->post_type === 'product' ) {

        // Mark this as a shadow variant product, linked to its real (A) product.
        update_post_meta( $new_id, '_abtestkit_shadow', 1 );
        update_post_meta( $new_id, '_abtestkit_shadow_of', (int) $post_id );

        // Hide from catalog/search so it never appears in loops.
        if ( function_exists( 'wc_get_product' ) ) {
            $orig_product = wc_get_product( $post_id );

            if (
                $orig_product instanceof WC_Product
                && function_exists( 'abtestkit_pt_product_supports_shadow_children' )
                && abtestkit_pt_product_supports_shadow_children( $orig_product )
                && function_exists( 'abtestkit_pt_duplicate_shadow_variations' )
            ) {
                abtestkit_pt_duplicate_shadow_variations( (int) $post_id, (int) $new_id );

                // Remove stale variable-product caches/meta copied from A before we save/sync B.
                foreach ( [
                    '_children',
                    '_visible_children',
                    '_price',
                    '_min_variation_price',
                    '_max_variation_price',
                    '_min_variation_regular_price',
                    '_max_variation_regular_price',
                    '_min_variation_sale_price',
                    '_max_variation_sale_price',
                    '_min_price_variation_id',
                    '_max_price_variation_id',
                ] as $stale_key ) {
                    delete_post_meta( $new_id, $stale_key );
                }

                if ( class_exists( 'WC_Product_Variable' ) && method_exists( 'WC_Product_Variable', 'sync' ) ) {
                    WC_Product_Variable::sync( $new_id );
                }
            }

            $product_b = wc_get_product( $new_id );
            if ( $product_b instanceof WC_Product ) {
                $product_b->set_catalog_visibility( 'hidden' ); // not in catalog/search
                $product_b->save();
            }

            if ( function_exists( 'abtestkit_pt_sync_shadow_stock_truth' ) ) {
                abtestkit_pt_sync_shadow_stock_truth( (int) $new_id );
            }

            clean_post_cache( $new_id );

            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients( $new_id );
                wc_delete_product_transients( $post_id );
            }
        }

        // Prevent SKU collisions.
        delete_post_meta( $new_id, '_sku' );

        // Optional: make sure the shadow cannot ever be purchased if somehow linked.
        // (We also enforce this with filters later.)
        update_post_meta( $new_id, '_sold_individually', 'yes' );
    }

    // ───────────────────────────────────────────────────────────
    // hygiene: mark ALL duplicates as "shadow" variants
    // This is used for SEO (noindex), canonical, and sitemap exclusion,
    // even if the test is paused or deleted later.
    // ───────────────────────────────────────────────────────────
    update_post_meta( $new_id, '_abtestkit_shadow', 1 );
    update_post_meta( $new_id, '_abtestkit_shadow_of', (int) $post_id );
    // ───────────────────────────────────────────────────────────
    // Builder compatibility: regenerate CSS/assets on the clone
    // ───────────────────────────────────────────────────────────
    
    abtestkit_regenerate_builder_assets( $new_id, $post_id );

    return $new_id;
}

/**
 * Shadow / Variant products guardrails
 * - Never allow a shadow product to be published
 * - Hide shadow products from the normal Products list
 * - Ensure shadow product URLs are noindex if accessed
 * - Show a strong admin warning banner on edit screens
 */
/**
 * Clear cached admin shadow counts (used for list count tabs).
 *
 * @param string|null $type post type to clear (product/post/page). If null, clears all.
 */
function abtestkit_clear_shadow_counts_cache( $type = null ) {
    $cache_group = 'abtestkit';
    $blog_id     = (int) get_current_blog_id();

    if ( $type ) {
        wp_cache_delete( 'shadow_counts_by_status_' . $type . '_' . $blog_id, $cache_group );
        return;
    }

    foreach ( [ 'product', 'post', 'page' ] as $pt ) {
        wp_cache_delete( 'shadow_counts_by_status_' . $pt . '_' . $blog_id, $cache_group );
    }
}

function abtestkit_is_shadow_product( $post_id ) {
    return (int) get_post_meta( (int) $post_id, '_abtestkit_shadow', true ) === 1;
}

/**
 * Keep non-catalog products out of Woodmart's adjacent-product query.
 *
 * Woodmart walks past invisible products after get_adjacent_post() returns
 * them. Its date-only cursor can select the same invisible product forever,
 * which eventually exhausts PHP's execution time. Excluding products that
 * WooCommerce cannot show in the catalog also keeps abtestkit shadow products
 * out of that loop without changing their stored visibility.
 *
 * @param string $where Adjacent-post query WHERE clause.
 * @return string
 */
function abtestkit_woodmart_adjacent_product_where( $where ) {
    global $post, $wpdb;

    if (
        ! class_exists( '\\XTS\\Modules\\WC_Adjacent_Products' )
        || ! ( $post instanceof WP_Post )
        || $post->post_type !== 'product'
    ) {
        return $where;
    }

    $where .= " AND NOT EXISTS (
        SELECT 1
        FROM {$wpdb->term_relationships} AS abtk_tr
        INNER JOIN {$wpdb->term_taxonomy} AS abtk_tt
            ON abtk_tt.term_taxonomy_id = abtk_tr.term_taxonomy_id
        INNER JOIN {$wpdb->terms} AS abtk_t
            ON abtk_t.term_id = abtk_tt.term_id
        WHERE abtk_tr.object_id = p.ID
            AND abtk_tt.taxonomy = 'product_visibility'
            AND abtk_t.slug = 'exclude-from-catalog'
    )";

    $where .= " AND NOT EXISTS (
        SELECT 1
        FROM {$wpdb->postmeta} AS abtk_pm
        WHERE abtk_pm.post_id = p.ID
            AND abtk_pm.meta_key = '_abtestkit_shadow'
            AND abtk_pm.meta_value = '1'
    )";

    return $where;
}
add_filter( 'get_previous_post_where', 'abtestkit_woodmart_adjacent_product_where', 20 );
add_filter( 'get_next_post_where', 'abtestkit_woodmart_adjacent_product_where', 20 );

function abtestkit_pt_get_product_test_control_id( $product ) : int {
    if ( $product instanceof WC_Product_Variation ) {
        $parent_id = (int) $product->get_parent_id();
        return $parent_id > 0 ? $parent_id : (int) $product->get_id();
    }

    if ( $product instanceof WC_Product ) {
        return (int) $product->get_id();
    }

    $product_id = absint( $product );
    if ( $product_id <= 0 ) {
        return 0;
    }

    if ( get_post_type( $product_id ) === 'product_variation' ) {
        $parent_id = (int) wp_get_post_parent_id( $product_id );
        return $parent_id > 0 ? $parent_id : $product_id;
    }

    return $product_id;
}

function abtestkit_pt_variation_signature( $variation ) : string {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return '';
    }

    if ( $variation instanceof WC_Product_Variation ) {
        $wc_variation = $variation;
    } else {
        $wc_variation = wc_get_product( $variation );
        if ( ! ( $wc_variation instanceof WC_Product_Variation ) ) {
            return '';
        }
    }

    $attrs = $wc_variation->get_attributes();
    if ( ! is_array( $attrs ) || empty( $attrs ) ) {
        return '';
    }

    $normalized = [];
    foreach ( $attrs as $key => $value ) {
        $normalized[ sanitize_title( (string) $key ) ] = sanitize_title( (string) $value );
    }

    ksort( $normalized );

    $parts = [];
    foreach ( $normalized as $key => $value ) {
        $parts[] = $key . '=' . $value;
    }

    return implode( '|', $parts );
}

function abtestkit_pt_get_shadow_variation_ids_for_product( int $shadow_product_id ) : array {
    $shadow_product_id = (int) $shadow_product_id;
    if ( $shadow_product_id <= 0 ) {
        return [];
    }

    static $cache = [];

    if ( isset( $cache[ $shadow_product_id ] ) ) {
        return $cache[ $shadow_product_id ];
    }

    $ids = get_posts( [
        'post_parent'    => $shadow_product_id,
        'post_type'      => 'product_variation',
        'post_status'    => [ 'publish', 'private', 'draft', 'pending', 'future' ],
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order ID',
        'order'          => 'ASC',
    ] );

    $cache[ $shadow_product_id ] = array_values( array_map( 'absint', (array) $ids ) );

    return $cache[ $shadow_product_id ];
}

function abtestkit_pt_product_supports_shadow_children( $product ) : bool {
    if ( ! ( $product instanceof WC_Product ) ) {
        return false;
    }

    if ( method_exists( $product, 'is_type' ) ) {
        if ( $product->is_type( [ 'variable', 'variable-subscription' ] ) ) {
            return true;
        }
    }

    if ( $product instanceof WC_Product_Variable ) {
        return true;
    }

    if ( ! method_exists( $product, 'get_children' ) ) {
        return false;
    }

    $children = $product->get_children();

    return is_array( $children ) && ! empty( $children );
}

function abtestkit_pt_duplicate_shadow_variations( int $control_product_id, int $shadow_product_id ) : void {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    $control_product_id = (int) $control_product_id;
    $shadow_product_id  = (int) $shadow_product_id;

    if ( $control_product_id <= 0 || $shadow_product_id <= 0 ) {
        return;
    }

    $control_product = wc_get_product( $control_product_id );
    if (
        ! ( $control_product instanceof WC_Product )
        || ! function_exists( 'abtestkit_pt_product_supports_shadow_children' )
        || ! abtestkit_pt_product_supports_shadow_children( $control_product )
        || ! method_exists( $control_product, 'get_children' )
    ) {
        return;
    }

    $control_variation_ids = $control_product->get_children();
    if ( empty( $control_variation_ids ) || ! is_array( $control_variation_ids ) ) {
        return;
    }

    global $wpdb;

    foreach ( $control_variation_ids as $control_variation_id ) {
        $control_variation_id = absint( $control_variation_id );
        if ( $control_variation_id <= 0 ) {
            continue;
        }

        $variation_post = get_post( $control_variation_id );
        if ( ! $variation_post || $variation_post->post_type !== 'product_variation' ) {
            continue;
        }

        $new_variation_id = wp_insert_post( wp_slash( [
            'post_title'   => $variation_post->post_title,
            'post_content' => $variation_post->post_content,
            'post_excerpt' => $variation_post->post_excerpt,
            'post_status'  => $variation_post->post_status ?: 'publish',
            'post_type'    => 'product_variation',
            'post_author'  => get_current_user_id() ?: $variation_post->post_author,
            'post_parent'  => $shadow_product_id,
            'menu_order'   => (int) $variation_post->menu_order,
            'post_name'    => sanitize_title( $variation_post->post_name . '-variant-b-' . $shadow_product_id ),
        ] ), true );

        if ( is_wp_error( $new_variation_id ) || ! $new_variation_id ) {
            continue;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "
                INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                SELECT %d, meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id = %d
                  AND meta_key NOT IN ( %s, %s, %s, %s )
                ",
                (int) $new_variation_id,
                (int) $control_variation_id,
                '_edit_lock',
                '_edit_last',
                '_wp_old_slug',
                '_sku'
            )
        );

        update_post_meta( $new_variation_id, '_abtestkit_shadow', 1 );
        update_post_meta( $new_variation_id, '_abtestkit_shadow_of', (int) $control_variation_id );
        delete_post_meta( $new_variation_id, '_sku' );
    }
}

if ( ! function_exists( 'abtestkit_pt_copy_meta_key_if_exists' ) ) {
    function abtestkit_pt_copy_meta_key_if_exists( int $from_id, int $to_id, string $meta_key ) : void {
        if ( $from_id <= 0 || $to_id <= 0 || $meta_key === '' ) {
            return;
        }

        if ( metadata_exists( 'post', $from_id, $meta_key ) ) {
            update_post_meta( $to_id, $meta_key, get_post_meta( $from_id, $meta_key, true ) );
        } else {
            delete_post_meta( $to_id, $meta_key );
        }
    }
}

if ( ! function_exists( 'abtestkit_pt_sync_shadow_stock_truth' ) ) {
    function abtestkit_pt_sync_shadow_stock_truth( int $shadow_product_id ) : void {
        if ( $shadow_product_id <= 0 ) {
            return;
        }

        if ( ! function_exists( 'abtestkit_is_shadow_product' ) || ! abtestkit_is_shadow_product( $shadow_product_id ) ) {
            return;
        }

        $control_product_id = (int) get_post_meta( $shadow_product_id, '_abtestkit_shadow_of', true );
        if ( $control_product_id <= 0 ) {
            return;
        }

        $stock_meta_keys = [
            '_manage_stock',
            '_stock',
            '_stock_status',
            '_backorders',
            '_backorders_allowed',
            '_backordered',
        ];

        foreach ( $stock_meta_keys as $meta_key ) {
            abtestkit_pt_copy_meta_key_if_exists( $control_product_id, $shadow_product_id, $meta_key );
        }

        $shadow_variation_ids = function_exists( 'abtestkit_pt_get_shadow_variation_ids_for_product' )
            ? abtestkit_pt_get_shadow_variation_ids_for_product( $shadow_product_id )
            : [];

        foreach ( $shadow_variation_ids as $shadow_variation_id ) {
            $shadow_variation_id = (int) $shadow_variation_id;
            if ( $shadow_variation_id <= 0 || get_post_type( $shadow_variation_id ) !== 'product_variation' ) {
                continue;
            }

            $control_variation_id = (int) get_post_meta( $shadow_variation_id, '_abtestkit_shadow_of', true );
            if ( $control_variation_id <= 0 ) {
                continue;
            }

            foreach ( $stock_meta_keys as $meta_key ) {
                abtestkit_pt_copy_meta_key_if_exists( $control_variation_id, $shadow_variation_id, $meta_key );
            }

            clean_post_cache( $shadow_variation_id );
        }

        clean_post_cache( $shadow_product_id );
        clean_object_term_cache( $shadow_product_id, 'product' );

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $shadow_product_id );
            wc_delete_product_transients( $control_product_id );
        }
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_posted_variation_value' ) ) {
    function abtestkit_pt_shadow_posted_variation_value( string $field, int $index, $default = null ) {
        if (
            ! isset( $_POST['woocommerce_meta_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ),
                'woocommerce_save_data'
            )
        ) {
            return $default;
        }

        $field = sanitize_key( $field );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce product save nonce is verified above.
        if ( '' === $field || ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) || ! array_key_exists( $index, $_POST[ $field ] ) ) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce nonce verified above; rejected if array and sanitized immediately below.
        $raw = wp_unslash( $_POST[ $field ][ $index ] );

        if ( is_array( $raw ) ) {
            return $default;
        }

        return sanitize_text_field( (string) $raw );
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_save_subscription_variation_fields' ) ) {
    function abtestkit_pt_shadow_save_subscription_variation_fields( int $variation_id, int $i ) : void {
        if ( $variation_id <= 0 || get_post_type( $variation_id ) !== 'product_variation' ) {
            return;
        }

        if ( ! function_exists( 'abtestkit_is_shadow_product' ) || ! abtestkit_is_shadow_product( $variation_id ) ) {
            return;
        }

        $parent_id = (int) wp_get_post_parent_id( $variation_id );
        if ( $parent_id <= 0 || ! abtestkit_is_shadow_product( $parent_id ) ) {
            return;
        }

        $subscription_field_map = [
            'variable_subscription_sign_up_fee'     => '_subscription_sign_up_fee',
            'variable_subscription_trial_length'    => '_subscription_trial_length',
            'variable_subscription_trial_period'    => '_subscription_trial_period',
            'variable_subscription_period_interval' => '_subscription_period_interval',
            'variable_subscription_period'          => '_subscription_period',
            'variable_subscription_length'          => '_subscription_length',
        ];

        foreach ( $subscription_field_map as $posted_key => $meta_key ) {
            $posted_value = abtestkit_pt_shadow_posted_variation_value( $posted_key, $i, null );
            if ( null === $posted_value ) {
                continue;
            }

            switch ( $meta_key ) {
                case '_subscription_sign_up_fee':
                    $posted_value = ( '' === $posted_value ) ? '' : wc_format_decimal( $posted_value );
                    break;

                case '_subscription_trial_period':
                case '_subscription_period':
                    $posted_value = sanitize_key( $posted_value );
                    break;

                default:
                    $posted_value = (string) absint( $posted_value );
                    break;
            }

            update_post_meta( $variation_id, $meta_key, $posted_value );
        }

        $regular_price = abtestkit_pt_shadow_posted_variation_value( 'variable_regular_price', $i, null );
        $sale_price    = abtestkit_pt_shadow_posted_variation_value( 'variable_sale_price', $i, null );

        if ( null !== $regular_price ) {
            if ( '' === $regular_price ) {
                delete_post_meta( $variation_id, '_regular_price' );
                delete_post_meta( $variation_id, '_subscription_price' );
            } else {
                $regular_price = wc_format_decimal( $regular_price );
                update_post_meta( $variation_id, '_regular_price', $regular_price );
                update_post_meta( $variation_id, '_subscription_price', $regular_price );
            }
        }

        if ( null !== $sale_price ) {
            if ( '' === $sale_price ) {
                delete_post_meta( $variation_id, '_sale_price' );
            } else {
                $sale_price = wc_format_decimal( $sale_price );
                update_post_meta( $variation_id, '_sale_price', $sale_price );
            }
        }

        $active_price = '';

        if ( null !== $sale_price && '' !== $sale_price ) {
            $active_price = $sale_price;
        } elseif ( null !== $regular_price && '' !== $regular_price ) {
            $active_price = $regular_price;
        }

        if ( '' !== $active_price ) {
            update_post_meta( $variation_id, '_price', wc_format_decimal( $active_price ) );
        }

        update_post_meta( $parent_id, '_abtestkit_rev', (string) time() );

        if ( function_exists( 'abtestkit_pt_sync_shadow_stock_truth' ) ) {
            abtestkit_pt_sync_shadow_stock_truth( $parent_id );
        }

        clean_post_cache( $variation_id );
        clean_post_cache( $parent_id );

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $parent_id );
        }
    }
}

add_action( 'woocommerce_save_product_variation', 'abtestkit_pt_shadow_save_subscription_variation_fields', 999, 2 );

add_action( 'save_post_product', function( $post_id, $post, $update ) {
    $post_id = (int) $post_id;

    if ( $post_id <= 0 ) {
        return;
    }

    if ( function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $post_id ) && function_exists( 'abtestkit_pt_sync_shadow_stock_truth' ) ) {
        abtestkit_pt_sync_shadow_stock_truth( $post_id );
    }
}, 30, 3 );

function abtestkit_pt_get_matching_shadow_variation_id( int $control_variation_id, int $shadow_product_id ) : int {
    $control_variation_id = (int) $control_variation_id;
    $shadow_product_id    = (int) $shadow_product_id;

    if ( $control_variation_id <= 0 || $shadow_product_id <= 0 ) {
        return 0;
    }

    static $cache = [];
    $cache_key = $control_variation_id . ':' . $shadow_product_id;

    if ( isset( $cache[ $cache_key ] ) ) {
        return (int) $cache[ $cache_key ];
    }

    $control_signature = abtestkit_pt_variation_signature( $control_variation_id );
    $shadow_ids        = abtestkit_pt_get_shadow_variation_ids_for_product( $shadow_product_id );

    if ( $control_signature !== '' ) {
        foreach ( $shadow_ids as $shadow_variation_id ) {
            if ( abtestkit_pt_variation_signature( (int) $shadow_variation_id ) === $control_signature ) {
                $cache[ $cache_key ] = (int) $shadow_variation_id;
                return (int) $cache[ $cache_key ];
            }
        }
    }

    $control_post = get_post( $control_variation_id );
    $control_menu_order = $control_post ? (int) $control_post->menu_order : 0;

    foreach ( $shadow_ids as $shadow_variation_id ) {
        $shadow_post = get_post( $shadow_variation_id );
        if ( $shadow_post && (int) $shadow_post->menu_order === $control_menu_order ) {
            $cache[ $cache_key ] = (int) $shadow_variation_id;
            return (int) $cache[ $cache_key ];
        }
    }

    $cache[ $cache_key ] = 0;
    return 0;
}

function abtestkit_pt_apply_shadow_variation_to_control( int $control_variation_id, int $shadow_variation_id ) : void {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    $control_variation_id = (int) $control_variation_id;
    $shadow_variation_id  = (int) $shadow_variation_id;

    if ( $control_variation_id <= 0 || $shadow_variation_id <= 0 ) {
        return;
    }

    $control_post = get_post( $control_variation_id );
    $shadow_post  = get_post( $shadow_variation_id );

    if (
        ! $control_post
        || ! $shadow_post
        || $control_post->post_type !== 'product_variation'
        || $shadow_post->post_type !== 'product_variation'
    ) {
        return;
    }

    $shadow_variation = wc_get_product( $shadow_variation_id );

    /*
     * 1) Copy core post fields from shadow child -> live child.
     */
    wp_update_post(
        [
            'ID'           => $control_variation_id,
            'post_title'   => (string) $shadow_post->post_title,
            'post_content' => (string) $shadow_post->post_content,
            'post_excerpt' => (string) $shadow_post->post_excerpt,
            'menu_order'   => (int) $shadow_post->menu_order,
        ]
    );

    /*
     * 2) Replace most child variation meta on A with B's child meta.
     * Preserve commerce identity / stock truth on the live variation.
     *
     * This also brings across Woo Subscriptions variation meta automatically,
     * because those recurring fields are stored as normal variation post meta.
     */
    $preserve_meta_keys = [
        '_sku',
        '_stock',
        '_stock_status',
        '_manage_stock',
        '_backorders',
        '_backorders_allowed',
        '_backordered',
        'total_sales',
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',

        // Never copy shadow markers onto the real variation.
        '_abtestkit_shadow',
        '_abtestkit_shadow_of',
        '_abtestkit_variant_in_use',
        '_abtestkit_variant_of',
        '_abtestkit_variant_test_id',
    ];

    $existing_meta = get_post_meta( $control_variation_id );
    if ( is_array( $existing_meta ) ) {
        foreach ( array_keys( $existing_meta ) as $meta_key ) {
            $meta_key = (string) $meta_key;

            if ( in_array( $meta_key, $preserve_meta_keys, true ) ) {
                continue;
            }

            delete_post_meta( $control_variation_id, $meta_key );
        }
    }

    $shadow_meta = get_post_meta( $shadow_variation_id );
    if ( is_array( $shadow_meta ) ) {
        foreach ( $shadow_meta as $meta_key => $meta_values ) {
            $meta_key = (string) $meta_key;

            if ( $meta_key === '' || in_array( $meta_key, $preserve_meta_keys, true ) ) {
                continue;
            }

            foreach ( (array) $meta_values as $meta_value ) {
                add_post_meta( $control_variation_id, $meta_key, $meta_value );
            }
        }
    }

    /*
     * 3) Make sure key variation price / image fields are explicitly aligned.
     */
    if ( $shadow_variation instanceof WC_Product ) {
        $regular_price = $shadow_variation->get_regular_price( 'edit' );
        $sale_price    = $shadow_variation->get_sale_price( 'edit' );
        $active_price  = $shadow_variation->get_price( 'edit' );
        $image_id      = (int) $shadow_variation->get_image_id( 'edit' );

        if ( $regular_price !== '' && $regular_price !== null ) {
            update_post_meta( $control_variation_id, '_regular_price', wc_format_decimal( $regular_price ) );
        }

        if ( $sale_price !== '' && $sale_price !== null ) {
            update_post_meta( $control_variation_id, '_sale_price', wc_format_decimal( $sale_price ) );
        } else {
            delete_post_meta( $control_variation_id, '_sale_price' );
        }

        if ( $active_price !== '' && $active_price !== null ) {
            update_post_meta( $control_variation_id, '_price', wc_format_decimal( $active_price ) );
        }

        if ( $image_id > 0 ) {
            update_post_meta( $control_variation_id, '_thumbnail_id', $image_id );
        } else {
            delete_post_meta( $control_variation_id, '_thumbnail_id' );
        }
    }

    clean_post_cache( $control_variation_id );
    wp_cache_delete( $control_variation_id, 'posts' );

    $parent_id = (int) wp_get_post_parent_id( $control_variation_id );
    if ( $parent_id > 0 ) {
        clean_post_cache( $parent_id );
        clean_object_term_cache( $parent_id, 'product' );

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $parent_id );
        }
    }
}

function abtestkit_pt_apply_shadow_variations_to_product( int $control_product_id, int $shadow_product_id ) : void {
    if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'abtestkit_pt_get_matching_shadow_variation_id' ) ) {
        return;
    }

    $control_product_id = (int) $control_product_id;
    $shadow_product_id  = (int) $shadow_product_id;

    if ( $control_product_id <= 0 || $shadow_product_id <= 0 ) {
        return;
    }

    $control_product = wc_get_product( $control_product_id );
    $shadow_product  = wc_get_product( $shadow_product_id );

    if ( ! ( $control_product instanceof WC_Product ) || ! ( $shadow_product instanceof WC_Product ) ) {
        return;
    }

    if ( ! method_exists( $control_product, 'get_children' ) || ! method_exists( $shadow_product, 'get_children' ) ) {
        return;
    }

    $control_variation_ids = array_values(
        array_filter(
            array_map( 'absint', (array) $control_product->get_children() )
        )
    );

    if ( empty( $control_variation_ids ) ) {
        return;
    }

    foreach ( $control_variation_ids as $control_variation_id ) {
        $shadow_variation_id = abtestkit_pt_get_matching_shadow_variation_id(
            (int) $control_variation_id,
            (int) $shadow_product_id
        );

        if ( $shadow_variation_id <= 0 ) {
            continue;
        }

        abtestkit_pt_apply_shadow_variation_to_control(
            (int) $control_variation_id,
            (int) $shadow_variation_id
        );
    }

    clean_post_cache( $control_product_id );
    clean_object_term_cache( $control_product_id, 'product' );
    wp_cache_delete( $control_product_id, 'posts' );

    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $control_product_id );
        wc_delete_product_transients( $shadow_product_id );
    }
}

function abtestkit_pt_get_shadow_product_for_runtime( $product, $test ) {
    $shadow = function_exists( 'abtestkit_pt_get_shadow_product_for_test' )
        ? abtestkit_pt_get_shadow_product_for_test( $test )
        : null;

    if ( ! ( $shadow instanceof WC_Product ) ) {
        return null;
    }

    $variation_id = 0;

    // Variation object: map to matching shadow variation.
    if ( $product instanceof WC_Product_Variation ) {
        $variation_id = (int) $product->get_id();

    // Any non-variation WC_Product (simple, variable, etc): use the shadow product itself.
    } elseif ( $product instanceof WC_Product ) {
        return $shadow;

    // WP_Post support for any theme/plugin paths that pass posts around.
    } elseif ( $product instanceof WP_Post ) {
        $product_id = (int) $product->ID;

        if ( $product_id > 0 && get_post_type( $product_id ) === 'product_variation' ) {
            $variation_id = $product_id;
        } else {
            return $shadow;
        }

    // Raw numeric IDs are still supported.
    } elseif ( is_numeric( $product ) ) {
        $product_id = absint( $product );

        if ( $product_id > 0 && get_post_type( $product_id ) === 'product_variation' ) {
            $variation_id = $product_id;
        } else {
            return $shadow;
        }

    // Unknown input type: safest fallback is the parent shadow product.
    } else {
        return $shadow;
    }

    if ( $variation_id > 0 ) {
        $shadow_variation_id = abtestkit_pt_get_matching_shadow_variation_id( $variation_id, (int) $shadow->get_id() );

        if ( $shadow_variation_id > 0 && function_exists( 'wc_get_product' ) ) {
            $shadow_variation = wc_get_product( $shadow_variation_id );

            if ( $shadow_variation instanceof WC_Product ) {
                return $shadow_variation;
            }
        }
    }

    return $shadow;
}

function abtestkit_pt_get_shadow_product_for_cart_item( array $cart_item ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return null;
    }

    if ( ! empty( $cart_item['abtestkit_shadow_variation_id'] ) ) {
        $shadow_variation = wc_get_product( (int) $cart_item['abtestkit_shadow_variation_id'] );
        if ( $shadow_variation instanceof WC_Product ) {
            return $shadow_variation;
        }
    }

    if ( ! empty( $cart_item['abtestkit_shadow_product_id'] ) ) {
        $shadow_product = wc_get_product( (int) $cart_item['abtestkit_shadow_product_id'] );
        if ( $shadow_product instanceof WC_Product ) {
            return $shadow_product;
        }
    }

    return null;
}


/**
 * Universal SEO hygiene for ALL shadow variants (posts/pages/products):
 * - Always noindex/nofollow
 * - Canonical back to Version A (shadow_of)
 * This applies even if a test is paused, draft, or deleted.
 */
function abtestkit_is_shadow_variant( int $post_id ): bool {
    return (int) get_post_meta( $post_id, '_abtestkit_shadow', true ) === 1;
}

function abtestkit_shadow_block_notice_key( int $post_id, int $user_id = 0 ) : string {
    $post_id = (int) $post_id;
    $user_id = $user_id > 0 ? (int) $user_id : (int) get_current_user_id();

    return 'abtestkit_shadow_blocked_' . $post_id . '_' . $user_id;
}

function abtestkit_mark_shadow_publish_blocked( int $post_id ) : void {
    $post_id = (int) $post_id;
    $user_id = (int) get_current_user_id();

    if ( $post_id <= 0 || $user_id <= 0 ) {
        return;
    }

    set_transient( abtestkit_shadow_block_notice_key( $post_id, $user_id ), 1, 2 * MINUTE_IN_SECONDS );
}

function abtestkit_consume_shadow_publish_blocked( int $post_id ) : bool {
    $post_id = (int) $post_id;
    $user_id = (int) get_current_user_id();

    if ( $post_id <= 0 || $user_id <= 0 ) {
        return false;
    }

    $key   = abtestkit_shadow_block_notice_key( $post_id, $user_id );
    $found = (bool) get_transient( $key );

    if ( $found ) {
        delete_transient( $key );
    }

    return $found;
}

function abtestkit_peek_shadow_publish_blocked( int $post_id ) : bool {
    $post_id = (int) $post_id;
    $user_id = (int) get_current_user_id();

    if ( $post_id <= 0 || $user_id <= 0 ) {
        return false;
    }

    return (bool) get_transient( abtestkit_shadow_block_notice_key( $post_id, $user_id ) );
}

function abtestkit_is_seo_protected_variant( int $post_id ) : bool {
    return abtestkit_is_shadow_variant( $post_id ) || abtestkit_is_existing_variant_in_use( $post_id );
}

function abtestkit_shadow_of( int $post_id ): int {
    return (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );
}

// ──────────────────────────────────────────────────────────────
// Variant-in-use markers (for "existing page as Version B")
// ──────────────────────────────────────────────────────────────
function abtestkit_pt_mark_existing_variant_in_use( int $variant_id, int $control_id, string $test_id ) : void {
    if ( $variant_id <= 0 || $control_id <= 0 || $test_id === '' ) return;

    update_post_meta( $variant_id, '_abtestkit_variant_in_use', 1 );
    update_post_meta( $variant_id, '_abtestkit_variant_of', (int) $control_id );
    update_post_meta( $variant_id, '_abtestkit_variant_test_id', (string) $test_id );
}

function abtestkit_pt_unmark_existing_variant_in_use( int $variant_id, string $test_id = '' ) : void {
    if ( $variant_id <= 0 ) return;

    // If we know the test_id, only remove if it matches (safety).
    if ( $test_id !== '' ) {
        $cur = (string) get_post_meta( $variant_id, '_abtestkit_variant_test_id', true );
        if ( $cur !== '' && $cur !== $test_id ) return;
    }

    delete_post_meta( $variant_id, '_abtestkit_variant_in_use' );
    delete_post_meta( $variant_id, '_abtestkit_variant_of' );
    delete_post_meta( $variant_id, '_abtestkit_variant_test_id' );
}

function abtestkit_is_existing_variant_in_use( int $post_id ) : bool {
    return (int) get_post_meta( $post_id, '_abtestkit_variant_in_use', true ) === 1;
}

add_filter( 'wp_robots', function( $robots ) {
    if ( is_admin() ) {
        return $robots;
    }

    if ( ! is_singular() ) {
        return $robots;
    }

    $pid = (int) get_queried_object_id();
    if ( ! $pid ) {
        return $robots;
    }

    if ( abtestkit_is_seo_protected_variant( $pid ) ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
        $robots['nosnippet'] = true;
    }

    return $robots;
}, 20 );

add_filter( 'get_canonical_url', function( $canonical, $post ) {
    if ( is_admin() ) {
        return $canonical;
    }

    $pid = 0;
    if ( $post instanceof WP_Post ) {
        $pid = (int) $post->ID;
    } elseif ( is_numeric( $post ) ) {
        $pid = (int) $post;
    }

    if ( ! $pid ) {
        return $canonical;
    }

    if ( ! abtestkit_is_seo_protected_variant( $pid ) ) {
        return $canonical;
    }

    // Shadow duplicates store A in _abtestkit_shadow_of.
    // Existing pages used as B store A in _abtestkit_variant_of.
    $a_id = abtestkit_is_shadow_variant( $pid )
        ? abtestkit_shadow_of( $pid )
        : (int) get_post_meta( $pid, '_abtestkit_variant_of', true );
    if ( $a_id > 0 ) {
        $a_url = get_permalink( $a_id );
        if ( $a_url ) {
            return $a_url;
        }
    }

    return $canonical;
}, 20, 2 );

// Exclude shadow variants from WordPress core sitemaps.
add_filter( 'wp_sitemaps_posts_query_args', function( $args, $post_type ) {
    if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
        $args['meta_query'] = [];
    }

    $args['meta_query'][] = [
        'relation' => 'AND',
        [
            'key'     => '_abtestkit_shadow',
            'value'   => 1,
            'compare' => '!=',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => '_abtestkit_variant_in_use',
            'value'   => 1,
            'compare' => '!=',
            'type'    => 'NUMERIC',
        ],
    ];

    return $args;
}, 20, 2 );

/**
 * FRONTEND: Exclude Version B (shadow) variants from listings (blog archives, search, sliders, etc).
 * Does NOT affect singular views (direct access), so preview/edit flows still work.
 */
/**
 * FRONTEND: Exclude Version B variants from listings (blog archives, search, sliders, etc).
 * - Hides duplicates (shadow) via _abtestkit_shadow
 * - Hides existing Version B when SEO-safe is ON via _abtestkit_variant_in_use
 *
 * Applies to ALL frontend queries (not just main query) so Elementor widgets/sliders/custom WP_Query loops
 * also exclude Version B content.
 */
add_action( 'pre_get_posts', function( $q ) {
    if ( is_admin() ) return;
    if ( $q->is_singular() ) return;

    $pt = $q->get( 'post_type' );

    // Affect default blog queries, 'any', and any query that includes post/page (and optionally product).
    $affects = false;
    if ( empty( $pt ) || $pt === 'any' ) {
        $affects = true;
    } elseif ( is_string( $pt ) && in_array( $pt, [ 'post', 'page', 'product' ], true ) ) {
        $affects = true;
    } elseif ( is_array( $pt ) && array_intersect( $pt, [ 'post', 'page', 'product' ] ) ) {
        $affects = true;
    }

    if ( ! $affects ) return;

    $meta_query = (array) $q->get( 'meta_query' );

    // Remove any existing clauses we might add (defensive).
    $meta_query = array_values( array_filter( $meta_query, function( $clause ) {
        if ( ! is_array( $clause ) ) return true;
        if ( isset( $clause['key'] ) && in_array( $clause['key'], [ '_abtestkit_shadow', '_abtestkit_variant_in_use' ], true ) ) {
            return false;
        }
        return true;
    } ) );

    // Exclude duplicate B (shadow)
    $meta_query[] = [
        'key'     => '_abtestkit_shadow',
        'compare' => 'NOT EXISTS',
    ];

    // Exclude existing B when SEO-safe is ON
    $meta_query[] = [
        'key'     => '_abtestkit_variant_in_use',
        'compare' => 'NOT EXISTS',
    ];

    $q->set( 'meta_query', $meta_query );
}, 20 );

/**
 * If someone tries to publish a shadow variant (product/post/page), force it back to draft.
 * Also append a query flag so we can show a “blocked” notice after redirect.
 */
/**
 * If someone tries to publish a shadow PRODUCT, force it back to draft.
 * (We do NOT block publishing for shadow pages/posts.)
 */
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
    if ( empty( $postarr['ID'] ) ) return $data;

    $post_id = (int) $postarr['ID'];

    // Only apply to duplicate Version B shadows.
    if ( ! abtestkit_is_shadow_variant( $post_id ) ) {
        return $data;
    }

    $attempting_publish = in_array( $data['post_status'], [ 'publish', 'future', 'private' ], true );

    if ( $attempting_publish ) {
        $data['post_status'] = 'draft';
        $GLOBALS['abtestkit_shadow_publish_blocked'] = 1;
        abtestkit_mark_shadow_publish_blocked( $post_id );
    }

    return $data;
}, 99, 2 );

add_filter( 'redirect_post_location', function( $location, $post_id ) {
    if ( ! empty( $GLOBALS['abtestkit_shadow_publish_blocked'] ) && abtestkit_is_shadow_variant( (int) $post_id ) ) {
        $location = add_query_arg( [ 'abtestkit_shadow_blocked' => 1 ], $location );
    }
    return $location;
}, 99, 2 );

/**
 * Fix the wp-admin "Mine" view count so it matches our list-table exclusion logic.
 * Core computes "Mine" separately, so the count can include Version B items even if hidden.
 */
function abtestkit_fix_mine_view_count( array $views, string $post_type ) : array {
    if ( empty( $views['mine'] ) ) {
        return $views;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return $views;
    }

    // Count ONLY items authored by current user that are NOT Version B and NOT trash/auto-draft.
    // (Matches the intent of core "Mine" without pulling in our hidden variants.)
    $cache_key  = 'mine_count_' . $post_type . '_' . (int) $user_id;
    $mine_count = wp_cache_get( $cache_key, 'abtestkit' );

    if ( false === $mine_count ) {
        $q = new WP_Query(
            array(
                'post_type'              => $post_type,
                'author'                 => (int) $user_id,

                // "Mine" should include anything except trash/auto-draft.
                // WP_Query doesn't support post_status NOT IN directly, so we whitelist the common valid statuses.
                'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),

                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => false, // we need found_posts
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,

                'meta_query'             => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_abtestkit_shadow',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_abtestkit_variant_in_use',
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            )
        );

        $mine_count = (int) $q->found_posts;

        // Cache briefly to satisfy WPCS without risking stale counts for long.
        wp_cache_set( $cache_key, $mine_count, 'abtestkit', 60 );
    }

    // Core only shows "Mine" if there's something there.
    if ( $mine_count <= 0 ) {
        unset( $views['mine'] );
        return $views;
    }

    $formatted = number_format_i18n( $mine_count );

    // Replace the "(n)" count inside the existing Mine link HTML.
    $views['mine'] = preg_replace(
        '/<span class="count">\\([^)]*\\)<\\/span>/',
        '<span class="count">(' . $formatted . ')</span>',
        (string) $views['mine']
    );

    return $views;
}

add_filter( 'views_edit-product', function( $views ) {

    if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'edit_posts' ) ) {
        return $views;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'edit-product' ) {
        return $views;
    }

    $count_q = new WP_Query( [
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'relation' => 'OR',
                [ 'key' => '_abtestkit_shadow',         'compare' => 'EXISTS' ],
                [ 'key' => '_abtestkit_variant_in_use', 'compare' => 'EXISTS' ],
            ],
        ],
    ] );
    $count = (int) $count_q->found_posts;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $shadow_flag    = isset( $_GET['abtestkit_shadow'] ) ? sanitize_text_field( wp_unslash( $_GET['abtestkit_shadow'] ) ) : '';
    $is_shadow_view = ( $shadow_flag === '1' );

    $url = add_query_arg(
        [ 'post_type' => 'product', 'abtestkit_shadow' => 1 ],
        admin_url( 'edit.php' )
    );

    $class = $is_shadow_view ? ' class="current"' : '';
    $label = sprintf(
        /* translators: %s: Number of Version B products. */
        __( 'Version B Products <span class="count">(%s)</span>', 'abtestkit' ),
        number_format_i18n( $count )
    );

    $shadow_link = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, $label );

    if ( isset( $views['all'] ) ) {
        $out = [ 'all' => $views['all'], 'abtestkit_shadow' => $shadow_link ];
        foreach ( $views as $k => $v ) {
            if ( $k === 'all' ) {
                continue;
            }
            $out[ $k ] = $v;
        }
        $views = $out;
    } else {
        $views['abtestkit_shadow'] = $shadow_link;
    }

    $views = abtestkit_fix_mine_view_count( $views, 'product' );
    return $views;
} );

/**
 * Add "Version B" view tabs in wp-admin list tables (Products / Posts / Pages).
 */

add_filter( 'views_edit-post', function( $views ) {

    if ( ! current_user_can( 'edit_posts' ) ) {
        return $views;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'edit-post' ) {
        return $views;
    }

    $count_q = new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [ 'key' => '_abtestkit_shadow', 'compare' => 'EXISTS' ],
        ],
    ] );
    $count = (int) $count_q->found_posts;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $shadow_flag    = isset( $_GET['abtestkit_shadow'] ) ? sanitize_text_field( wp_unslash( $_GET['abtestkit_shadow'] ) ) : '';
    $is_shadow_view = ( $shadow_flag === '1' );

    $url = add_query_arg(
        [ 'post_type' => 'post', 'abtestkit_shadow' => 1 ],
        admin_url( 'edit.php' )
    );

    $class = $is_shadow_view ? ' class="current"' : '';
    $label = sprintf(
        /* translators: %s: Number of Version B posts. */
        __( 'Version B Posts <span class="count">(%s)</span>', 'abtestkit' ),
        number_format_i18n( $count )
    );

    $shadow_link = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, $label );

    if ( isset( $views['all'] ) ) {
        $out = [ 'all' => $views['all'], 'abtestkit_shadow' => $shadow_link ];
        foreach ( $views as $k => $v ) { if ( $k === 'all' ) continue; $out[ $k ] = $v; }
        $views = $out; // don't return yet — we also need to fix "Mine"
    } else {
        $views['abtestkit_shadow'] = $shadow_link;
    }

    $views = abtestkit_fix_mine_view_count( $views, 'post' );
    return $views;
} );

add_filter( 'views_edit-page', function( $views ) {

    if ( ! current_user_can( 'edit_pages' ) ) {
        return $views;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'edit-page' ) {
        return $views;
    }

    $count_q = new WP_Query( [
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [ 'key' => '_abtestkit_shadow', 'compare' => 'EXISTS' ],
        ],
    ] );
    $count = (int) $count_q->found_posts;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $shadow_flag    = isset( $_GET['abtestkit_shadow'] ) ? sanitize_text_field( wp_unslash( $_GET['abtestkit_shadow'] ) ) : '';
    $is_shadow_view = ( $shadow_flag === '1' );

    $url = add_query_arg(
        [ 'post_type' => 'page', 'abtestkit_shadow' => 1 ],
        admin_url( 'edit.php' )
    );

    $class = $is_shadow_view ? ' class="current"' : '';
    $label = sprintf(
        /* translators: %s: Number of Version B pages. */
        __( 'Version B Pages <span class="count">(%s)</span>', 'abtestkit' ),
        number_format_i18n( $count )
    );

    $shadow_link = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, $label );

    if ( isset( $views['all'] ) ) {
        $out = [ 'all' => $views['all'], 'abtestkit_shadow' => $shadow_link ];
        foreach ( $views as $k => $v ) {
            if ( $k === 'all' ) continue;
            $out[ $k ] = $v;
        }
        $views = $out; // <-- IMPORTANT: do not return yet, we still need to fix "Mine"
    } else {
        $views['abtestkit_shadow'] = $shadow_link;
    }

    $views = abtestkit_fix_mine_view_count( $views, 'page' );
    return $views;
} );

/**
 * Hide Version B variants from wp-admin list tables by default,
 * but show them when the "Version B" view is active.
 *
 * Applies to Products, Posts, and Pages.
 *
 * - Duplicates use: _abtestkit_shadow
 * - Existing B (SEO-safe) uses: _abtestkit_variant_in_use
 *
 * This also ensures they never show up under the "Mine" view.
 */
add_action( 'pre_get_posts', function( $q ) {
    if ( ! is_admin() ) return;
    if ( ! $q->is_main_query() ) return;

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! in_array( $screen->id, [ 'edit-product', 'edit-post', 'edit-page' ], true ) ) return;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $shadow_flag    = isset( $_GET['abtestkit_shadow'] ) ? sanitize_text_field( wp_unslash( $_GET['abtestkit_shadow'] ) ) : '';
    $is_shadow_view = ( $shadow_flag === '1' );

    // Trash view: keep variants visible so they can be deleted/recovered.
    $requested_status = $q->get( 'post_status' );
    if ( empty( $requested_status ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
    }
    $is_trash_view = (string) $requested_status === 'trash';

    $meta_query = (array) $q->get( 'meta_query' );

    // Remove any existing clauses we might add (defensive).
    $meta_query = array_values( array_filter( $meta_query, function( $clause ) {
        if ( ! is_array( $clause ) ) return true;
        if ( isset( $clause['key'] ) && in_array( $clause['key'], [ '_abtestkit_shadow', '_abtestkit_variant_in_use' ], true ) ) {
            return false;
        }
        return true;
    } ) );

    if ( $is_shadow_view ) {
        // Show ALL Version B items (duplicates + existing B in use).
        $meta_query[] = [
            'relation' => 'OR',
            [ 'key' => '_abtestkit_shadow',         'compare' => 'EXISTS' ],
            [ 'key' => '_abtestkit_variant_in_use', 'compare' => 'EXISTS' ],
        ];
        $q->set( 'meta_query', $meta_query );
        return;
    }

    if ( ! $is_trash_view ) {
        // Hide ALL Version B items from normal tabs (All, Published, Draft, Mine, etc).
        $meta_query[] = [ 'key' => '_abtestkit_shadow',         'compare' => 'NOT EXISTS' ];
        $meta_query[] = [ 'key' => '_abtestkit_variant_in_use', 'compare' => 'NOT EXISTS' ];
        $q->set( 'meta_query', $meta_query );
    }
}, 20 );

// Bust admin shadow-count cache when products/posts/pages change.
add_action( 'save_post', function( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( empty( $post ) ) {
        return;
    }

    if ( in_array( $post->post_type, [ 'product', 'post', 'page' ], true ) ) {
        abtestkit_clear_shadow_counts_cache( $post->post_type );
    }
}, 10, 3 );

add_action( 'trashed_post', function( $post_id ) {
    $pt = get_post_type( $post_id );
    if ( in_array( $pt, [ 'product', 'post', 'page' ], true ) ) {
        abtestkit_clear_shadow_counts_cache( $pt );
    }
}, 10, 1 );

add_action( 'untrashed_post', function( $post_id ) {
    $pt = get_post_type( $post_id );
    if ( in_array( $pt, [ 'product', 'post', 'page' ], true ) ) {
        abtestkit_clear_shadow_counts_cache( $pt );
    }
}, 10, 1 );

add_action( 'deleted_post', function( $post_id ) {
    $pt = get_post_type( $post_id );
    if ( in_array( $pt, [ 'product', 'post', 'page' ], true ) ) {
        abtestkit_clear_shadow_counts_cache( $pt );
    }
}, 10, 1 );

// Optional: catches status transitions that don't go through save_post the way you expect.
add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
    if ( empty( $post ) ) return;
    if ( $new_status === $old_status ) return;

    if ( in_array( $post->post_type, [ 'product', 'post', 'page' ], true ) ) {
        abtestkit_clear_shadow_counts_cache( $post->post_type );
    }
}, 10, 3 );

add_filter( 'wp_count_posts', function( $counts, $type, $perm ) {

    if ( ! in_array( $type, [ 'product', 'post', 'page' ], true ) ) {
        return $counts;
    }

    if ( ! is_admin() ) {
        return $counts;
    }

    global $wpdb;

    $cache_group = 'abtestkit';
    $cache_key   = 'shadow_counts_by_status_' . $type . '_' . (int) get_current_blog_id();

    $rows = wp_cache_get( $cache_key, $cache_group );
    if ( false === $rows ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT p.post_status, COUNT(*) AS c
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID
                   AND pm.meta_key = %s
                WHERE p.post_type = %s
                GROUP BY p.post_status
                ",
                '_abtestkit_shadow',
                $type
            ),
            ARRAY_A
        );

        wp_cache_set( $cache_key, $rows, $cache_group, 60 );
    }

    if ( empty( $rows ) ) {
        return $counts;
    }

    foreach ( $rows as $r ) {
        $status = (string) $r['post_status'];
        $c      = (int) $r['c'];

        if ( $status === 'trash' ) {
            continue;
        }

        if ( isset( $counts->$status ) ) {
            $counts->$status = max( 0, (int) $counts->$status - $c );
        }
    }

    if ( isset( $counts->total ) ) {
        $sum = 0;
        foreach ( get_object_vars( $counts ) as $k => $v ) {
            if ( $k === 'total' ) continue;
            $sum += (int) $v;
        }
        $counts->total = $sum;
    }

    return $counts;
}, 10, 3 );

/**
 * Big banner on version b edit screens.
 */
add_action( 'admin_notices', function() {
    if ( ! is_admin() ) return;

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->base !== 'post' ) return;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
    if ( ! $post_id ) return;

    $post = get_post( $post_id );
    if ( ! $post ) return;

    $post_type = (string) $post->post_type;

    // ───────────────────────────────────────────────────────────
    // 1) Existing banner: Shadow Product helper (keep behaviour)
    // ───────────────────────────────────────────────────────────
    if ( $post_type === 'product' && abtestkit_is_shadow_product( $post_id ) ) {

        $shadow_of = (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );

        echo '<div class="notice notice-warning" style="border-left-color:#fc510b;">';
        echo '<p><strong>abtestkit:</strong> This is your <strong>Version B product</strong> for this A/B test.</p>';
        echo '<p>It is only used to build and preview the Version B experience. It will not go live as its own product.</p>';
        echo '<p>This product <strong>cannot be published</strong>. If you try to publish it, abtestkit will keep it saved as a draft.</p>';
        if ( $shadow_of ) {
            echo '<p>Linked Version A product ID: <code>' . esc_html( (string) $shadow_of ) . '</code></p>';
        }
        echo '<p><strong>When you are done, save as draft and close this tab to return to your test.</strong></p>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['abtestkit_shadow_blocked'] ) ) {
            echo '<p><strong style="color:#d63638;">Publish blocked:</strong> Shadow products are never allowed to go live.</p>';
        }
        echo '</div>';

        return;
    }

    // ───────────────────────────────────────────────────────────
    // 2) New banner: Version B helper for Posts + Pages
    //    - duplicates: _abtestkit_shadow (+ optional _abtestkit_shadow_of)
    //    - existing B:  _abtestkit_variant_in_use
    // ───────────────────────────────────────────────────────────
    if ( $post_type !== 'post' && $post_type !== 'page' ) {
        return;
    }

    $is_shadow    = (bool) get_post_meta( $post_id, '_abtestkit_shadow', true );
    $is_existingB = (bool) get_post_meta( $post_id, '_abtestkit_variant_in_use', true );

    if ( ! $is_shadow && ! $is_existingB ) {
        return;
    }

    $shadow_of = (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );

    $type_label      = ( $post_type === 'page' ) ? 'Page' : 'Post';
    $type_label_lc   = strtolower( $type_label );
    $show_blocked_notice = $is_shadow && abtestkit_consume_shadow_publish_blocked( $post_id );

    echo '<div class="notice notice-warning" style="border-left-color:#fc510b;">';

    if ( $is_shadow ) {
        echo '<p><strong>abtestkit:</strong> This is your <strong>Version B ' . esc_html( $type_label_lc ) . '</strong> for this A/B test.</p>';
        echo '<p>It is only used to build and preview the Version B experience. It will not go live as its own ' . esc_html( $type_label_lc ) . '.</p>';
        echo '<p>This ' . esc_html( $type_label_lc ) . ' <strong>cannot be published</strong>. If you try to publish it, abtestkit will keep it saved as a draft.</p>';
        if ( $shadow_of ) {
            echo '<p>Linked Version A ' . esc_html( $type_label ) . ' ID: <code>' . esc_html( (string) $shadow_of ) . '</code></p>';
        }
        echo '<p><strong>When you are done, save as draft and close this tab to return to your test.</strong></p>';

        if ( $show_blocked_notice ) {
            echo '<p><strong style="color:#d63638;">Publish blocked:</strong> Shadow ' . esc_html( $type_label_lc ) . 's are never allowed to go live.</p>';
        }
    } else {
        echo '<p><strong>abtestkit:</strong> You are editing a <strong>Version B ' . esc_html( $type_label ) . '</strong> (used for A/B testing).</p>';
        echo '<p>Make your changes, <strong>publish/update</strong>, then <strong>close this tab</strong> to continue creating your test.</p>';
        if ( $shadow_of ) {
            echo '<p>Linked Version A ' . esc_html( $type_label ) . ' ID: <code>' . esc_html( (string) $shadow_of ) . '</code></p>';
        }
    }

    echo '</div>';
} );

// ── Admin Dashboard UI ───────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    // Top-level "abtestkit" menu (icon)
    add_menu_page(
        __( 'abtestkit', 'abtestkit' ),
        __( 'abtestkit', 'abtestkit' ),
        'manage_options',
        'abtestkit-dashboard',
        'abtestkit_render_page_tests_dashboard',
        plugins_url( 'assets/img/menu-icon.png', __FILE__ ),
        59
    );

    // Explicit "Dashboard" submenu under the icon
    add_submenu_page(
        'abtestkit-dashboard',
        __( 'Dashboard', 'abtestkit' ),
        __( 'Dashboard', 'abtestkit' ),
        'manage_options',
        'abtestkit-dashboard',
        'abtestkit_render_page_tests_dashboard'
    );
}, 9 );

/**
 * Show A/B test membership for pages, posts and products in the list tables.
 */
function abtestkit_register_ab_columns( $columns ) {
    $columns['abtestkit_variant'] = __( 'A/B Version', 'abtestkit' );
    $columns['abtestkit_test']    = __( 'A/B Test', 'abtestkit' );
    return $columns;
}

add_filter( 'manage_page_posts_columns', 'abtestkit_register_ab_columns' );
add_filter( 'manage_post_posts_columns', 'abtestkit_register_ab_columns' );

// WooCommerce products get a single "Test Status" column (no A/B Version column).
add_filter( 'manage_product_posts_columns', 'abtestkit_register_product_test_status_column' );

/**
 * Add a single "Test Status" column to WooCommerce products list table.
 * (Products don't have a physical Version B post, so A/B Version isn't shown here.)
 */
function abtestkit_register_product_test_status_column( $columns ) {
    // Insert right after the product name column when possible.
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'name' ) {
            $new['abtestkit_test_status'] = __( 'Test Status', 'abtestkit' );
        }
    }
    if ( ! isset( $new['abtestkit_test_status'] ) ) {
        $new['abtestkit_test_status'] = __( 'Test Status', 'abtestkit' );
    }
    return $new;
}

/**
 * Render the "Test Status" column for WooCommerce products.
 */
function abtestkit_render_product_test_status_column( $column, $post_id ) {
    if ( $column !== 'abtestkit_test_status' ) {
        return;
    }

    // If this row is a shadow product, resolve its control product ID.
    $is_shadow = (bool) get_post_meta( $post_id, '_abtestkit_shadow', true );
    $shadow_of = (int) get_post_meta( $post_id, '_abtestkit_shadow_of', true );
    $control_id = ( $is_shadow && $shadow_of > 0 ) ? $shadow_of : (int) $post_id;

    $matches = [];

    foreach ( abtestkit_pt_all() as $t ) {
        if ( empty( $t['id'] ) ) {
            continue;
        }
        // Only product tests.
        if ( ( $t['kind'] ?? '' ) !== 'product' ) {
            continue;
        }
        // Product tests are tied to the control product (variant_id is 0).
        if ( (int) ( $t['control_id'] ?? 0 ) !== (int) $control_id ) {
            continue;
        }
        $matches[] = $t;
    }

    if ( empty( $matches ) ) {
        echo '—';
        return;
    }

    // Prefer running > paused > draft > complete (best-effort).
    $rank = [ 'running' => 1, 'paused' => 2, 'draft' => 3, 'complete' => 4 ];
    usort( $matches, function( $a, $b ) use ( $rank ) {
        $sa = (string) ( $a['status'] ?? 'paused' );
        $sb = (string) ( $b['status'] ?? 'paused' );
        $ra = $rank[ $sa ] ?? 99;
        $rb = $rank[ $sb ] ?? 99;
        return $ra <=> $rb;
    } );

    $t       = $matches[0];
    $status  = (string) ( $t['status'] ?? 'paused' );
    $title   = (string) ( $t['title'] ?? $t['id'] ?? '' );
    $test_id = (string) ( $t['id'] ?? '' );

    switch ( $status ) {
        case 'running':
            $status_label = __( 'Running', 'abtestkit' );
            break;
        case 'complete':
            $status_label = __( 'Complete', 'abtestkit' );
            break;
        case 'draft':
            $status_label = __( 'Draft', 'abtestkit' );
            break;
        case 'paused':
        default:
            $status_label = __( 'Paused', 'abtestkit' );
            break;
    }

    $url = add_query_arg(
        [
            'page'      => 'abtestkit-dashboard',
            'tab'       => 'products',
            'highlight' => $test_id,
        ],
        admin_url( 'admin.php' )
    );

    printf(
        '<a href="%s" title="%s">%s</a>',
        esc_url( $url ),
        esc_attr( $title ),
        esc_html( $status_label )
    );
}

/**
 * Render A/B test info in admin list columns.
 */
function abtestkit_render_ab_columns( $column, $post_id ) {

    if ( $column !== 'abtestkit_variant' && $column !== 'abtestkit_test' ) {
        return;
    }

    $variant_label = '—';
    $test_title    = '—';
    $test_status   = '';
    $test_id       = '';
    $post_type     = get_post_type( $post_id );

    foreach ( abtestkit_pt_all() as $t ) {
        if ( empty( $t['id'] ) ) {
            continue;
        }

        $control_id = isset( $t['control_id'] ) ? (int) $t['control_id'] : 0;
        $variant_id = isset( $t['variant_id'] ) ? (int) $t['variant_id'] : 0;

        if ( $control_id === (int) $post_id ) {
            $variant_label = __( 'Version A', 'abtestkit' );
            $test_title    = $t['title'] ?? $t['id'];
            $test_status   = $t['status'] ?? 'paused';
            $test_id       = (string) ( $t['id'] ?? '' );
            break;
        }

        if ( $variant_id === (int) $post_id ) {
            $variant_label = __( 'Version B', 'abtestkit' );
            $test_title    = $t['title'] ?? $t['id'];
            $test_status   = $t['status'] ?? 'paused';
            $test_id       = (string) ( $t['id'] ?? '' );
            break;
        }
    }

    if ( $column === 'abtestkit_variant' ) {
        echo esc_html( $variant_label );
        return;
    }

    // A/B Test column
    if ( $test_title === '—' ) {
        echo '—';
        return;
    }

    // Human label for status (stored values: paused|running|complete|draft)
    switch ( (string) $test_status ) {
        case 'running':
            $status_label = __( 'Running', 'abtestkit' );
            break;
        case 'complete':
            $status_label = __( 'Complete', 'abtestkit' );
            break;
        case 'draft':
            $status_label = __( 'Draft', 'abtestkit' );
            break;
        case 'paused':
        default:
            $status_label = __( 'Paused', 'abtestkit' );
            break;
    }

    $linked_text = sprintf(
        '%s (%s)',
        (string) $test_title,
        (string) $status_label
    );

    // “Relevant tab” (best-effort): pages/posts/products.
    // Even if your React dashboard ignores this, it won’t break anything.
    $tab = 'pages';
    if ( $post_type === 'post' ) {
        $tab = 'posts';
    } elseif ( $post_type === 'product' ) {
        $tab = 'products';
    }

    $url = add_query_arg(
        [
            'page'      => 'abtestkit-dashboard',
            'tab'       => $tab,
            'highlight' => $test_id,
        ],
        admin_url( 'admin.php' )
    );

    printf(
        '<a href="%s">%s</a>',
        esc_url( $url ),
        esc_html( $linked_text )
    );
}

add_action( 'manage_page_posts_custom_column', 'abtestkit_render_ab_columns', 10, 2 );
add_action( 'manage_post_posts_custom_column', 'abtestkit_render_ab_columns', 10, 2 );
add_action( 'manage_product_posts_custom_column', 'abtestkit_render_product_test_status_column', 10, 2 );

/**
 * Small CSS tweak so the columns don’t squash.
 */
add_action( 'admin_head-edit.php', function () {
    ?>
    <style>
        .column-abtestkit_variant,
        .column-abtestkit_test,
        .column-abtestkit_test_status {
            width: 120px;
        }
    </style>
    <?php
} );

/** Handle create/start/pause/reset/apply/delete */
add_action( 'admin_post_abtestkit_pt_action', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'forbidden' );
    check_admin_referer( 'abtestkit_pt_action' );

    $do = sanitize_key( $_POST['do'] ?? '' );
    $id         = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
    $return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';

    if ( $do === 'create' ) {
        $src = absint( $_POST['source_page'] ?? 0 );
        if ( $src ) {
            $post_type = get_post_type( $src );

            if ( $post_type === 'product' ) {
                // Product test: no duplicate product; we'll use overrides on the control product.
                $b_id = 0;
            } else {
                $b_id = abtestkit_duplicate_post_deep( $src );
            }

            if ( $post_type === 'product' || $b_id ) {
                $test = [
                    'id'              => 'pt-' . substr( md5( $src . '|' . microtime( true ) ), 0, 8 ),
                    'title'           => get_the_title( $src ),
                    'control_id'      => $src,
                    'variant_id'      => ( $post_type === 'product' ) ? 0 : $b_id,
                    'status'          => 'paused',
                    'split'           => 50,
                    'cookie_ttl_days' => 30,
                    'started_at'      => 0,
                    'finished_at'     => 0,
                    'paused_at'       => 0,
                    'paused_total'    => 0,
                    'kind'            => ( $post_type === 'product' ) ? 'product' : 'page',
                ];
                abtestkit_pt_put( $test );

                if ( function_exists( 'abtestkit_review_prompt_note_test_created' ) ) {
                    abtestkit_review_prompt_note_test_created();
                }

                // Once the user has created a test, never show onboarding again.
                update_option( 'abtestkit_onboarding_done', '1' );

                wp_safe_redirect( add_query_arg( [ 'page' => 'abtestkit-dashboard', 'made' => '1' ], admin_url( 'admin.php' ) ) );
                exit;
            }
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'abtestkit-dashboard', 'error' => 'create_failed' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    if ( ! $id ) {
        wp_safe_redirect( add_query_arg( [ 'page' => 'abtestkit-dashboard', 'error' => 'no_id' ], admin_url( 'admin.php' ) ) );
        exit;
    }
    $test = abtestkit_pt_get( $id );
    if ( ! $test ) {
        wp_safe_redirect( add_query_arg( [ 'page' => 'abtestkit-dashboard', 'error' => 'not_found' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    switch ( $do ) {
        case 'start':
            $confirm_broken_resume = isset( $_POST['confirm_broken_resume'] )
                && '1' === sanitize_text_field( wp_unslash( $_POST['confirm_broken_resume'] ) );

            if ( ! empty( $test['auto_paused_broken'] ) && ! $confirm_broken_resume ) {
                $redirect = $return_url
                    ? add_query_arg( 'abtestkit_error', 'broken_resume_requires_confirmation', $return_url )
                    : add_query_arg( [ 'page' => 'abtestkit-dashboard', 'error' => 'broken_resume_requires_confirmation' ], admin_url( 'admin.php' ) );

                wp_safe_redirect( $redirect );
                exit;
            }

            // Block starting if either A or B page is already used by another running test
            $conflicts = abtestkit_pt_conflicts_for_pages( (int) $test['control_id'], (int) $test['variant_id'], (string) $test['id'] );
            if ( ! empty( $conflicts ) ) {
                // Bounce back with an error flag; UI can show a notice
                wp_safe_redirect( add_query_arg(
                    [ 'page' => 'abtestkit-dashboard', 'error' => 'conflict_running', 'conflicts' => implode(',', $conflicts) ],
                    admin_url( 'admin.php' )
                ) );
                exit;
            }

            // Preserve the original started_at across pause/resume.
            // We track paused_total for runtime calculations, but we must NOT move started_at,
            // otherwise the performance timeline loses earlier history after resume.
            $prev_status = isset( $test['status'] ) ? (string) $test['status'] : 'paused';

            if ( $prev_status === 'paused' ) {
                $paused_at = isset( $test['paused_at'] ) ? (int) $test['paused_at'] : 0;
                if ( $paused_at > 0 ) {
                    $delta = max( 0, time() - $paused_at );
                    $test['paused_total'] = (int) ( $test['paused_total'] ?? 0 ) + $delta;
                    $test['paused_at']    = 0;
                }
            }

            if ( (int) ( $test['started_at'] ?? 0 ) <= 0 || $prev_status === 'draft' ) {
                $test['started_at'] = time();
            }

            $test['status'] = 'running';

            if ( $confirm_broken_resume ) {
                unset(
                    $test['auto_paused_broken'],
                    $test['auto_paused_broken_at'],
                    $test['auto_paused_broken_summary'],
                    $test['auto_paused_broken_checks']
                );

                if (
                    ( $test['kind'] ?? '' ) === 'custom_html'
                    && function_exists( 'abtestkit_html_runtime_health_delete' )
                ) {
                    abtestkit_html_runtime_health_delete( (string) $test['id'] );
                }
            }

            abtestkit_pt_put( $test );

            if ( $return_url ) {
                wp_safe_redirect( $return_url );
                exit;
            }
            break;

        case 'pause':
            // Record when the pause started so we can exclude it from runtime on resume.
            if ( ( $test['status'] ?? 'paused' ) === 'running' ) {
                $test['paused_at'] = time();
                if ( ! isset( $test['paused_total'] ) ) {
                    $test['paused_total'] = 0;
                }
            }

            $test['status'] = 'paused';
            abtestkit_pt_put( $test );

            if ( $return_url ) {
                wp_safe_redirect( $return_url );
                exit;
            }
            break;
        case 'delete':
            $variant_id = (int) ( $test['variant_id'] ?? 0 );
            $control_id = (int) ( $test['control_id'] ?? 0 );

            /*
             * Normal in-plugin delete respects the UI choice.
             *
             * Dashboard/performance send trash_b=1 when their single delete
             * confirmation explains that an abtestkit-owned Version B is included.
             *
             * Full automatic cleanup of ABTestKit-owned shadows is reserved for uninstall.
             */
            $trash_b_raw = isset( $_POST['trash_b'] )
                ? sanitize_text_field( wp_unslash( $_POST['trash_b'] ) )
                : '0';

            $trash_b = ( '1' === $trash_b_raw );

            if ( $trash_b && $variant_id > 0 && get_post_status( $variant_id ) ) {
                $is_shadow_b = ( (int) get_post_meta( $variant_id, '_abtestkit_shadow', true ) === 1 );
                $shadow_of   = (int) get_post_meta( $variant_id, '_abtestkit_shadow_of', true );

                /*
                 * Safety:
                 * - Delete only ABTestKit-created shadow Version B objects.
                 * - Never delete existing user-owned content, even if trash_b=1.
                 */
                if ( $is_shadow_b && $control_id > 0 && $shadow_of === $control_id ) {
                    if ( function_exists( 'abtestkit_delete_owned_shadow_variant' ) ) {
                        abtestkit_delete_owned_shadow_variant( $variant_id );
                    } else {
                        wp_trash_post( $variant_id );
                    }
                }
            }

            abtestkit_pt_delete( (string) $test['id'] );
            break;

        case 'reset':
            global $wpdb;
            $table = defined( 'ABTESTKIT_EVENTS_TABLE' ) ? ABTESTKIT_EVENTS_TABLE : ( $wpdb->prefix . 'abtestkit_events' );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table}
                     WHERE ab_test_id = %s",
                    (string) $test['id']
                )
            );
            // phpcs:enable
            break;
        case 'apply_b_winner':
            // Two flavours:
            // - 'page' tests: copy the B page's content/meta/taxonomies onto A, then mark test complete.
            // - 'product' tests: commit overrides from the virtual B onto the real product, then mark test complete.
            $kind = isset( $test['kind'] ) ? (string) $test['kind'] : 'page';

            // Snapshot now (before we overwrite A / commit overrides / trash B).
            abtestkit_pt_snapshot_completed_test( $test );

            if ( in_array( $kind, [ 'custom_css', 'custom_html' ], true ) ) {
                $test['status']      = 'complete';
                $test['winner']      = 'B';
                $test['finished_at'] = time();
                abtestkit_pt_put( $test );
                break;
            }

            if ( $kind === 'product' ) {
                global $wpdb;

                $product_id = (int) $test['control_id'];
                $shadow_id  = (int) $test['variant_id'];

                if (
                    $product_id > 0
                    && $shadow_id > 0
                    && function_exists( 'wc_get_product' )
                    && get_post_type( $product_id ) === 'product'
                    && get_post_type( $shadow_id ) === 'product'
                    && function_exists( 'abtestkit_is_shadow_product' )
                    && abtestkit_is_shadow_product( $shadow_id )
                    && (int) get_post_meta( $shadow_id, '_abtestkit_shadow_of', true ) === $product_id
                ) {
                    $shadow_post    = get_post( $shadow_id );
                    $control_post   = get_post( $product_id );
                    $shadow_product = wc_get_product( $shadow_id );

                    if ( $shadow_post && $control_post && ( $shadow_product instanceof WC_Product ) ) {

                        /*
                         * 1) Copy core post fields from B -> A
                         * Keep A's ID, slug, status and authoring identity stable.
                         */
                        wp_update_post(
                            [
                                'ID'           => $product_id,
                                'post_title'   => (string) $shadow_post->post_title,
                                'post_content' => (string) $shadow_post->post_content,
                                'post_excerpt' => (string) $shadow_post->post_excerpt,
                                'menu_order'   => (int) $shadow_post->menu_order,
                            ]
                        );

                        /*
                         * 2) Copy taxonomies from B -> A
                         * This brings across product categories, tags, attributes, etc.
                         */
                        $taxonomies = get_object_taxonomies( 'product' );
                        foreach ( $taxonomies as $taxonomy ) {
                            $terms = wp_get_object_terms( $shadow_id, $taxonomy, [ 'fields' => 'ids' ] );
                            if ( ! is_wp_error( $terms ) ) {
                                wp_set_object_terms( $product_id, $terms, $taxonomy, false );
                            }
                        }

                        /*
                         * 3) Replace most post meta on A with B's post meta
                         * This is the important part for ACF/template/meta-driven setups.
                         *
                         * We preserve A-side identity/commerce history fields so the
                         * live product keeps its real stock/order/review/SKU record.
                         */
                        $preserve_meta_keys = [
                            '_sku',
                            '_stock',
                            '_stock_status',
                            '_manage_stock',
                            '_backorders',
                            '_backorders_allowed',
                            '_backordered',
                            'total_sales',
                            '_wc_average_rating',
                            '_wc_review_count',
                            '_wc_rating_count',
                            '_edit_lock',
                            '_edit_last',

                            /*
                             * Keep A's SEO/plugin identity meta intact.
                             * This preserves the live product's existing SEO setup
                             * even when Version B content/design/meta is applied.
                             */
                            '_yoast_wpseo_title',
                            '_yoast_wpseo_metadesc',
                            '_yoast_wpseo_focuskw',
                            '_yoast_wpseo_focuskeywords',
                            '_yoast_wpseo_canonical',
                            '_yoast_wpseo_opengraph-title',
                            '_yoast_wpseo_opengraph-description',
                            '_yoast_wpseo_opengraph-image',
                            '_yoast_wpseo_twitter-title',
                            '_yoast_wpseo_twitter-description',
                            '_yoast_wpseo_twitter-image',
                            '_yoast_wpseo_meta-robots-noindex',
                            '_yoast_wpseo_meta-robots-nofollow',
                            '_yoast_wpseo_schema_page_type',
                            '_yoast_wpseo_schema_article_type',

                            'rank_math_title',
                            'rank_math_description',
                            'rank_math_focus_keyword',
                            'rank_math_canonical_url',
                            'rank_math_robots',
                            'rank_math_facebook_title',
                            'rank_math_facebook_description',
                            'rank_math_facebook_image',
                            'rank_math_twitter_title',
                            'rank_math_twitter_description',
                            'rank_math_twitter_image',
                            'rank_math_schema_type',

                            '_aioseo_title',
                            '_aioseo_description',
                            '_aioseo_keywords',
                            '_aioseo_og_title',
                            '_aioseo_og_description',
                            '_aioseo_og_image',
                            '_aioseo_twitter_title',
                            '_aioseo_twitter_description',
                            '_aioseo_twitter_image',
                            '_aioseo_canonical_url',
                            '_aioseo_robots_noindex',
                            '_aioseo_robots_nofollow',

                            // Never copy shadow markers onto the real product.
                            '_abtestkit_shadow',
                            '_abtestkit_shadow_of',
                            '_abtestkit_variant_in_use',
                            '_abtestkit_variant_of',
                            '_abtestkit_variant_test_id',
                        ];

                        $shadow_meta_cache_key   = 'abtestkit_shadow_meta_rows_' . (int) $shadow_id;
                        $product_meta_cache_key  = 'abtestkit_product_meta_keys_' . (int) $product_id;

                        $meta_rows = wp_cache_get( $shadow_meta_cache_key, 'abtestkit' );
                        if ( false === $meta_rows ) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $meta_rows = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT meta_key, meta_value
                                     FROM {$wpdb->postmeta}
                                     WHERE post_id = %d",
                                    $shadow_id
                                ),
                                ARRAY_A
                            );

                            wp_cache_set( $shadow_meta_cache_key, $meta_rows, 'abtestkit', MINUTE_IN_SECONDS );
                        }

                        if ( is_array( $meta_rows ) ) {
                            // Clear existing A meta except preserved keys.
                            $existing_keys = wp_cache_get( $product_meta_cache_key, 'abtestkit' );
                            if ( false === $existing_keys ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                                $existing_keys = $wpdb->get_col(
                                    $wpdb->prepare(
                                        "SELECT meta_key
                                         FROM {$wpdb->postmeta}
                                         WHERE post_id = %d",
                                        $product_id
                                    )
                                );

                                wp_cache_set( $product_meta_cache_key, $existing_keys, 'abtestkit', MINUTE_IN_SECONDS );
                            }

                            if ( is_array( $existing_keys ) ) {
                                foreach ( $existing_keys as $meta_key ) {
                                    $meta_key = (string) $meta_key;
                                    if ( in_array( $meta_key, $preserve_meta_keys, true ) ) {
                                        continue;
                                    }
                                    delete_post_meta( $product_id, $meta_key );
                                }
                            }

                            // Copy B meta onto A, excluding preserved keys.
                            foreach ( $meta_rows as $row ) {
                                $meta_key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';
                                if ( $meta_key === '' ) {
                                    continue;
                                }

                                if ( in_array( $meta_key, $preserve_meta_keys, true ) ) {
                                    continue;
                                }

                                $meta_value = $row['meta_value'] ?? '';
                                add_post_meta( $product_id, $meta_key, maybe_unserialize( $meta_value ) );
                            }
                        }

                        /*
                         * 4) Make absolutely sure key Woo price/image fields are aligned
                         * with the shadow product after meta copy.
                         */
                        $regular_price = $shadow_product->get_regular_price( 'edit' );
                        $sale_price    = $shadow_product->get_sale_price( 'edit' );
                        $active_price  = $shadow_product->get_price( 'edit' );
                        $image_id      = (int) $shadow_product->get_image_id( 'edit' );
                        $gallery_ids   = $shadow_product->get_gallery_image_ids( 'edit' );

                        if ( $regular_price !== '' && $regular_price !== null ) {
                            update_post_meta( $product_id, '_regular_price', wc_format_decimal( $regular_price ) );
                        }

                        if ( $sale_price !== '' && $sale_price !== null ) {
                            update_post_meta( $product_id, '_sale_price', wc_format_decimal( $sale_price ) );
                        } else {
                            delete_post_meta( $product_id, '_sale_price' );
                        }

                        if ( $active_price !== '' && $active_price !== null ) {
                            update_post_meta( $product_id, '_price', wc_format_decimal( $active_price ) );
                        }

                        if ( $image_id > 0 ) {
                            update_post_meta( $product_id, '_thumbnail_id', $image_id );
                        } else {
                            delete_post_meta( $product_id, '_thumbnail_id' );
                        }

                        if ( is_array( $gallery_ids ) ) {
                            $gallery_ids = array_values( array_filter( array_map( 'absint', $gallery_ids ) ) );
                            update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
                        }

                        /*
                         * 5) For variable products / variable subscriptions,
                         * merge each shadow child variation back onto its real A child.
                         */
                        if ( function_exists( 'abtestkit_pt_apply_shadow_variations_to_product' ) ) {
                            abtestkit_pt_apply_shadow_variations_to_product( (int) $product_id, (int) $shadow_id );
                        }

                        /*
                         * 6) Clear caches/transients
                         */
                        clean_post_cache( $product_id );
                        clean_object_term_cache( $product_id, 'product' );
                        wp_cache_delete( $product_id, 'posts' );

                        if ( function_exists( 'wc_delete_product_transients' ) ) {
                            wc_delete_product_transients( $product_id );
                            wc_delete_product_transients( $shadow_id );
                        }

                        /*
                         * 6) Auto-trash the shadow B product now it has been applied
                         */
                        if ( get_post_status( $shadow_id ) ) {
                            wp_trash_post( $shadow_id );
                        }
                    }
                }

                /*
                 * 7) Remove the test entirely so frontend assignment stops cleanly.
                 * No need to keep a live completed product test once B is committed.
                 */
                $test['status']      = 'complete';
                $test['winner']      = 'B';
                $test['finished_at'] = time();
                abtestkit_pt_put( $test );
                break;
            }

            // Default behaviour for page tests (with a real B page)
            $A = get_post( $test['control_id'] );
            $B = get_post( $test['variant_id'] );
            if ( $A && $B ) {
                // 1) Content/excerpt
                wp_update_post( [
                    'ID'           => $A->ID,
                    'post_content' => $B->post_content,
                    'post_excerpt' => $B->post_excerpt,
                ] );
                // 2) Meta
                $meta = get_post_meta( $B->ID );
                foreach ( $meta as $k => $vals ) {
                    if ( in_array( $k, [ '_edit_lock','_edit_last','_wp_old_slug' ], true ) ) {
                        continue;
                    }
                    delete_post_meta( $A->ID, $k );
                    foreach ( (array) $vals as $v ) {
                        add_post_meta( $A->ID, $k, maybe_unserialize( $v ) );
                    }
                }
                // 3) Taxonomies
                $taxes = get_object_taxonomies( $A->post_type );
                foreach ( $taxes as $tx ) {
                    $terms = wp_get_object_terms( $B->ID, $tx, [ 'fields' => 'ids' ] );
                    if ( ! is_wp_error( $terms ) ) {
                        wp_set_object_terms( $A->ID, $terms, $tx, false );
                    }
                }

                $test['status']      = 'complete';
                $test['finished_at'] = time();
                abtestkit_pt_put( $test );
                // If Version B is an existing page/post (not a shadow duplicate), restore SEO now the test is finished.
                if ( $B && ! abtestkit_is_shadow_variant( (int) $B->ID ) ) {
                    abtestkit_pt_unmark_existing_variant_in_use( (int) $B->ID, (string) $test['id'] );
                }

                wp_trash_post( $B->ID ); // optional
            }
            break;
        case 'keep_a_winner':
            $kind       = isset( $test['kind'] ) ? (string) $test['kind'] : 'page';
            $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
            $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;

            // Snapshot now before removing the live test record.
            abtestkit_pt_snapshot_completed_test( $test );

            // PRODUCT TESTS:
            // Keep A live exactly as-is, auto-trash the shadow B product if it is a valid shadow of A,
            // but keep the test record and mark it complete so it still shows on the dashboard.
            if ( $kind === 'product' ) {
                $trashed_b   = false;
                $is_shadow_b = false;

                if (
                    $variant_id > 0
                    && $control_id > 0
                    && function_exists( 'abtestkit_is_shadow_product' )
                    && abtestkit_is_shadow_product( $variant_id )
                ) {
                    $shadow_of   = (int) get_post_meta( $variant_id, '_abtestkit_shadow_of', true );
                    $is_shadow_b = ( $shadow_of === $control_id );
                }

                if ( $is_shadow_b && get_post_status( $variant_id ) ) {
                    wp_trash_post( $variant_id );
                    $trashed_b = true;
                }

                $test['status']      = 'complete';
                $test['winner']      = 'A';
                $test['finished_at'] = time();
                abtestkit_pt_put( $test );

                if ( function_exists( 'abtestkit_send_telemetry' ) && abtestkit_is_telemetry_opted_in() ) {
                    abtestkit_send_telemetry( 'winner_applied', [
                        'type'       => 'keep_a',
                        'test_id'    => (string) $test['id'],
                        'control_id' => $control_id,
                        'variant_id' => $variant_id,
                        'trashed_b'  => $trashed_b ? 1 : 0,
                    ] );
                }

                break;
            }

            // PAGE / POST TESTS:
            // End test, keep A as-is, optionally trash B depending on user choice.
            $test['status']      = 'complete';
            $test['finished_at'] = time();
            abtestkit_pt_put( $test );

            $trash_b_raw = isset( $_POST['trash_b'] )
                ? sanitize_text_field( wp_unslash( $_POST['trash_b'] ) )
                : '0';

            $trash_b = ( '1' === $trash_b_raw );

            if ( $trash_b && $variant_id > 0 && get_post_status( $variant_id ) ) {

                // If Version B is an existing page/post (not a shadow duplicate), restore SEO now the test is finished.
                if ( ! abtestkit_is_shadow_variant( $variant_id ) ) {
                    abtestkit_pt_unmark_existing_variant_in_use( $variant_id, (string) $test['id'] );
                }

                wp_trash_post( $variant_id );
            }

            if ( function_exists( 'abtestkit_send_telemetry' ) && abtestkit_is_telemetry_opted_in() ) {
                abtestkit_send_telemetry( 'winner_applied', [
                    'type'       => 'keep_a',
                    'test_id'    => (string) $test['id'],
                    'control_id' => $control_id,
                    'variant_id' => $variant_id,
                    'trashed_b'  => $trash_b ? 1 : 0,
                ] );
            }
            break;
        case 'save_split':
            $split = max( 0, min( 100, intval( $_POST['split'] ?? 50 ) ) );
            $test['split'] = $split;
            abtestkit_pt_put( $test );
            break;
    }

    wp_safe_redirect( add_query_arg( [ 'page' => 'abtestkit-dashboard', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
} );

/** Stats helper (reads abtestkit_events) */
/**
 * Compute stats for snapshotting (by ab_test_id only).
 * This avoids "0 stats" issues when events don't have a reliable post_id (e.g. Woo add_to_cart / AJAX).
 */
function abtestkit_pt_stats_for_snapshot( string $test_id ) : array {
    global $wpdb;

    $out = [
        'A' => [ 'impressions' => 0, 'clicks' => 0, 'purchases' => 0, 'revenue' => 0.0, 'revenue_per_customer' => 0.0 ],
        'B' => [ 'impressions' => 0, 'clicks' => 0, 'purchases' => 0, 'revenue' => 0.0, 'revenue_per_customer' => 0.0 ],
    ];

    if ( $test_id === '' ) {
        return $out;
    }

    $table = ABTESTKIT_EVENTS_TABLE;

    if ( abtestkit_events_table_has_column( 'amount' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT variant, event_type, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS revenue ' .
                'FROM %i ' .
                'WHERE ab_test_id = %s ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY variant, event_type',
                $table,
                $test_id
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT variant, event_type, COUNT(*) AS c, 0 AS revenue ' .
                'FROM %i ' .
                'WHERE ab_test_id = %s ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY variant, event_type',
                $table,
                $test_id
            ),
            ARRAY_A
        );
    }

    foreach ( (array) $rows as $r ) {
        $v       = isset( $r['variant'] ) ? $r['variant'] : '';
        $t       = isset( $r['event_type'] ) ? $r['event_type'] : '';
        $c       = isset( $r['c'] ) ? (int) $r['c'] : 0;
        $revenue = isset( $r['revenue'] ) ? (float) $r['revenue'] : 0.0;

        if ( ! isset( $out[ $v ] ) ) {
            continue;
        }

        if ( $t === 'purchase' ) {
            $out[ $v ]['purchases'] += $c;
            $out[ $v ]['revenue']   += $revenue;
        } elseif ( isset( $out[ $v ][ $t . 's' ] ) ) {
            $out[ $v ][ $t . 's' ] += $c;
        }
    }

    foreach ( [ 'A', 'B' ] as $variant ) {
        $purchases = (int) $out[ $variant ]['purchases'];
        $revenue   = (float) $out[ $variant ]['revenue'];
        $out[ $variant ]['revenue_per_customer'] = $purchases > 0 ? round( $revenue / $purchases, 2 ) : 0.0;
        $out[ $variant ]['revenue'] = round( $revenue, 2 );
    }

    return $out;
}

function abtestkit_pt_capture_post_snapshot( int $post_id ) : array {
    $p = $post_id > 0 ? get_post( $post_id ) : null;
    if ( ! ( $p instanceof WP_Post ) ) {
        return [];
    }

    return [
        'id'            => (int) $p->ID,
        'post_type'     => (string) $p->post_type,
        'post_status'   => (string) $p->post_status,
        'post_title'    => (string) $p->post_title,
        'post_name'     => (string) $p->post_name,
        'post_date_gmt' => (string) $p->post_date_gmt,
        'modified_gmt'  => (string) $p->post_modified_gmt,
        'post_excerpt'  => (string) $p->post_excerpt,
        'post_content'  => (string) $p->post_content,
    ];
}

function abtestkit_pt_capture_product_snapshot( int $product_id ) : array {
    $p = $product_id > 0 ? get_post( $product_id ) : null;
    if ( ! ( $p instanceof WP_Post ) ) {
        return [];
    }

    $regular = get_post_meta( $product_id, '_regular_price', true );
    $sale    = get_post_meta( $product_id, '_sale_price', true );
    $price   = get_post_meta( $product_id, '_price', true );

    if ( function_exists( 'wc_get_product' ) ) {
        $prod = wc_get_product( $product_id );
        if ( $prod ) {
            $regular = $prod->get_regular_price();
            $sale    = $prod->get_sale_price();
            $price   = $prod->get_price();
        }
    }

    $image_id    = (int) get_post_thumbnail_id( $product_id );
    $gallery_csv = (string) get_post_meta( $product_id, '_product_image_gallery', true );
    $gallery_ids = [];
    if ( $gallery_csv !== '' ) {
        $gallery_ids = array_values(
            array_filter(
                array_map(
                    'intval',
                    array_map( 'trim', explode( ',', $gallery_csv ) )
                )
            )
        );
    }

    return [
        'id'                => (int) $p->ID,
        'post_title'        => (string) $p->post_title,
        'short_description' => (string) $p->post_excerpt,
        'description'       => (string) $p->post_content,
        'regular_price'     => (string) $regular,
        'sale_price'        => (string) $sale,
        'price'             => (string) $price,
        'image_id'          => (int) $image_id,
        'gallery_ids'       => $gallery_ids,
    ];
}

/**
 * Snapshot the "final" state of a test so Completed/Winner tabs remain stable forever.
 * - final_stats: A/B impressions+clicks computed by ab_test_id
 * - snapshot_versions: the exact A and B versions at snapshot time
 */
function abtestkit_pt_snapshot_completed_test( array &$test ) : void {
    $test_id = isset( $test['id'] ) ? (string) $test['id'] : '';
    if ( $test_id === '' ) {
        return;
    }

    if ( empty( $test['final_stats'] ) || ! is_array( $test['final_stats'] ) ) {
        $test['final_stats'] = abtestkit_pt_stats_for_snapshot( $test_id );
    }

    if ( empty( $test['snapshot_at'] ) ) {
        $test['snapshot_at'] = time();
    }

    if ( empty( $test['snapshot_versions'] ) || ! is_array( $test['snapshot_versions'] ) ) {
        $kind = isset( $test['kind'] ) ? (string) $test['kind'] : 'page';

        if ( $kind === 'product' ) {
            $a = abtestkit_pt_capture_product_snapshot( (int) ( $test['control_id'] ?? 0 ) );

            // For product tests, B might be "virtual" via overrides (variant_id can be 0).
            $b = [];
            $variant_id = (int) ( $test['variant_id'] ?? 0 );
            if ( $variant_id > 0 ) {
                $b = abtestkit_pt_capture_product_snapshot( $variant_id );
            } else {
                $b = $a;
                $overrides = ( isset( $test['overrides'] ) && is_array( $test['overrides'] ) ) ? $test['overrides'] : [];
                foreach ( $overrides as $k => $v ) {
                    $b[ $k ] = $v;
                }
                $b['virtual'] = true;
            }

            $test['snapshot_versions'] = [
                'A' => $a,
                'B' => $b,
            ];
        } else {
            $test['snapshot_versions'] = [
                'A' => abtestkit_pt_capture_post_snapshot( (int) ( $test['control_id'] ?? 0 ) ),
                'B' => abtestkit_pt_capture_post_snapshot( (int) ( $test['variant_id'] ?? 0 ) ),
            ];
        }
    }
}

function abtestkit_pt_stats_default() : array {
    return [
        'A' => [ 'impressions' => 0, 'clicks' => 0, 'purchases' => 0, 'revenue' => 0.0, 'revenue_per_customer' => 0.0 ],
        'B' => [ 'impressions' => 0, 'clicks' => 0, 'purchases' => 0, 'revenue' => 0.0, 'revenue_per_customer' => 0.0 ],
    ];
}

function abtestkit_engagement_sample_rate() : float {
    $rate = defined( 'ABTESTKIT_ENGAGEMENT_SAMPLE_RATE' )
        ? (float) ABTESTKIT_ENGAGEMENT_SAMPLE_RATE
        : 1.0;

    return max( 0.0, min( 1.0, $rate ) );
}

function abtestkit_pt_engagement_stats( string $test_id ) : array {
    global $wpdb;

    $out = [
        'sample_rate' => abtestkit_engagement_sample_rate(),
        'A'           => [
            'count'      => 0,
            'avg_scroll' => 0.0,
            'avg_time'   => 0.0,
        ],
        'B'           => [
            'count'      => 0,
            'avg_scroll' => 0.0,
            'avg_time'   => 0.0,
        ],
    ];

    $test_id = abtestkit_sanitize_test_id( $test_id );

    if (
        $test_id === ''
        || ! abtestkit_events_table_has_column( 'scroll_pct' )
        || ! abtestkit_events_table_has_column( 'time_sec' )
    ) {
        return $out;
    }

    // Read the small engagement aggregate only when an admin opens performance.
    // Engagement events deliberately do not invalidate the larger conversion caches.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                variant,
                COUNT(*) AS sample_count,
                AVG(scroll_pct) AS avg_scroll,
                AVG(time_sec) AS avg_time
             FROM %i
             WHERE ab_test_id = %s
               AND event_type = 'engagement'
             GROUP BY variant",
            ABTESTKIT_EVENTS_TABLE,
            $test_id
        ),
        ARRAY_A
    );

    foreach ( (array) $rows as $row ) {
        $variant = isset( $row['variant'] ) ? (string) $row['variant'] : '';

        if ( ! isset( $out[ $variant ] ) ) {
            continue;
        }

        $out[ $variant ] = [
            'count'      => isset( $row['sample_count'] ) ? (int) $row['sample_count'] : 0,
            'avg_scroll' => isset( $row['avg_scroll'] ) ? round( (float) $row['avg_scroll'], 1 ) : 0.0,
            'avg_time'   => isset( $row['avg_time'] ) ? round( (float) $row['avg_time'], 1 ) : 0.0,
        ];
    }

    return $out;
}

function abtestkit_pt_stats_cache_key( string $test_id ) : string {
    return 'abtestkit_pt_stats_' . md5( $test_id );
}

function abtestkit_pt_performance_cache_key( string $test_id, string $range ) : string {
    return 'abtestkit_pt_perf_preview_v2_' . md5( $test_id . '|' . $range );
}

function abtestkit_pt_dashboard_cache_key( array $tests ) : string {
    $fingerprint = [];

    foreach ( $tests as $test ) {
        $fingerprint[] = [
            'id'         => (string) ( $test['id'] ?? '' ),
            'status'     => (string) ( $test['status'] ?? '' ),
            'updated_at' => (int) ( $test['snapshot_at'] ?? 0 ),
            'winner'     => (string) ( $test['winner'] ?? '' ),
            'title'      => (string) ( $test['title'] ?? '' ),
        ];
    }

    return 'abtestkit_pt_dash_preview_v2_' . md5( wp_json_encode( $fingerprint ) );
}

function abtestkit_pt_performance_cache_ttl( string $range ) : int {
    switch ( $range ) {
        case 'month':
            return 10 * MINUTE_IN_SECONDS;
        case 'week':
            return 5 * MINUTE_IN_SECONDS;
        case 'day':
        default:
            return 2 * MINUTE_IN_SECONDS;
    }
}

function abtestkit_pt_normalize_goal_for_display( $goal ) : string {
    $goal = sanitize_key( (string) $goal );

    if ( in_array( $goal, [ 'button', 'link', 'click' ], true ) ) {
        return 'clicks';
    }

    if ( in_array( $goal, [ 'destination', 'url', 'destination-url' ], true ) ) {
        return 'destination_url';
    }

    if ( in_array( $goal, [ 'scroll', 'scroll-depth', 'scroll_percentage', 'scroll-percentage' ], true ) ) {
        return 'scroll_depth';
    }

    return $goal;
}

function abtestkit_pt_scroll_depth_for_test( array $test ) : int {
    $depth = isset( $test['scroll_depth'] ) ? absint( $test['scroll_depth'] ) : 50;

    $allowed_depths = [ 25, 50, 75, 90 ];

    if ( ! in_array( $depth, $allowed_depths, true ) ) {
        $depth = 50;
    }

    return $depth;
}

function abtestkit_pt_click_targets_for_test( array $test ) : array {
    $keys = [
        'links',
        'click_targets',
        'clickTargets',
        'targets',
        'conversion_targets',
        'conversionTargets',
        'target_urls',
        'targetUrls',
        'link_targets',
        'linkTargets',
    ];

    $raw_targets = [];

    foreach ( $keys as $key ) {
        if ( empty( $test[ $key ] ) ) {
            continue;
        }

        $raw_targets = is_array( $test[ $key ] )
            ? $test[ $key ]
            : [ $test[ $key ] ];
        break;
    }

    if ( empty( $raw_targets ) ) {
        return [];
    }

    $targets = [];

    foreach ( $raw_targets as $target ) {
        if ( is_array( $target ) ) {
            $target = $target['target']
                ?? $target['url']
                ?? $target['href']
                ?? $target['selector']
                ?? $target['value']
                ?? '';
        } elseif ( is_object( $target ) ) {
            $target = $target->target
                ?? $target->url
                ?? $target->href
                ?? $target->selector
                ?? $target->value
                ?? '';
        }

        $target = sanitize_text_field( (string) $target );

        if ( $target !== '' ) {
            $targets[] = $target;
        }
    }

    return array_values( array_unique( $targets ) );
}

function abtestkit_pt_health_summary( array $test, array $stats = [], array $context = [] ) : array {
    $checks  = [];
    $overall = 'good';

    $add_check = static function( string $id, string $status, string $title, string $description = '' ) use ( &$checks, &$overall ) : void {
        $allowed_statuses = [ 'good', 'attention', 'broken', 'info' ];
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'info';
        }

        if ( $status === 'broken' ) {
            $overall = 'broken';
        } elseif ( $status === 'attention' && $overall !== 'broken' ) {
            $overall = 'attention';
        }

        $checks[] = [
            'id'          => $id,
            'status'      => $status,
            'title'       => $title,
            'description' => $description,
        ];
    };

    $test_id     = isset( $test['id'] ) ? (string) $test['id'] : '';
    $kind        = isset( $test['kind'] ) ? sanitize_key( (string) $test['kind'] ) : 'page';
    $status      = isset( $test['status'] ) ? sanitize_key( (string) $test['status'] ) : 'paused';
    $control_id  = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
    $variant_id  = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
    $goal        = isset( $test['goal'] ) ? abtestkit_pt_normalize_goal_for_display( $test['goal'] ) : '';
    $targets     = abtestkit_pt_click_targets_for_test( $test );
    $started_at  = isset( $test['started_at'] ) ? (int) $test['started_at'] : 0;
    $min_imps    = isset( $test['min_impressions'] ) ? max( 0, (int) $test['min_impressions'] ) : 50;
    $http_count  = isset( $context['http_excluded_count'] ) ? (int) $context['http_excluded_count'] : 0;
    $http_last   = isset( $context['http_excluded_last'] ) ? (string) $context['http_excluded_last'] : '';
    $preview_failure = function_exists( 'abtestkit_preview_health_latest_for_test' )
        ? abtestkit_preview_health_latest_for_test( $test )
        : [];
    $html_runtime_health = (
        $kind === 'custom_html'
        && $test_id !== ''
        && function_exists( 'abtestkit_html_runtime_health_get' )
    )
        ? abtestkit_html_runtime_health_get( $test_id )
        : [];

    $stats = ( is_array( $stats ) && isset( $stats['A'], $stats['B'] ) ) ? $stats : abtestkit_pt_stats_default();

    $impressions_a = isset( $stats['A']['impressions'] ) ? (int) $stats['A']['impressions'] : 0;
    $impressions_b = isset( $stats['B']['impressions'] ) ? (int) $stats['B']['impressions'] : 0;
    $clicks_a      = isset( $stats['A']['clicks'] ) ? (int) $stats['A']['clicks'] : 0;
    $clicks_b      = isset( $stats['B']['clicks'] ) ? (int) $stats['B']['clicks'] : 0;
    $purchases_a   = isset( $stats['A']['purchases'] ) ? (int) $stats['A']['purchases'] : 0;
    $purchases_b   = isset( $stats['B']['purchases'] ) ? (int) $stats['B']['purchases'] : 0;

    $total_impressions = $impressions_a + $impressions_b;
    $conversion_total  = ( $goal === 'purchase' )
        ? ( $purchases_a + $purchases_b )
        : ( $clicks_a + $clicks_b );

    if ( $test_id === '' ) {
        $add_check( 'test_config', 'broken', __( 'Test configuration is missing', 'abtestkit' ), __( 'This test does not have a valid internal ID.', 'abtestkit' ) );
    } else {
        $add_check( 'test_config', 'good', __( 'Test configuration found', 'abtestkit' ), __( 'The saved test settings can be loaded.', 'abtestkit' ) );
    }

    if ( $status === 'running' ) {
        $add_check( 'test_status', 'good', __( 'Test is running', 'abtestkit' ), __( 'Visitors can be assigned to Version A or Version B.', 'abtestkit' ) );
    } elseif ( in_array( $status, [ 'complete', 'winner' ], true ) ) {
        $add_check( 'test_status', 'good', __( 'Test is complete', 'abtestkit' ), __( 'This test has finished and its saved results are available.', 'abtestkit' ) );
    } elseif ( $status === 'draft' ) {
        $add_check( 'test_status', 'info', __( 'Test is still a draft', 'abtestkit' ), __( 'Start the test when you are ready to collect visitor data.', 'abtestkit' ) );
    } else {
        $add_check( 'test_status', 'info', __( 'Test is paused', 'abtestkit' ), __( 'Paused tests keep their setup and results, but do not assign new visitors.', 'abtestkit' ) );
    }

    if ( $status === 'paused' && ! empty( $test['auto_paused_broken'] ) ) {
        $add_check(
            'auto_paused_broken',
            'attention',
            __( 'Automatically paused after a broken health check', 'abtestkit' ),
            __( 'Fix the broken item, then resume the test when you are ready. Broken tests will be paused automatically.', 'abtestkit' )
        );
    }

    if ( ! empty( $preview_failure ) ) {
        $preview_failed_at = isset( $preview_failure['last_failed_at'] ) ? (int) $preview_failure['last_failed_at'] : 0;
        $preview_label     = isset( $preview_failure['label'] ) ? sanitize_text_field( (string) $preview_failure['label'] ) : '';
        $preview_context   = isset( $preview_failure['context'] ) ? sanitize_key( (string) $preview_failure['context'] ) : '';

        $description = __( 'An admin preview iframe recently timed out before the page finished loading. This does not pause the test, but it can indicate a theme, plugin, or local environment is blocking preview rendering.', 'abtestkit' );

        if ( $preview_failed_at > 0 ) {
            $description .= ' ' . sprintf(
                /* translators: %s is a date/time string for the last failed preview. */
                __( 'Last failed: %s.', 'abtestkit' ),
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $preview_failed_at )
            );
        }

        if ( $preview_label !== '' || $preview_context !== '' ) {
            $description .= ' ' . sprintf(
                /* translators: 1: preview label, 2: preview context key. */
                __( 'Preview: %1$s%2$s', 'abtestkit' ),
                $preview_label !== '' ? $preview_label : __( 'Unknown', 'abtestkit' ),
                $preview_context !== '' ? ' (' . $preview_context . ')' : ''
            );
        }

        $add_check(
            'preview_load',
            'attention',
            __( 'Preview recently failed to load', 'abtestkit' ),
            $description
        );
    }

    $control_post = $control_id > 0 ? get_post( $control_id ) : null;
    if ( ! ( $control_post instanceof WP_Post ) ) {
        $add_check( 'version_a', 'broken', __( 'Version A is missing', 'abtestkit' ), __( 'The original page, post, or product could not be found.', 'abtestkit' ) );
    } elseif ( in_array( (string) $control_post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
        $add_check( 'version_a', 'broken', __( 'Version A is not available', 'abtestkit' ), __( 'The original content exists, but it is not in a usable state.', 'abtestkit' ) );
    } else {
        $add_check( 'version_a', 'good', __( 'Version A exists', 'abtestkit' ), __( 'The original content for this test can be found.', 'abtestkit' ) );
    }

    if ( $kind === 'custom_css' ) {
        $custom_css_data = abtestkit_custom_css_data_for_test( $test );

        if ( empty( $custom_css_data['custom_css'] ) ) {
            $add_check( 'custom_css_saved', 'broken', __( 'Version B CSS is missing', 'abtestkit' ), __( 'Custom CSS tests need saved Version B CSS before they can show a visual difference.', 'abtestkit' ) );
        } else {
            $add_check( 'custom_css_saved', 'good', __( 'Version B CSS is saved', 'abtestkit' ), __( 'The Custom CSS override layer is saved for Version B visitors.', 'abtestkit' ) );
        }

        if ( empty( $custom_css_data['css_markers'] ) ) {
            $add_check( 'custom_css_markers', 'info', __( 'No B-only markers saved', 'abtestkit' ), __( 'This is fine if your CSS targets existing selectors directly.', 'abtestkit' ) );
        } else {
            $add_check( 'custom_css_markers', 'good', __( 'B-only markers are saved', 'abtestkit' ), __( 'Marker classes will be added to selected elements for Version B visitors only.', 'abtestkit' ) );
        }
    } elseif ( $kind === 'custom_html' ) {
        $custom_html_data = abtestkit_custom_html_data_for_test( $test );

        if ( empty( $custom_html_data['html_changes'] ) ) {
            $add_check( 'custom_html_saved', 'broken', __( 'Version B HTML is missing', 'abtestkit' ), __( 'Custom HTML tests need at least one saved selector, operation and HTML change before they can show a difference.', 'abtestkit' ) );
        } else {
            $add_check( 'custom_html_saved', 'good', __( 'Version B HTML is saved', 'abtestkit' ), __( 'The selected targets, operations and Version B HTML are saved.', 'abtestkit' ) );
        }

        if ( ! empty( $custom_html_data['html_changes'] ) ) {
            $runtime_status   = isset( $html_runtime_health['status'] ) ? sanitize_key( (string) $html_runtime_health['status'] ) : '';
            $runtime_matched  = isset( $html_runtime_health['matched'] ) ? max( 0, (int) $html_runtime_health['matched'] ) : 0;
            $runtime_missing  = isset( $html_runtime_health['missing'] ) ? max( 0, (int) $html_runtime_health['missing'] ) : 0;
            $runtime_invalid  = isset( $html_runtime_health['invalid'] ) ? max( 0, (int) $html_runtime_health['invalid'] ) : 0;
            $runtime_total    = isset( $html_runtime_health['total'] ) ? max( 0, (int) $html_runtime_health['total'] ) : 0;

            if ( empty( $html_runtime_health ) ) {
                $add_check(
                    'custom_html_runtime',
                    'info',
                    __( 'Awaiting a live selector check', 'abtestkit' ),
                    __( 'Aggregate selector health will appear after Version B is served on the live target page. Preview visits are not recorded.', 'abtestkit' )
                );
            } elseif ( $runtime_status === 'broken' ) {
                $add_check(
                    'custom_html_runtime',
                    'broken',
                    __( 'Live HTML targets are not reliable', 'abtestkit' ),
                    sprintf(
                        /* translators: 1: matched selector count, 2: total selector count, 3: missing selector count, 4: invalid selector count. */
                        __( 'The latest confirmed checks matched %1$d of %2$d saved targets. Missing: %3$d. Invalid: %4$d. The report contains counts only, never selector text or visitor data.', 'abtestkit' ),
                        $runtime_matched,
                        $runtime_total,
                        $runtime_missing,
                        $runtime_invalid
                    )
                );
            } elseif ( $runtime_status === 'attention' ) {
                $add_check(
                    'custom_html_runtime',
                    'attention',
                    __( 'Some live HTML targets need checking', 'abtestkit' ),
                    sprintf(
                        /* translators: 1: matched selector count, 2: total selector count, 3: missing selector count, 4: invalid selector count. */
                        __( 'The latest live check matched %1$d of %2$d saved targets. Missing: %3$d. Invalid: %4$d. Missing targets stay in Needs attention because conditional or device-specific content can legitimately be absent; repeatedly confirmed invalid selector syntax can pause the test.', 'abtestkit' ),
                        $runtime_matched,
                        $runtime_total,
                        $runtime_missing,
                        $runtime_invalid
                    )
                );
            } else {
                $add_check(
                    'custom_html_runtime',
                    'good',
                    __( 'Live HTML targets are matching', 'abtestkit' ),
                    sprintf(
                        /* translators: %d is the number of matched saved HTML targets. */
                        __( 'The latest Version B check matched all %d saved targets.', 'abtestkit' ),
                        $runtime_total
                    )
                );
            }
        }
    } else {
        $variant_post = $variant_id > 0 ? get_post( $variant_id ) : null;
        if ( ! ( $variant_post instanceof WP_Post ) ) {
        $add_check( 'version_b', 'broken', __( 'Version B is missing', 'abtestkit' ), __( 'The test cannot reliably show a variant because Version B could not be found.', 'abtestkit' ) );
    } elseif ( in_array( (string) $variant_post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
        $add_check( 'version_b', 'broken', __( 'Version B is not available', 'abtestkit' ), __( 'Version B exists, but it is not in a usable state.', 'abtestkit' ) );
    } else {
        $variant_is_shadow = function_exists( 'abtestkit_is_shadow_variant' )
            && abtestkit_is_shadow_variant( (int) $variant_id );

        $variant_shadow_of = $variant_is_shadow
            ? (int) get_post_meta( (int) $variant_id, '_abtestkit_shadow_of', true )
            : 0;

        $variant_is_linked_shadow = $variant_is_shadow
            && $control_id > 0
            && $variant_shadow_of === (int) $control_id;

        if ( $variant_is_linked_shadow ) {
            $add_check( 'version_b', 'good', __( 'Version B shadow exists', 'abtestkit' ), __( 'Version B is saved as a hidden shadow version linked to Version A.', 'abtestkit' ) );
        } elseif ( $variant_is_shadow ) {
            $add_check( 'version_b', 'attention', __( 'Version B shadow link may need checking', 'abtestkit' ), __( 'Version B is marked as a shadow version, but its link back to Version A could not be confirmed.', 'abtestkit' ) );
        } elseif ( $status === 'running' && ! in_array( (string) $variant_post->post_status, [ 'publish', 'private' ], true ) ) {
            $add_check( 'version_b', 'attention', __( 'Version B may need checking', 'abtestkit' ), __( 'Version B exists, but it is not a confirmed shadow version. Preview it before relying on this test.', 'abtestkit' ) );
        } else {
            $add_check( 'version_b', 'good', __( 'Version B exists', 'abtestkit' ), __( 'The variant content for this test can be found.', 'abtestkit' ) );
        }
    }

    }

    if ( $kind === 'product' && $variant_post instanceof WP_Post && function_exists( 'abtestkit_is_shadow_product' ) ) {
        if ( abtestkit_is_shadow_product( $variant_id ) ) {
            $add_check( 'product_shadow', 'good', __( 'Shadow product is linked', 'abtestkit' ), __( 'Version B is set up as a hidden WooCommerce shadow product.', 'abtestkit' ) );
        } else {
            $add_check( 'product_shadow', 'attention', __( 'Shadow product link may need checking', 'abtestkit' ), __( 'Version B exists, but it does not look like a linked shadow product.', 'abtestkit' ) );
        }
    }

    if ( in_array( $goal, [ 'click', 'clicks' ], true ) ) {
        if ( empty( $targets ) ) {
            $add_check( 'click_target', 'broken', __( 'Click target is missing', 'abtestkit' ), __( 'This click test does not have a saved click target.', 'abtestkit' ) );
        } else {
            $add_check( 'click_target', 'good', __( 'Click target is saved', 'abtestkit' ), __( 'The selected click target is stored for conversion tracking.', 'abtestkit' ) );
        }
    }

    if ( $goal === 'destination_url' ) {
        if ( empty( $targets ) ) {
            $add_check( 'destination_url', 'broken', __( 'Destination URL is missing', 'abtestkit' ), __( 'This destination URL test does not have a saved destination URL.', 'abtestkit' ) );
        } else {
            $add_check( 'destination_url', 'good', __( 'Destination URL is saved', 'abtestkit' ), __( 'The selected destination URL is stored for conversion tracking.', 'abtestkit' ) );
        }
    }

    if ( $goal === 'scroll_depth' ) {
        $scroll_depth = abtestkit_pt_scroll_depth_for_test( $test );
        $add_check(
            'scroll_depth',
            'good',
            __( 'Scroll depth is saved', 'abtestkit' ),
            sprintf(
                /* translators: %d is the scroll depth percentage. */
                __( 'Visitors count as converted when they reach %d%% of the page.', 'abtestkit' ),
                $scroll_depth
            )
        );
    }

    if (
        function_exists( 'abtestkit_events_table_has_column' )
        && abtestkit_events_table_has_column( 'time' )
        && abtestkit_events_table_has_column( 'ab_test_id' )
        && abtestkit_events_table_has_column( 'variant' )
        && abtestkit_events_table_has_column( 'event_type' )
    ) {
        $add_check( 'tracking_table', 'good', __( 'Tracking storage is ready', 'abtestkit' ), __( 'The event table has the columns needed for impressions and conversions.', 'abtestkit' ) );
    } else {
        $add_check( 'tracking_table', 'broken', __( 'Tracking storage needs repair', 'abtestkit' ), __( 'The event table is missing one or more required columns.', 'abtestkit' ) );
    }

    if ( $total_impressions > 0 ) {
        $add_check( 'impressions', 'good', __( 'Visitor data is recording', 'abtestkit' ), sprintf(
            /* translators: %d is the number of recorded impressions. */
            __( '%d total impressions have been recorded for this test.', 'abtestkit' ),
            $total_impressions
        ) );

        if ( $total_impressions >= 50 && ( $impressions_a === 0 || $impressions_b === 0 ) && $status === 'running' ) {
            $add_check( 'traffic_split', 'attention', __( 'Only one version has visitor data', 'abtestkit' ), __( 'The test has traffic, but one version has not received visitors yet. This can happen early, but it is worth checking if it continues.', 'abtestkit' ) );
        }
    } else {
        $age_seconds = ( $started_at > 0 ) ? max( 0, current_time( 'timestamp' ) - $started_at ) : 0;

        if ( $status === 'running' && $age_seconds >= ( 2 * DAY_IN_SECONDS ) ) {
            $add_check( 'impressions', 'attention', __( 'No visitor data yet', 'abtestkit' ), __( 'This test has been running for over a week, but no impressions have been recorded yet. Check the test URL and preview links if you expected traffic.', 'abtestkit' ) );
        } else {
            $add_check( 'impressions', 'info', __( 'Waiting for visitors', 'abtestkit' ), __( 'No impressions have been recorded yet.', 'abtestkit' ) );
        }
    }

    if ( $total_impressions > 0 ) {
        if ( $conversion_total > 0 ) {
            $add_check( 'conversions', 'good', __( 'Conversion data is recording', 'abtestkit' ), __( 'At least one conversion has been recorded for this test.', 'abtestkit' ) );
        } else {
            $conversion_attention_threshold = max( 500, $min_imps * 5 );

            if ( $total_impressions >= $conversion_attention_threshold ) {
                $add_check( 'conversions', 'attention', __( 'Conversion setup may need checking', 'abtestkit' ), __( 'This test has visitor data but no conversions yet. If you expected conversions by now, check that the goal is still present on the page.', 'abtestkit' ) );
            } else {
                $add_check( 'conversions', 'info', __( 'Waiting for conversion data', 'abtestkit' ), __( 'This test is recording visitors, but no conversions have been recorded yet. That can be normal while traffic is building.', 'abtestkit' ) );
            }
        }
    }

    if ( $http_count > 0 ) {
        $description = sprintf(
            /* translators: %d is the number of HTTP visits excluded from tracking. */
            __( '%d visit(s) were excluded because they came through an insecure HTTP version of the site.', 'abtestkit' ),
            $http_count
        );

        if ( $http_last !== '' ) {
            $description .= ' ' . sprintf(
                /* translators: %s is a date/time string for the last excluded HTTP visit. */
                __( 'Last seen: %s.', 'abtestkit' ),
                $http_last
            );
        }

        $add_check( 'http_tracking', 'attention', __( 'HTTP visits are being excluded', 'abtestkit' ), $description );
    }

    $label = [
        'good'      => __( 'Good', 'abtestkit' ),
        'attention' => __( 'Needs attention', 'abtestkit' ),
        'broken'    => __( 'Broken', 'abtestkit' ),
    ][ $overall ];

    $summary = [
        'good'      => __( 'Core setup looks ready. Keep an eye on the results as traffic builds.', 'abtestkit' ),
        'attention' => __( 'The test can run, but one or more items are worth checking.', 'abtestkit' ),
        'broken'    => __( 'One or more required parts of this test are missing or unavailable.', 'abtestkit' ),
    ][ $overall ];

    return [
        'status'  => $overall,
        'label'   => $label,
        'summary' => $summary,
        'checks'  => $checks,
    ];
}

function abtestkit_pt_timeline_days_back( string $range ) : int {
    switch ( $range ) {
        case 'month':
            return 365;
        case 'week':
            return 182;
        case 'day':
        default:
            return 45;
    }
}

function abtestkit_pt_flush_test_caches( string $test_id ) : void {
    $test_id = (string) $test_id;
    if ( $test_id === '' ) {
        return;
    }

    delete_transient( abtestkit_pt_stats_cache_key( $test_id ) );
    delete_transient( abtestkit_pt_performance_cache_key( $test_id, 'day' ) );
    delete_transient( abtestkit_pt_performance_cache_key( $test_id, 'week' ) );
    delete_transient( abtestkit_pt_performance_cache_key( $test_id, 'month' ) );
}

/**
 * Return lifetime HTTP-exclusion health context for multiple tests in one query.
 *
 * @param array $tests Page-test records.
 * @return array<string,array{http_excluded_count:int,http_excluded_last:string}>
 */
function abtestkit_pt_http_exclusions_bulk( array $tests ) : array {
    global $wpdb;

    $out      = [];
    $test_ids = [];

    foreach ( $tests as $test ) {
        $test_id = is_array( $test ) && isset( $test['id'] )
            ? abtestkit_sanitize_test_id( (string) $test['id'] )
            : '';

        if ( $test_id === '' ) {
            continue;
        }

        $test_ids[]     = $test_id;
        $out[ $test_id ] = [
            'http_excluded_count' => 0,
            'http_excluded_last'  => '',
        ];
    }

    $test_ids = array_values( array_unique( $test_ids ) );

    if (
        empty( $test_ids )
        || ! abtestkit_events_table_has_column( 'protocol' )
        || ! abtestkit_events_table_has_column( 'excluded_reason' )
    ) {
        return $out;
    }

    $table        = ABTESTKIT_EVENTS_TABLE;
    $placeholders = implode( ', ', array_fill( 0, count( $test_ids ), '%s' ) );
    $query_args   = array_merge( [ $table ], $test_ids );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results(
        /*
         * The fragment below contains generated %s tokens only. The argument
         * array supplies the table and one sanitized value for every token.
         */
        $wpdb->prepare(
            'SELECT ab_test_id, COUNT(*) AS total, MAX(`time`) AS last_seen ' .
            'FROM %i ' .
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'WHERE ab_test_id IN (' . $placeholders . ') ' .
            "AND event_type = 'protocol_warning' " .
            "AND protocol = 'http' " .
            "AND excluded_reason = 'http' " .
            'GROUP BY ab_test_id',
            $query_args
        ),
        ARRAY_A
    );

    foreach ( (array) $rows as $row ) {
        $test_id = isset( $row['ab_test_id'] ) ? (string) $row['ab_test_id'] : '';

        if ( $test_id === '' || ! isset( $out[ $test_id ] ) ) {
            continue;
        }

        $out[ $test_id ] = [
            'http_excluded_count' => isset( $row['total'] ) ? (int) $row['total'] : 0,
            'http_excluded_last'  => isset( $row['last_seen'] ) ? (string) $row['last_seen'] : '',
        ];
    }

    return $out;
}

function abtestkit_pt_stats_bulk( array $tests ) : array {
    global $wpdb;

    $out = [];

    if ( empty( $tests ) || ! is_array( $tests ) ) {
        return $out;
    }

    $live_test_ids = [];

    foreach ( $tests as $test ) {
        $test_id = isset( $test['id'] ) ? (string) $test['id'] : '';
        $status  = isset( $test['status'] ) ? (string) $test['status'] : 'paused';

        if ( $test_id === '' ) {
            continue;
        }

        if (
            in_array( $status, [ 'winner', 'complete' ], true )
            && isset( $test['final_stats'] )
            && is_array( $test['final_stats'] )
            && isset( $test['final_stats']['A'], $test['final_stats']['B'] )
        ) {
            $out[ $test_id ] = $test['final_stats'];
            continue;
        }

        $cached = get_transient( abtestkit_pt_stats_cache_key( $test_id ) );
        if ( is_array( $cached ) && isset( $cached['A'], $cached['B'] ) ) {
            $out[ $test_id ] = $cached;
            continue;
        }

        $live_test_ids[] = $test_id;
    }

    if ( empty( $live_test_ids ) ) {
        return $out;
    }

    $table         = ABTESTKIT_EVENTS_TABLE;
    $live_test_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    static function( $test_id ) {
                        return abtestkit_sanitize_test_id( (string) $test_id );
                    },
                    $live_test_ids
                )
            )
        )
    );

    if ( empty( $live_test_ids ) ) {
        return $out;
    }

    $live_test_id_placeholders = implode( ', ', array_fill( 0, count( $live_test_ids ), '%s' ) );
    $query_args                 = array_merge( [ $table ], $live_test_ids );

    if ( abtestkit_events_table_has_column( 'amount' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            /*
             * The fragment below contains generated %s tokens only. The argument
             * array supplies the table and one sanitized value for every token.
             */
            $wpdb->prepare(
                'SELECT ab_test_id, variant, event_type, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS revenue ' .
                'FROM %i ' .
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                'WHERE ab_test_id IN (' . $live_test_id_placeholders . ') ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY ab_test_id, variant, event_type',
                $query_args
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            /*
             * The fragment below contains generated %s tokens only. The argument
             * array supplies the table and one sanitized value for every token.
             */
            $wpdb->prepare(
                'SELECT ab_test_id, variant, event_type, COUNT(*) AS c, 0 AS revenue ' .
                'FROM %i ' .
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                'WHERE ab_test_id IN (' . $live_test_id_placeholders . ') ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY ab_test_id, variant, event_type',
                $query_args
            ),
            ARRAY_A
        );
    }

    foreach ( $live_test_ids as $test_id ) {
        $out[ $test_id ] = abtestkit_pt_stats_default();
    }

    foreach ( (array) $rows as $row ) {
        $row_test_id = isset( $row['ab_test_id'] ) ? (string) $row['ab_test_id'] : '';
        $variant     = isset( $row['variant'] ) ? (string) $row['variant'] : '';
        $type        = isset( $row['event_type'] ) ? (string) $row['event_type'] : '';
        $count       = isset( $row['c'] ) ? (int) $row['c'] : 0;
        $revenue     = isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0;

        if ( $row_test_id === '' || ! isset( $out[ $row_test_id ] ) ) {
            continue;
        }

        if ( ! in_array( $variant, [ 'A', 'B' ], true ) ) {
            continue;
        }

        if ( $type === 'impression' ) {
            $out[ $row_test_id ][ $variant ]['impressions'] = $count;
        } elseif ( $type === 'click' ) {
            $out[ $row_test_id ][ $variant ]['clicks'] = $count;
        } elseif ( $type === 'purchase' ) {
            $out[ $row_test_id ][ $variant ]['purchases'] = $count;
            $out[ $row_test_id ][ $variant ]['revenue']   = round( $revenue, 2 );
        }
    }

    foreach ( $live_test_ids as $test_id ) {
        foreach ( [ 'A', 'B' ] as $variant ) {
            $purchases = (int) $out[ $test_id ][ $variant ]['purchases'];
            $revenue   = (float) $out[ $test_id ][ $variant ]['revenue'];

            $out[ $test_id ][ $variant ]['revenue_per_customer'] = $purchases > 0
                ? round( $revenue / $purchases, 2 )
                : 0.0;
        }

        set_transient( abtestkit_pt_stats_cache_key( $test_id ), $out[ $test_id ], 5 * MINUTE_IN_SECONDS );
    }

    return $out;
}

function abtestkit_pt_stats( array $test ) : array {
    // If Winner/Complete and we have a snapshot, ALWAYS use it.
    $status = isset( $test['status'] ) ? (string) $test['status'] : 'paused';
    if ( in_array( $status, [ 'winner', 'complete' ], true )
        && isset( $test['final_stats'] )
        && is_array( $test['final_stats'] )
        && isset( $test['final_stats']['A'], $test['final_stats']['B'] )
    ) {
        return $test['final_stats'];
    }

    global $wpdb;

    $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;
    $test_id    = isset( $test['id'] ) ? (string) $test['id'] : '';

    if ( $control_id <= 0 || $test_id === '' ) {
        return abtestkit_pt_stats_default();
    }

    $cache_key = abtestkit_pt_stats_cache_key( $test_id );
    $cached    = get_transient( $cache_key );

    if ( is_array( $cached ) && isset( $cached['A'], $cached['B'] ) ) {
        return $cached;
    }

    $table = ABTESTKIT_EVENTS_TABLE;

    if ( abtestkit_events_table_has_column( 'amount' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT variant, event_type, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS revenue ' .
                'FROM %i ' .
                'WHERE ab_test_id = %s ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY variant, event_type',
                $table,
                $test_id
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT variant, event_type, COUNT(*) AS c, 0 AS revenue ' .
                'FROM %i ' .
                'WHERE ab_test_id = %s ' .
                "AND event_type IN ('impression','click','purchase') " .
                'GROUP BY variant, event_type',
                $table,
                $test_id
            ),
            ARRAY_A
        );
    }

    $out = abtestkit_pt_stats_default();

    foreach ( (array) $rows as $r ) {
        $v       = isset( $r['variant'] ) ? (string) $r['variant'] : '';
        $t       = isset( $r['event_type'] ) ? (string) $r['event_type'] : '';
        $c       = isset( $r['c'] ) ? (int) $r['c'] : 0;
        $revenue = isset( $r['revenue'] ) ? (float) $r['revenue'] : 0.0;

        if ( ! isset( $out[ $v ] ) ) {
            continue;
        }

        if ( $t === 'purchase' ) {
            $out[ $v ]['purchases'] += $c;
            $out[ $v ]['revenue']   += $revenue;
        } elseif ( isset( $out[ $v ][ $t . 's' ] ) ) {
            $out[ $v ][ $t . 's' ] += $c;
        }
    }

    foreach ( [ 'A', 'B' ] as $variant ) {
        $purchases = (int) $out[ $variant ]['purchases'];
        $revenue   = (float) $out[ $variant ]['revenue'];

        $out[ $variant ]['revenue'] = round( $revenue, 2 );
        $out[ $variant ]['revenue_per_customer'] = $purchases > 0 ? round( $revenue / $purchases, 2 ) : 0.0;
    }

    set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );

    return $out;
}

/**
 * Dashboard helper: ask the existing /evaluate logic who is winning.
 * Returns 'A', 'B', or '' (no winner yet).
 */
/**
 * Dashboard helper: return the winner for a PT test.
 * If we already locked a winner, use that; otherwise fall back to /evaluate.
 */
function abtestkit_pt_winner_for_dashboard( array $test ) : string {
    // If we already locked a winner, always show it.
    $locked = isset( $test['winner'] ) ? sanitize_text_field( (string) $test['winner'] ) : '';
    if ( in_array( $locked, [ 'A', 'B' ], true ) ) {
        return $locked;
    }

    // IMPORTANT: do not evaluate paused/draft/complete tests.
    // This prevents paused/draft tests from appearing as "Winner" in the dashboard UI.
    $status = isset( $test['status'] ) ? (string) $test['status'] : 'paused';
    if ( $status !== 'running' ) {
        return '';
    }

    $req = new WP_REST_Request( 'GET', '/abtestkit/v1/evaluate' );
    $req->set_param( 'abTestId', (string) ( $test['id'] ?? '' ) );
    $req->set_param( 'post_id',  (int) ( $test['control_id'] ?? 0 ) );

    $res  = abtestkit_handle_evaluate( $req );
    $data = ( $res instanceof WP_REST_Response ) ? $res->get_data() : (array) $res;

    $winner = isset( $data['winner'] ) ? sanitize_text_field( (string) $data['winner'] ) : '';
    return in_array( $winner, [ 'A', 'B' ], true ) ? $winner : '';
}

/**
 * When the Bayesian evaluator declares a winner, lock it in and pause the test.
 * (This is the "automatic decision making" moment.)
 */
function abtestkit_pt_maybe_lock_winner( string $test_id ) : void {
    if ( strpos( $test_id, 'pt-' ) !== 0 ) {
        return;
    }

    $test = abtestkit_pt_get( $test_id );
    if ( ! is_array( $test ) ) {
        return;
    }

    // Only lock while running; only lock once.
    if ( ( $test['status'] ?? 'paused' ) !== 'running' ) {
        return;
    }

    // Manual mode: never auto-lock a winner.
    // Guard both decision_mode and decision_rule (older/newer tests may set either).
    if ( ( $test['decision_mode'] ?? 'auto' ) === 'manual' || ( $test['decision_rule'] ?? '' ) === 'manual' ) {
        return;
    }

    $already = isset( $test['winner'] ) ? sanitize_text_field( (string) $test['winner'] ) : '';
    if ( in_array( $already, [ 'A', 'B' ], true ) ) {
        return;
    }

    $control_id = (int) ( $test['control_id'] ?? 0 );
    if ( $control_id <= 0 ) {
        return;
    }

    $req = new WP_REST_Request( 'GET', '/abtestkit/v1/evaluate' );
    $req->set_param( 'abTestId', $test_id );
    $req->set_param( 'post_id',  $control_id );

    $res  = abtestkit_handle_evaluate( $req );
    $data = ( $res instanceof WP_REST_Response ) ? $res->get_data() : (array) $res;

    $winner = isset( $data['winner'] ) ? sanitize_text_field( (string) $data['winner'] ) : '';
    if ( ! in_array( $winner, [ 'A', 'B' ], true ) ) {
        return;
    }

    $test['winner']    = $winner;
    $test['winner_at'] = time();
    $test['status']    = 'winner'; // Paused automatically, but with an explicit Winner state.

    abtestkit_pt_snapshot_completed_test( $test );
    abtestkit_pt_put( $test );
    delete_transient( abtestkit_pt_stats_cache_key( (string) $test['id'] ) );
}

// Dashboard Render //
function abtestkit_render_page_tests_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap abtestkit-dashboard">
        <h1><?php esc_html_e( 'abtestkit Dashboard', 'abtestkit' ); ?></h1>
        <div id="abtestkit-dashboard-root"></div>
    </div>
    <?php
}



function abtestkit_render_pt_wizard_page() {
    if ( ! current_user_can('manage_options') ) return;
    echo '<div class="wrap"><h1>' . esc_html__( 'Create A/B Test', 'abtestkit' ) . '</h1><div id="abtestkit-pt-wizard-root"></div></div>';
}

function abtestkit_render_test_performance_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    echo '<div class="wrap abtestkit-admin-page abtestkit-test-performance">';
    echo '<div id="abtestkit-test-performance-root"></div>';
    echo '</div>';
}

// Enqueue wizard assets on the Create A/B Test page
add_action( 'admin_enqueue_scripts', function( $hook ) {
    // Much simpler: just check the page slug in the URL.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( empty( $_GET['page'] ) || $_GET['page'] !== 'abtestkit-pt-wizard' ) {
        return;
    }

    // Telemetry (opt-in): first time opening Create Test wizard (and first time after first test)
    if ( function_exists( 'abtestkit_telemetry_track_pt_wizard_opened' ) ) {
        abtestkit_telemetry_track_pt_wizard_opened();
    }

    // Load the WordPress editor stack so we can embed classic editors in the wizard
    if ( function_exists( 'wp_enqueue_editor' ) ) {
        wp_enqueue_editor();
    } else {
        wp_enqueue_script( 'wp-editor' );
    }

    // React + components
    wp_enqueue_script( 'wp-element' );
    wp_enqueue_script( 'wp-components' );
    wp_enqueue_script( 'wp-api-fetch' );
    wp_enqueue_style( 'wp-components' );

    // Needed for wp.media() (image/gallery pickers in the Page Test wizard)
    wp_enqueue_media();

    // The actual wizard app
    wp_enqueue_script(
        'abtestkit-pt-wizard',
        plugins_url( 'assets/js/pt-wizard.js', __FILE__ ),
        [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-editor' ],
        file_exists( plugin_dir_path( __FILE__ ) . 'assets/js/pt-wizard.js' )
            ? filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/pt-wizard.js' )
            : ( defined( 'ABTESTKIT_VERSION' ) ? ABTESTKIT_VERSION : '1.5.1' ),
        true
    );

wp_localize_script( 'abtestkit-pt-wizard', 'abtestkit_PT', [
        'nonce'        => wp_create_nonce( 'wp_rest' ),
        'rest'         => esc_url_raw( rest_url( 'abtestkit/v1' ) ),
        'dashboard'    => admin_url( 'admin.php?page=abtestkit-dashboard' ),
        'editBase'     => admin_url( 'post.php?post=' ),
        'viewBase'     => home_url( '/?p=' ), // legacy fallback
        'postViewBase' => home_url( '/?p=' ),
        'pageViewBase' => home_url( '/?page_id=' ),
    ] );
} );

// Enqueue JS admin list guard
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'edit.php' ], true ) ) {
        return;
    }

    wp_enqueue_script(
        'abtestkit-admin-list-guard',
        plugins_url( 'assets/js/admin-list-guard.js', __FILE__ ),
        [ 'jquery' ],
        ( defined( 'ABTESTKIT_VERSION' ) ? ABTESTKIT_VERSION : '1.5.1' ),
        true
    );

    // Build a map of post_id => status for running tests only
    $map = [];

    foreach ( abtestkit_pt_all() as $t ) {
        if ( empty( $t['status'] ) || $t['status'] !== 'running' ) {
            continue;
        }

        if ( ! empty( $t['control_id'] ) ) {
            $map[ (int) $t['control_id'] ] = 'running';
        }

        if ( ! empty( $t['variant_id'] ) ) {
            $map[ (int) $t['variant_id'] ] = 'running';
        }
    }

    wp_localize_script(
        'abtestkit-admin-list-guard',
        'ABTESTKIT_RUNNING_MAP',
        $map
    );
});

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'plugins.php' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'abtestkit-delete-reason',
        plugins_url( 'assets/js/delete-reason.js', __FILE__ ),
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/delete-reason.js' ),
        true
    );

    wp_localize_script(
        'abtestkit-delete-reason',
        'ABTESTKIT_DELETE_REASON',
        [
            'rest'         => esc_url_raw( rest_url( 'abtestkit/v1/delete-reason' ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'pluginBase'   => plugin_basename( __FILE__ ),
            'title'          => __( 'Before you deactivate abtestkit', 'abtestkit' ),
            'intro'          => __( 'One quick answer helps us fix the right things.', 'abtestkit' ),
            'stepOneLabel'   => __( 'What made you deactivate?', 'abtestkit' ),
            'stepTwoLabel'   => __( 'What happened?', 'abtestkit' ),
            'areaLabel'      => __( 'Where did this happen?', 'abtestkit' ),
            'detailLabel'    => __( 'What happened?', 'abtestkit' ),
            'detailHelp'     => __( 'One sentence is enough. What were you trying to do, and what went wrong?', 'abtestkit' ),
            'snapshotText'   => '',
            'continueText'   => __( 'Continue', 'abtestkit' ),
            'backText'       => __( 'Back', 'abtestkit' ),
            'confirmText'    => __( 'Send feedback and deactivate', 'abtestkit' ),
            'skipText'       => __( 'Skip feedback and deactivate', 'abtestkit' ),
            'cancelText'     => __( 'Keep plugin active', 'abtestkit' ),
            'requiredText'   => __( 'Please choose the closest reason before continuing.', 'abtestkit' ),
            'placeholder'    => __( 'Example: I wanted to test a form submission after someone clicks a pricing button.', 'abtestkit' ),
            'reasons'        => [
                [
                    'value'         => 'frontend_woocommerce_product_issues',
                    'title'         => __( 'Frontend WooCommerce Product issues', 'abtestkit' ),
                    'desc'          => __( 'Products, cart or checkout did not behave correctly.', 'abtestkit' ),
                    'followupLabel' => __( 'Which WooCommerce/product area was affected?', 'abtestkit' ),
                    'tags'          => [
                        [ 'value' => 'simple_product',       'label' => __( 'Simple product', 'abtestkit' ) ],
                        [ 'value' => 'variable_product',     'label' => __( 'Variable product', 'abtestkit' ) ],
                        [ 'value' => 'subscription_product', 'label' => __( 'Subscription product', 'abtestkit' ) ],
                        [ 'value' => 'price_variations',     'label' => __( 'Prices/variations', 'abtestkit' ) ],
                        [ 'value' => 'images_gallery',       'label' => __( 'Images/gallery', 'abtestkit' ) ],
                        [ 'value' => 'cart_checkout',        'label' => __( 'Cart/checkout', 'abtestkit' ) ],
                        [ 'value' => 'product_preview',      'label' => __( 'Product preview', 'abtestkit' ) ],
                    ],
                ],
                [
                    'value'         => 'frontend_pages_posts_issues',
                    'title'         => __( 'Frontend Pages/Post issues', 'abtestkit' ),
                    'desc'          => __( 'Click targets, form submissions or frontend display did not behave correctly.', 'abtestkit' ),
                    'followupLabel' => __( 'Which page/post area was affected?', 'abtestkit' ),
                    'tags'          => [
                        [ 'value' => 'page_test',        'label' => __( 'Page test', 'abtestkit' ) ],
                        [ 'value' => 'post_test',        'label' => __( 'Post test', 'abtestkit' ) ],
                        [ 'value' => 'test_not_showing', 'label' => __( 'Test did not show', 'abtestkit' ) ],
                        [ 'value' => 'wrong_variant',    'label' => __( 'Wrong variant shown', 'abtestkit' ) ],
                        [ 'value' => 'preview',          'label' => __( 'Preview looked wrong', 'abtestkit' ) ],
                        [ 'value' => 'click_targeting',  'label' => __( 'Click targeting', 'abtestkit' ) ],
                        [ 'value' => 'builder_template', 'label' => __( 'Builder/template issue', 'abtestkit' ) ],
                    ],
                ],
                [
                    'value'         => 'backend_wordpress_issues',
                    'title'         => __( 'Backend issues in WordPress', 'abtestkit' ),
                    'desc'          => __( 'The WordPress admin, setup wizard, dashboard, reports, saving, loading, or applying a winner caused problems.', 'abtestkit' ),
                    'followupLabel' => __( 'Which WordPress admin area was affected?', 'abtestkit' ),
                    'tags'          => [
                        [ 'value' => 'create_test_wizard', 'label' => __( 'Create Test wizard', 'abtestkit' ) ],
                        [ 'value' => 'build_version_b',    'label' => __( 'Building Version B', 'abtestkit' ) ],
                        [ 'value' => 'dashboard_reports',  'label' => __( 'Dashboard/reports', 'abtestkit' ) ],
                        [ 'value' => 'performance_page',   'label' => __( 'Performance page', 'abtestkit' ) ],
                        [ 'value' => 'saving_changes',     'label' => __( 'Saving changes', 'abtestkit' ) ],
                        [ 'value' => 'admin_slow',         'label' => __( 'Admin felt slow', 'abtestkit' ) ],
                        [ 'value' => 'server_error',       'label' => __( 'Error/white screen', 'abtestkit' ) ],
                    ],
                ],
                [
                    'value'         => 'missing_feature',
                    'title'         => __( 'Missing feature', 'abtestkit' ),
                    'desc'          => __( 'abtestkit did not yet support the testing, workflow, report, or targeting option I needed.', 'abtestkit' ),
                    'followupLabel' => __( 'What were you hoping abtestkit could do?', 'abtestkit' ),
                    'tags'          => [
                        [ 'value' => 'heatmaps_click_maps',        'label' => __( 'Heatmaps / click maps', 'abtestkit' ) ],
                        [ 'value' => 'ai_test_suggestions',        'label' => __( 'AI test suggestions', 'abtestkit' ) ],
                        [ 'value' => 'css_javascript_tests',       'label' => __( 'CSS / JavaScript tests', 'abtestkit' ) ],
                        [ 'value' => 'form_lead_tracking',         'label' => __( 'Form or lead tracking', 'abtestkit' ) ],
                        [ 'value' => 'split_url_tests',            'label' => __( 'URL / redirect tests', 'abtestkit' ) ],
                        [ 'value' => 'woocommerce_options',        'label' => __( 'More WooCommerce options', 'abtestkit' ) ],
                        [ 'value' => 'better_reports',             'label' => __( 'Better reports', 'abtestkit' ) ],
                        [ 'value' => 'advanced_targeting_rules',   'label' => __( 'Advanced targeting rules', 'abtestkit' ) ],
                        [ 'value' => 'multi_variant_tests',        'label' => __( 'A/B/C or multi-variant tests', 'abtestkit' ) ],
                        [ 'value' => 'something_else',             'label' => __( 'Something else', 'abtestkit' ) ],
                    ],
                ],
            ],
            'areas'          => [
                [ 'value' => 'not_started',         'label' => __( 'Did not get started', 'abtestkit' ) ],
                [ 'value' => 'page_test',           'label' => __( 'Page/post test', 'abtestkit' ) ],
                [ 'value' => 'product_test',        'label' => __( 'Product test', 'abtestkit' ) ],
                [ 'value' => 'woocommerce_revenue', 'label' => __( 'WooCommerce revenue tracking', 'abtestkit' ) ],
                [ 'value' => 'click_tracking',      'label' => __( 'Click tracking', 'abtestkit' ) ],
                [ 'value' => 'preview_version_b',   'label' => __( 'Version B preview/editing', 'abtestkit' ) ],
                [ 'value' => 'dashboard_reports',   'label' => __( 'Dashboard/reports', 'abtestkit' ) ],
                [ 'value' => 'onboarding',          'label' => __( 'Onboarding/setup', 'abtestkit' ) ],
            ],
        ]
    );
} );

/**
 * Get defensive currency settings for revenue reporting.
 *
 * Revenue rows currently store amounts without a per-order currency, so the
 * performance screen uses the site's current WooCommerce reporting currency.
 * Keep the saved WooCommerce options useful when the plugin is temporarily
 * inactive, then preserve the historical GBP fallback for non-WooCommerce
 * installs.
 *
 * @return array{code:string,decimals:int}
 */
function abtestkit_reporting_currency_settings(): array {
    $currency_code = function_exists( 'get_woocommerce_currency' )
        ? (string) get_woocommerce_currency()
        : (string) get_option( 'woocommerce_currency', 'GBP' );

    $currency_code = strtoupper( sanitize_text_field( $currency_code ) );
    $currency_code = preg_replace( '/[^A-Z0-9_-]/', '', $currency_code );
    $currency_code = is_string( $currency_code ) ? substr( $currency_code, 0, 12 ) : '';

    if ( $currency_code === '' ) {
        $currency_code = 'GBP';
    }

    $decimals = function_exists( 'wc_get_price_decimals' )
        ? (int) wc_get_price_decimals()
        : (int) get_option( 'woocommerce_price_num_decimals', 2 );

    return [
        'code'     => $currency_code,
        'decimals' => max( 0, min( 20, $decimals ) ),
    ];
}

// Enqueue dashboard/performance admin apps
add_action( 'admin_enqueue_scripts', function( $hook ) {
    $is_dashboard_page = (
        $hook === 'toplevel_page_abtestkit-dashboard'
        || $hook === 'abtestkit-dashboard_page_abtestkit-dashboard'
    );

    $is_performance_page = ( $hook === 'admin_page_abtestkit-test-performance' );

    if ( ! $is_dashboard_page && ! $is_performance_page ) {
        return;
    }

    // Core deps from WordPress
    wp_enqueue_script( 'wp-element' );
    wp_enqueue_script( 'wp-components' );
    wp_enqueue_script( 'wp-api-fetch' );
    wp_enqueue_style( 'wp-components' );

    if ( $is_dashboard_page ) {
        wp_enqueue_script(
            'abtestkit-dashboard-app',
            plugins_url( 'assets/js/dashboard.js', __FILE__ ),
            [ 'wp-element', 'wp-components', 'wp-api-fetch' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/dashboard.js' ),
            true
        );

        $conflict_banner = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['error'] ) && $_GET['error'] === 'conflict_running' ) {
            $conflicts = [];
            if ( ! empty( $_GET['conflicts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $conflicts = array_filter(
                    array_map(
                        'sanitize_text_field',
                        explode( ',', (string) wp_unslash( $_GET['conflicts'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    )
                );
            }

            $base = __( 'This page is already in a running test, or a reusable section test is causing a conflict.', 'abtestkit' );
            if ( ! empty( $conflicts ) ) {
                $base .= ' ' . sprintf(
                    /* translators: %s is a comma-separated list of conflicting test IDs. */
                    __( 'Conflicting test ID(s): %s.', 'abtestkit' ),
                    implode( ', ', $conflicts )
                );
            }
            $conflict_banner = $base;
        }

        wp_localize_script( 'abtestkit-dashboard-app', 'abtestkitDashboard', [
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'rest'           => esc_url_raw( rest_url( 'abtestkit/v1' ) ),
            'createUrl'      => admin_url( 'admin.php?page=abtestkit-pt-wizard' ),
            'adminAction'    => admin_url( 'admin-post.php?action=abtestkit_pt_action' ),
            'adminNonce'     => wp_create_nonce( 'abtestkit_pt_action' ),
            'conflictBanner' => $conflict_banner,
        ] );
    }

    if ( $is_performance_page ) {
        wp_enqueue_script(
            'abtestkit-test-performance',
            plugins_url( 'assets/js/test-performance.js', __FILE__ ),
            [ 'wp-element', 'wp-components', 'wp-api-fetch' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/test-performance.js' ),
            true
        );

        $performance_test_id = '';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin page context for this screen.
        if ( isset( $_GET['test_id'] ) ) {
            $performance_test_id = sanitize_text_field( wp_unslash( $_GET['test_id'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $currency_settings = abtestkit_reporting_currency_settings();

        wp_localize_script( 'abtestkit-test-performance', 'abtestkitTestPerformance', [
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'rest'             => esc_url_raw( rest_url( 'abtestkit/v1' ) ),
            'testId'           => $performance_test_id,
            'dashboardUrl'     => admin_url( 'admin.php?page=abtestkit-dashboard' ),
            'adminAction'      => admin_url( 'admin-post.php?action=abtestkit_pt_action' ),
            'adminNonce'       => wp_create_nonce( 'abtestkit_pt_action' ),
            'currencyCode'     => $currency_settings['code'],
            'currencyDecimals' => $currency_settings['decimals'],
        ] );
    }
} );

function abtestkit_enqueue_admin_assets( $hook ) {
    // Only load on your AB Test Kit wizard page or post type page
    if ( strpos( $hook, 'abtestkit' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'abtestkit-admin',
        plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/admin.css' )
    );
}
add_action( 'admin_enqueue_scripts', 'abtestkit_enqueue_admin_assets' );


// ───────────────────────────────────────────────────────────
// Page/Post draft shadow helpers
// Keep these above the frontend assignment hook because Version B
// page/post shadow rendering is activated during template_redirect.
// ───────────────────────────────────────────────────────────

if ( ! function_exists( 'abtestkit_pt_page_shadow_ctx_get' ) ) {
    function abtestkit_pt_page_shadow_ctx_get() : array {
        $ctx = $GLOBALS['abtestkit_pt_page_shadow_ctx'] ?? null;
        return is_array( $ctx ) ? $ctx : [];
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_ctx_set' ) ) {
    function abtestkit_pt_page_shadow_ctx_set( int $control_id, int $shadow_id, array $test ) : void {
        $GLOBALS['abtestkit_pt_page_shadow_ctx'] = [
            'control_id' => $control_id,
            'shadow_id'  => $shadow_id,
            'test_id'    => isset( $test['id'] ) ? (string) $test['id'] : '',
        ];
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_get_post_metadata' ) ) {
    function abtestkit_pt_page_shadow_get_post_metadata( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $value;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $value;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];
        $pid        = (int) $object_id;

        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id || $pid !== $control_id ) {
            return $value;
        }

        static $guard = false;
        if ( $guard ) {
            return $value;
        }

        // Never shadow these housekeeping/meta relationship keys.
        $protected = [
            '_abtestkit_shadow',
            '_abtestkit_shadow_of',
            '_abtestkit_variant_in_use',
            '_abtestkit_variant_of',
            '_abtestkit_variant_test_id',
        ];

        if ( ! is_string( $meta_key ) || $meta_key === '' ) {
            $guard = true;
            $shadow_all  = get_post_meta( $shadow_id );
            $control_all = get_post_meta( $control_id );
            $guard = false;

            if ( ! is_array( $shadow_all ) ) {
                return $value;
            }

            foreach ( $protected as $k ) {
                if ( isset( $control_all[ $k ] ) ) {
                    $shadow_all[ $k ] = $control_all[ $k ];
                } else {
                    unset( $shadow_all[ $k ] );
                }
            }

            return $shadow_all;
        }

        if ( in_array( $meta_key, $protected, true ) ) {
            return $value;
        }

        $guard = true;
        $shadow_val = get_post_meta( $shadow_id, $meta_key, $single );
        $guard = false;

        if ( $single ) {
            if ( $shadow_val === '' || $shadow_val === null ) {
                return $value;
            }
            return [ $shadow_val ];
        }

        return ( is_array( $shadow_val ) && ! empty( $shadow_val ) ) ? $shadow_val : $value;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_acf_pre_load_post_id' ) ) {
    function abtestkit_pt_page_shadow_acf_pre_load_post_id( $null, $post_id ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $null;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $null;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];

        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id ) {
            return $null;
        }

        $pid = 0;

        if ( is_numeric( $post_id ) ) {
            $pid = (int) $post_id;
        } elseif ( is_string( $post_id ) && preg_match( '/_(\d+)$/', $post_id, $m ) ) {
            $pid = (int) $m[1];
        }

        if ( $pid !== $control_id ) {
            return $null;
        }

        return $shadow_id;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_the_title' ) ) {
    function abtestkit_pt_page_shadow_the_title( $title, $post_id ) {
        if ( is_admin() ) {
            return $title;
        }

        if ( ! is_singular( [ 'page', 'post' ] ) ) {
            return $title;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $title;
        }

        if ( (int) $post_id !== (int) $ctx['control_id'] ) {
            return $title;
        }

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_title ) && $shadow_post->post_title !== '' ) {
            return $shadow_post->post_title;
        }

        return $title;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_get_the_excerpt' ) ) {
    function abtestkit_pt_page_shadow_get_the_excerpt( $excerpt, $post ) {
        if ( is_admin() ) {
            return $excerpt;
        }

        if ( ! is_singular( [ 'page', 'post' ] ) ) {
            return $excerpt;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $excerpt;
        }

        $pid = 0;
        if ( $post instanceof WP_Post ) {
            $pid = (int) $post->ID;
        } elseif ( is_numeric( $post ) ) {
            $pid = (int) $post;
        } else {
            $pid = (int) get_the_ID();
        }

        if ( $pid !== (int) $ctx['control_id'] ) {
            return $excerpt;
        }

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_excerpt ) && $shadow_post->post_excerpt !== '' ) {
            return $shadow_post->post_excerpt;
        }

        return $excerpt;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_the_content' ) ) {
    function abtestkit_pt_page_shadow_the_content( $content ) {
        if ( is_admin() ) {
            return $content;
        }

        if ( ! is_singular( [ 'page', 'post' ] ) ) {
            return $content;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $content;
        }

        $current_id = (int) get_the_ID();
        if ( $current_id !== (int) $ctx['control_id'] ) {
            return $content;
        }

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_content ) ) {
            return $shadow_post->post_content;
        }

        return $content;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_activate' ) ) {
    function abtestkit_pt_page_shadow_activate() : void {
        static $did = false;

        if ( $did ) {
            return;
        }

        if ( is_admin() || is_feed() || is_embed() ) {
            return;
        }

        if ( ! is_singular( [ 'page', 'post' ] ) ) {
            return;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return;
        }

        $did = true;

        add_filter( 'get_post_metadata', 'abtestkit_pt_page_shadow_get_post_metadata', 9999, 4 );
        add_filter( 'acf/pre_load_post_id', 'abtestkit_pt_page_shadow_acf_pre_load_post_id', 9999, 2 );
        add_filter( 'the_title', 'abtestkit_pt_page_shadow_the_title', 9999, 2 );
        add_filter( 'get_the_excerpt', 'abtestkit_pt_page_shadow_get_the_excerpt', 9999, 2 );

        // Priority 1 lets normal WP content filters still render blocks/shortcodes afterwards.
        add_filter( 'the_content', 'abtestkit_pt_page_shadow_the_content', 1 );
    }
}


// ── Assignment + redirect + server-side impression logging ───────────────────
add_action( 'template_redirect', function () {
    if ( abtestkit_is_custom_css_picker_preview_request() ) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $preview_flag  = isset( $_GET['abtestkit_preview'] )
        ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_preview'] ) )
        : '';

    $raw_force = isset( $_GET['abtestkit_force'] )
        ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_force'] ) ) )
        : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $is_preview    = ( $preview_flag === '1' );
    $force_variant = ( $is_preview && in_array( $raw_force, [ 'A', 'B' ], true ) ) ? $raw_force : '';

    // Exempt viewers (admins etc): never enter normal live assignment/tracking.
    // Explicit forced previews are allowed so the wizard/dashboard can preview Version A/B.
    if ( $force_variant === '' && abtestkit_is_exempt_viewer() ) {
        return;
    }

    // No tracking in admin, feeds, embeds, or non-singular views.
    if ( is_admin() || is_feed() || is_embed() ) {
        return;
    }

    if ( ! is_singular() ) {
        return;
    }

    $post_id   = (int) get_queried_object_id();
    $post_type = get_post_type( $post_id );

    if ( ! $post_id || ! $post_type ) {
        return;
    }

    // See if this page/product belongs to a Page Test.
    // Forced previews must be able to resolve paused/complete tests too.
    if ( $force_variant !== '' ) {
        [ $test, $role ] = abtestkit_pt_find_by_post_any_status( $post_id );
    } else {
        [ $test, $role ] = abtestkit_pt_find_by_post( $post_id );
    }

    if ( ! $test ) {
        return;
    }

    // Product tests keep a single URL and use "virtual B" overrides instead of redirects.
    $is_product_test = ( $post_type === 'product' && ( $test['kind'] ?? '' ) === 'product' );

    $cookie_name = 'abtestkit_pt_' . (string) $test['id'];
    $ttl         = max( 1, (int) ( $test['cookie_ttl_days'] ?? 30 ) );
    $split       = max( 0, min( 100, (int) ( $test['split'] ?? 50 ) ) );

    /**
     * Running full-page tests neve cache.
     * Otherwise a cached 302 or cached HTML page can pin everyone to one variant.
     */
    // These are standard interoperability constants recognised by major caching/minification plugins.
    // They must use their canonical names and must not be plugin-prefixed.
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTCACHEDB' ) ) {
        define( 'DONOTCACHEDB', true );
    }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
        define( 'DONOTCACHEOBJECT', true );
    }
    if ( ! defined( 'DONOTMINIFY' ) ) {
        define( 'DONOTMINIFY', true );
    }
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

    if ( ! headers_sent() ) {
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'Pragma: no-cache', true );
        header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
        header( 'Surrogate-Control: no-store', true );
        header( 'Vary: Cookie', false );
    }

    /**
     * HTTP must never:
     * - assign a live variant
     * - bounce into the HTTPS test flow
     * - write an impression/click row
     *
     * Force control for this request and stop here.
     */
    if ( ! abtestkit_request_is_real_https() ) {
        $GLOBALS['abtestkit_current_pt_assignment'] = [
            'test'      => $test,
            'variant'   => 'A',
            'role'      => $role,
            'post_id'   => $post_id,
            'protocol'  => 'http',
            'no_track'  => true,
            'forced_a'  => true,
        ];

        if ( ! $is_product_test && $role === 'variant' && ! $is_preview ) {
            $control_http_url = set_url_scheme( get_permalink( (int) $test['control_id'] ), 'http' );
            wp_safe_redirect( $control_http_url, 302 );
            exit;
        }

        return;
    }

    /**
     * HTTPS only:
     * if someone lands directly on the Version B URL for a PAGE/POST test,
     * route them via the control URL so assignment happens first.
     */
    if ( ! $is_product_test && $role === 'variant' && ! $is_preview ) {
        $assigned_raw = '';

        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            $assigned_raw = wp_unslash( $_COOKIE[ $cookie_name ] );
        }

        $assigned = ( '' !== $assigned_raw ) ? sanitize_text_field( $assigned_raw ) : '';

        if ( 'A' !== $assigned && 'B' !== $assigned ) {
            wp_safe_redirect( get_permalink( (int) $test['control_id'] ), 302 );
            exit;
        }
    }

    // Exempt viewers (admins etc): always see Version A, never counted,
    // UNLESS this is an explicit preview force from the wizard/dashboard.
    if ( $force_variant === '' && abtestkit_is_exempt_viewer() ) {
        if ( $role === 'variant' && ! $is_product_test ) {
            wp_safe_redirect( get_permalink( (int) $test['control_id'] ), 302 );
            exit;
        }
        return;
    }

    // Assignment: explicit preview force → cookie → else random by split.
    $assigned = '';

    if ( $force_variant !== '' ) {
        $assigned = $force_variant;
    } else {
        $assigned = abtestkit_safe_get_cookie_value( $cookie_name );
    }

    // Only accept valid values; otherwise assign and persist it safely.
    if ( 'A' !== $assigned && 'B' !== $assigned ) {
        $assigned = ( wp_rand( 1, 100 ) <= (int) $split ) ? 'B' : 'A';
        abtestkit_safe_set_cookie(
            $cookie_name,
            $assigned,
            time() + ( (int) $ttl ) * DAY_IN_SECONDS
        );

        // Keep this same request aligned immediately.
        $_COOKIE[ $cookie_name ] = $assigned;
    }

    // Expose current assignment globally so other hooks (Woo overrides) can read it.
    $GLOBALS['abtestkit_current_pt_assignment'] = [
        'test'      => $test,
        'variant'   => $assigned,
        'role'      => $role,
        'post_id'   => $post_id,
        'protocol'  => 'https',
        'no_track'  => false,
        'forced_a'  => ( $force_variant === 'A' ),
        'forced_b'  => ( $force_variant === 'B' ),
    ];

    $uses_single_url_shadow =
        ( isset( $test['kind'] ) && in_array( (string) $test['kind'], [ 'custom_css', 'custom_html' ], true ) )
        || $is_product_test
        || (
            in_array( $post_type, [ 'page', 'post' ], true )
            && ! empty( $test['variant_id'] )
            && abtestkit_is_shadow_variant( (int) $test['variant_id'] )
        );

    // Redirect to the correct variant if the landing page mismatches assignment.
    // Products and draft shadow page/post tests keep a single public URL and shadow B onto A.
    if ( ! $uses_single_url_shadow ) {
        if ( $assigned === 'B' && $role === 'control' && ! empty( $test['variant_id'] ) ) {
            wp_safe_redirect( get_permalink( (int) $test['variant_id'] ), 302 );
            exit;
        }
        if ( $assigned === 'A' && $role === 'variant' ) {
            wp_safe_redirect( get_permalink( (int) $test['control_id'] ), 302 );
            exit;
        }
    } else {
        // Page/post draft shadow mode: render B on the control URL instead of redirecting to the draft permalink.
        if ( ! $is_product_test && $assigned === 'B' && $role === 'control' && ! empty( $test['variant_id'] ) ) {
            abtestkit_pt_page_shadow_ctx_set(
                (int) $test['control_id'],
                (int) $test['variant_id'],
                (array) $test
            );
            abtestkit_pt_page_shadow_activate();
        }

        // Direct visits to the draft B permalink should still bounce back to A unless previewing.
        if ( ! $is_product_test && $assigned === 'A' && $role === 'variant' ) {
            wp_safe_redirect( get_permalink( (int) $test['control_id'] ), 302 );
            exit;
        }
    }

}, 1 );


if ( ! function_exists( 'abtestkit_custom_css_preview_assignment' ) ) {
    function abtestkit_custom_css_preview_assignment() : array {
        if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
            return [];
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only preview token; ownership is checked against the current admin user.
        $preview_flag = isset( $_GET['abtestkit_preview'] )
            ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_preview'] ) )
            : '';

        $token = isset( $_GET['abtestkit_custom_css_preview'] )
            ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_custom_css_preview'] ) )
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( $preview_flag !== '1' || $token === '' ) {
            return [];
        }

        $data = get_transient( 'abtestkit_custom_css_preview_' . $token );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        if ( $user_id <= 0 || $user_id !== (int) get_current_user_id() ) {
            return [];
        }

        $control_id = isset( $data['control_id'] ) ? absint( $data['control_id'] ) : 0;
        if ( $control_id <= 0 ) {
            return [];
        }

        $current_id = (int) get_queried_object_id();
        if ( $current_id > 0 && $current_id !== $control_id ) {
            return [];
        }

        $custom_css  = isset( $data['custom_css'] ) ? abtestkit_sanitize_custom_css_input( $data['custom_css'] ) : '';
        $css_markers = isset( $data['css_markers'] ) ? abtestkit_sanitize_custom_css_markers( $data['css_markers'] ) : [];

        if ( $custom_css === '' && empty( $css_markers ) ) {
            return [];
        }

        return [
            'ctx'  => [
                'variant' => 'B',
                'preview' => 1,
            ],
            'test' => [
                'id'         => 'preview-' . substr( md5( $token ), 0, 12 ),
                'kind'       => 'custom_css',
                'control_id' => $control_id,
            ],
            'data' => [
                'css_scope'   => isset( $data['post_type'] ) ? sanitize_key( (string) $data['post_type'] ) : '',
                'custom_css'  => $custom_css,
                'css_markers' => $css_markers,
            ],
        ];
    }
}

if ( ! function_exists( 'abtestkit_custom_css_current_assignment' ) ) {
    function abtestkit_custom_css_current_assignment() : array {
        $preview_assignment = abtestkit_custom_css_preview_assignment();

        if ( ! empty( $preview_assignment ) ) {
            return $preview_assignment;
        }

        $ctx = isset( $GLOBALS['abtestkit_current_pt_assignment'] ) && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
            ? $GLOBALS['abtestkit_current_pt_assignment']
            : [];

        $test = isset( $ctx['test'] ) && is_array( $ctx['test'] ) ? $ctx['test'] : [];

        if ( empty( $test['id'] ) || ( $test['kind'] ?? '' ) !== 'custom_css' ) {
            return [];
        }

        if ( ( $ctx['variant'] ?? '' ) !== 'B' ) {
            return [];
        }

        return [
            'ctx'  => $ctx,
            'test' => $test,
            'data' => abtestkit_custom_css_data_for_test( $test ),
        ];
    }
}

add_filter( 'body_class', function ( $classes ) {
    $assignment = abtestkit_custom_css_current_assignment();

    if ( empty( $assignment ) ) {
        return $classes;
    }

    $classes[] = 'abtestkit-custom-css-b';

    $test_id = isset( $assignment['test']['id'] ) ? sanitize_html_class( (string) $assignment['test']['id'] ) : '';
    if ( $test_id !== '' ) {
        $classes[] = 'abtestkit-custom-css-b-' . $test_id;
    }

    return array_values( array_unique( array_filter( $classes ) ) );
} );

add_action( 'wp_head', function () {
    $assignment = abtestkit_custom_css_current_assignment();

    if ( empty( $assignment ) || empty( $assignment['data']['custom_css'] ) ) {
        return;
    }

    $test_id = isset( $assignment['test']['id'] ) ? sanitize_html_class( (string) $assignment['test']['id'] ) : 'custom-css';
    $css     = (string) $assignment['data']['custom_css'];

    echo "\n" . '<style id="abtestkit-custom-css-' . esc_attr( $test_id ) . '">' . "\n";
    // CSS is sanitised before storage and again before output by abtestkit_custom_css_data_for_test().
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $css;
    echo "\n" . '</style>' . "\n";
}, 999 );

/**
 * Apply optional Version B marker classes before initial body paint.
 *
 * Direct Custom CSS already loads in wp_head. Marker-based rules also need
 * their saved classes before the first rendered frame, then incremental
 * reapplication when builders or AJAX replace the target later.
 */
add_action( 'wp_head', function () {
    $assignment = abtestkit_custom_css_current_assignment();

    if ( empty( $assignment ) ) {
        return;
    }

    $markers = isset( $assignment['data']['css_markers'] ) && is_array( $assignment['data']['css_markers'] )
        ? $assignment['data']['css_markers']
        : [];

    if ( empty( $markers ) ) {
        return;
    }

    ?>
    <script id="abtestkit-custom-css-markers">
    (function () {
      var markers = <?php echo wp_json_encode( $markers ); ?>;
      if (!Array.isArray(markers) || !markers.length) return;

      var markerApplyQueued = false;
      var markerQueuedRoots = [];
      var markerObserver = null;
      var markerStopped = false;
      var initialMarkerApplicationFinished = document.readyState !== 'loading';
      var fallbackSelector = [
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'a',
        'button',
        '[role="button"]',
        '[role="tab"]',
        '.title',
        '.element-title',
        '.slider-title',
        '.section-title',
        '.product_title',
        '.related',
        '.related-products',
        '.upsells',
        '.cross-sells'
      ].join(',');

      function normaliseMarkerText(value) {
        return String(value || '')
          .toLowerCase()
          .replace(/[_-]+/g, ' ')
          .replace(/\s+/g, ' ')
          .trim();
      }

      function labelFromMarker(marker) {
        var label = normaliseMarkerText(marker && marker.label ? marker.label : '');

        if (label) {
          return label;
        }

        var className = String(marker && marker.class_name ? marker.class_name : '');
        className = className.replace(/^abtestkit-marker-/, '');

        return normaliseMarkerText(className);
      }

      function isUsableFallbackElement(el) {
        if (!el || !el.classList || !el.textContent) {
          return false;
        }

        try {
          var style = window.getComputedStyle(el);

          if (!style || style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') === 0) {
            return false;
          }
        } catch (e) {}

        return true;
      }

      function addMarkerClass(el, className) {
        if (el && el.classList && className) {
          el.classList.add(className);
          return true;
        }

        return false;
      }

      function elementsWithinRoot(root, selector) {
        var elements = [];

        if (root && root.nodeType === 1 && root.matches && root.matches(selector)) {
          elements.push(root);
        }

        if (root && root.querySelectorAll) {
          elements = elements.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
        }

        return elements.filter(function (element, index, allElements) {
          return allElements.indexOf(element) === index;
        });
      }

      function applyMarkerBySelector(marker, root) {
        var matched = 0;

        if (!marker || !marker.selector || !marker.class_name) {
          return matched;
        }

        try {
          elementsWithinRoot(root, marker.selector).forEach(function (el) {
            if (addMarkerClass(el, marker.class_name)) {
              matched++;
            }
          });
        } catch (e) {}

        return matched;
      }

      function applyMarkerByLabelFallback(marker, root) {
        var label = labelFromMarker(marker);

        if (!label || !marker || !marker.class_name) {
          return 0;
        }

        var matched = 0;
        var candidates = elementsWithinRoot(root, fallbackSelector);

        candidates.forEach(function (el) {
          if (matched > 0 || !isUsableFallbackElement(el)) {
            return;
          }

          var text = normaliseMarkerText(el.textContent);

          if (text === label) {
            if (addMarkerClass(el, marker.class_name)) {
              matched++;
            }
          }
        });

        return matched;
      }

      function applyMarkers(root) {
        root = root && root.querySelectorAll ? root : document;

        markers.forEach(function (marker) {
          if (!marker || !marker.class_name) return;

          var matched = applyMarkerBySelector(marker, root);

          if (!matched) {
            applyMarkerByLabelFallback(marker, root);
          }
        });
      }

      function markerRootContains(parent, child) {
        if (parent === document) return true;
        return !!(parent && child && parent.contains && parent.contains(child));
      }

      function scheduleMarkerApply(root) {
        if (markerStopped) return;

        root = root && root.querySelectorAll ? root : document;

        if (root === document || markerQueuedRoots.length >= 50) {
          markerQueuedRoots = [document];
        } else {
          if (markerQueuedRoots.some(function (queuedRoot) {
            return markerRootContains(queuedRoot, root);
          })) {
            return;
          }

          markerQueuedRoots = markerQueuedRoots.filter(function (queuedRoot) {
            return !markerRootContains(root, queuedRoot);
          });
          markerQueuedRoots.push(root);
        }

        if (markerApplyQueued) return;

        markerApplyQueued = true;

        window.requestAnimationFrame(function () {
          if (markerStopped) return;

          var roots = markerQueuedRoots.slice();
          markerQueuedRoots = [];
          markerApplyQueued = false;

          roots.forEach(applyMarkers);
        });
      }

      function finishInitialMarkerApplication() {
        if (markerStopped) return;

        applyMarkers(document);
        initialMarkerApplicationFinished = true;
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', finishInitialMarkerApplication, { once: true });
      } else {
        finishInitialMarkerApplication();
      }

      window.addEventListener('load', function () {
        scheduleMarkerApply(document);
      }, { once: true });

      [100, 500, 1200, 2500].forEach(function (delay) {
        window.setTimeout(function () {
          scheduleMarkerApply(document);
        }, delay);
      });

      if (window.MutationObserver && document.documentElement) {
        markerObserver = new MutationObserver(function (records) {
          records.forEach(function (record) {
            Array.prototype.forEach.call(record.addedNodes || [], function (node) {
              var root = node && node.nodeType === 1
                ? node
                : (node && node.parentElement ? node.parentElement : null);

              if (!root) return;

              if (initialMarkerApplicationFinished) {
                scheduleMarkerApply(root);
              } else {
                applyMarkers(root);
              }
            });
          });
        });

        markerObserver.observe(document.documentElement, {
          childList: true,
          subtree: true
        });
      }

      window.addEventListener('pagehide', function () {
        markerStopped = true;
        markerQueuedRoots = [];

        if (markerObserver) {
          markerObserver.disconnect();
        }
      }, { once: true });
    })();
    </script>
    <?php
}, 2 );

if ( ! function_exists( 'abtestkit_custom_html_preview_assignment' ) ) {
    function abtestkit_custom_html_preview_assignment() : array {
        if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
            return [];
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only preview token; ownership is checked against the current admin user.
        $preview_flag = isset( $_GET['abtestkit_preview'] )
            ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_preview'] ) )
            : '';

        $token = isset( $_GET['abtestkit_custom_html_preview'] )
            ? sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_custom_html_preview'] ) )
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( $preview_flag !== '1' || $token === '' ) {
            return [];
        }

        $data = get_transient( 'abtestkit_custom_html_preview_' . $token );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        if ( $user_id <= 0 || $user_id !== (int) get_current_user_id() ) {
            return [];
        }

        $control_id = isset( $data['control_id'] ) ? absint( $data['control_id'] ) : 0;
        if ( $control_id <= 0 ) {
            return [];
        }

        $current_id = (int) get_queried_object_id();
        if ( $current_id > 0 && $current_id !== $control_id ) {
            return [];
        }

        $html_changes = isset( $data['html_changes'] )
            ? abtestkit_sanitize_custom_html_changes( $data['html_changes'] )
            : [];

        if ( empty( $html_changes ) ) {
            return [];
        }

        return [
            'ctx'  => [
                'variant' => 'B',
                'preview' => 1,
            ],
            'test' => [
                'id'         => 'preview-' . substr( md5( $token ), 0, 12 ),
                'kind'       => 'custom_html',
                'control_id' => $control_id,
            ],
            'data' => [
                'html_scope'   => isset( $data['post_type'] ) ? sanitize_key( (string) $data['post_type'] ) : '',
                'html_changes' => $html_changes,
            ],
        ];
    }
}

if ( ! function_exists( 'abtestkit_custom_html_current_assignment' ) ) {
    function abtestkit_custom_html_current_assignment() : array {
        $preview_assignment = abtestkit_custom_html_preview_assignment();

        if ( ! empty( $preview_assignment ) ) {
            return $preview_assignment;
        }

        $ctx = isset( $GLOBALS['abtestkit_current_pt_assignment'] ) && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
            ? $GLOBALS['abtestkit_current_pt_assignment']
            : [];

        $test = isset( $ctx['test'] ) && is_array( $ctx['test'] ) ? $ctx['test'] : [];

        if ( empty( $test['id'] ) || ( $test['kind'] ?? '' ) !== 'custom_html' ) {
            return [];
        }

        if ( ( $ctx['variant'] ?? '' ) !== 'B' ) {
            return [];
        }

        return [
            'ctx'  => $ctx,
            'test' => $test,
            'data' => abtestkit_custom_html_data_for_test( $test ),
        ];
    }
}

add_filter( 'body_class', function ( $classes ) {
    $assignment = abtestkit_custom_html_current_assignment();

    if ( empty( $assignment ) ) {
        return $classes;
    }

    $classes[] = 'abtestkit-custom-html-b';

    $test_id = isset( $assignment['test']['id'] ) ? sanitize_html_class( (string) $assignment['test']['id'] ) : '';
    if ( $test_id !== '' ) {
        $classes[] = 'abtestkit-custom-html-b-' . $test_id;
    }

    return array_values( array_unique( array_filter( $classes ) ) );
} );

/**
 * Hide Custom HTML targets only while Version B is being applied.
 *
 * The selector text is evaluated by querySelectorAll() rather than emitted as
 * CSS. This keeps malformed selectors isolated and prevents one bad selector
 * from invalidating the complete anti-flicker layer. The timeout is deliberately
 * short and fail-open so a runtime or third-party JavaScript error can never
 * leave live content hidden.
 */
add_action( 'wp_head', function () {
    $assignment = abtestkit_custom_html_current_assignment();

    if ( empty( $assignment ) ) {
        return;
    }

    $changes = isset( $assignment['data']['html_changes'] ) && is_array( $assignment['data']['html_changes'] )
        ? $assignment['data']['html_changes']
        : [];

    if ( empty( $changes ) ) {
        return;
    }

    $selector_configs = [];

    foreach ( $changes as $change ) {
        $selector = isset( $change['selector'] ) ? trim( (string) $change['selector'] ) : '';

        if ( $selector === '' ) {
            continue;
        }

        $selector_configs[] = [
            'selector'   => $selector,
            'match_mode' => ( ( $change['match_mode'] ?? 'all' ) === 'first' ) ? 'first' : 'all',
        ];
    }

    if ( empty( $selector_configs ) ) {
        return;
    }

    $test_id = isset( $assignment['test']['id'] )
        ? sanitize_html_class( (string) $assignment['test']['id'] )
        : 'custom-html';

    $safe_test_id      = preg_replace( '/[^a-zA-Z0-9_-]/', '', $test_id );
    $pending_attribute = 'data-abtestkit-html-' . ( $safe_test_id ?: 'custom-html' ) . '-pending';
    ?>
    <style id="abtestkit-custom-html-antiflicker-<?php echo esc_attr( $test_id ); ?>">
    [<?php echo esc_attr( $pending_attribute ); ?>="1"] { visibility: hidden !important; }
    </style>
    <script id="abtestkit-custom-html-antiflicker-loader-<?php echo esc_attr( $test_id ); ?>">
    (function () {
      var configs = <?php echo wp_json_encode( array_values( $selector_configs ) ); ?>;
      var pendingAttribute = <?php echo wp_json_encode( $pending_attribute ); ?>;
      var styleId = <?php echo wp_json_encode( 'abtestkit-custom-html-antiflicker-' . $test_id ); ?>;
      var observer = null;
      var finished = false;

      if (!Array.isArray(configs) || !configs.length || !pendingAttribute) return;

      configs = configs.map(function (config) {
        return {
          selector: String((config && config.selector) || ''),
          matchMode: String((config && config.match_mode) || 'all') === 'first' ? 'first' : 'all',
          matched: false
        };
      });

      function markElement(element, config) {
        if (!element || !element.setAttribute || (config.matchMode === 'first' && config.matched)) {
          return;
        }

        element.setAttribute(pendingAttribute, '1');
        config.matched = true;
      }

      function markWithin(root) {
        if (finished || !root) return;

        configs.forEach(function (config) {
          if (!config.selector || (config.matchMode === 'first' && config.matched)) return;

          try {
            if (root.nodeType === 1 && root.matches && root.matches(config.selector)) {
              markElement(root, config);
            }

            if (config.matchMode === 'first' && config.matched) return;
            if (!root.querySelectorAll) return;

            var matches = root.querySelectorAll(config.selector);
            var limit = config.matchMode === 'first' ? Math.min(matches.length, 1) : matches.length;

            for (var index = 0; index < limit; index++) {
              markElement(matches[index], config);
            }
          } catch (e) {}
        });
      }

      function cleanup() {
        if (finished) return;
        finished = true;

        if (observer) {
          observer.disconnect();
        }

        try {
          document.querySelectorAll('[' + pendingAttribute + ']').forEach(function (element) {
            element.removeAttribute(pendingAttribute);
          });
        } catch (e) {}

        var style = document.getElementById(styleId);
        if (style && style.parentNode) {
          style.parentNode.removeChild(style);
        }
      }

      markWithin(document);

      if (window.MutationObserver && document.documentElement) {
        observer = new MutationObserver(function (records) {
          records.forEach(function (record) {
            Array.prototype.forEach.call(record.addedNodes || [], markWithin);
          });
        });

        observer.observe(document.documentElement, {
          childList: true,
          subtree: true
        });
      }

      window.setTimeout(cleanup, 3000);
    })();
    </script>
    <?php
}, 1 );

add_action( 'wp_head', function () {
    $assignment = abtestkit_custom_html_current_assignment();

    if ( empty( $assignment ) ) {
        return;
    }

    $changes = isset( $assignment['data']['html_changes'] ) && is_array( $assignment['data']['html_changes'] )
        ? $assignment['data']['html_changes']
        : [];

    if ( empty( $changes ) ) {
        return;
    }

    $test_id = isset( $assignment['test']['id'] )
        ? sanitize_html_class( (string) $assignment['test']['id'] )
        : 'custom-html';
    $control_id = isset( $assignment['test']['control_id'] )
        ? absint( $assignment['test']['control_id'] )
        : 0;
    $health_reporting_enabled = (
        empty( $assignment['ctx']['preview'] )
        && ( $assignment['test']['status'] ?? '' ) === 'running'
        && strpos( $test_id, 'pt-' ) === 0
        && $control_id > 0
    );
    $health_endpoint = $health_reporting_enabled
        ? rest_url( 'abtestkit/v1/pt/html-runtime-health' )
        : '';
    ?>
    <script id="abtestkit-custom-html-<?php echo esc_attr( $test_id ); ?>">
    (function () {
      var changes = <?php echo wp_json_encode( $changes ); ?>;
      if (!Array.isArray(changes) || !changes.length) return;

      var applyQueued = false;
      var queuedRoots = [];
      var observer = null;
      var stopped = false;
      var initialApplicationFinished = document.readyState !== 'loading';
      var healthTimer = null;
      var lastHealthCheckAt = 0;
      var lastHealthSignature = '';
      var testId = <?php echo wp_json_encode( $test_id ); ?>;
      var controlId = <?php echo (int) $control_id; ?>;
      var healthEndpoint = <?php echo wp_json_encode( $health_endpoint ); ?>;
      var healthReportingEnabled = <?php echo $health_reporting_enabled ? 'true' : 'false'; ?>;
      var markerAttribute = 'data-abtestkit-html-' + String(testId || 'custom-html').replace(/[^a-zA-Z0-9_-]/g, '');
      var insertedAttribute = markerAttribute + '-inserted';
      var pendingAttribute = markerAttribute + '-pending';
      var appliedStates = typeof WeakMap === 'function' ? new WeakMap() : null;
      var fallbackStateKey = '__abtestkitHtmlState' + String(testId || 'customHtml').replace(/[^a-zA-Z0-9_$]/g, '');

      function getAppliedStates(element) {
        if (appliedStates) {
          return appliedStates.get(element) || null;
        }

        return element && element[fallbackStateKey] ? element[fallbackStateKey] : null;
      }

      function getAppliedState(element, changeKey) {
        var states = getAppliedStates(element);
        return states && states[changeKey] ? states[changeKey] : null;
      }

      function setAppliedState(element, changeKey, state) {
        var states = getAppliedStates(element) || {};
        states[changeKey] = state;

        if (appliedStates) {
          appliedStates.set(element, states);
          return;
        }

        try {
          element[fallbackStateKey] = states;
        } catch (e) {}
      }

      function normaliseOperation(value) {
        var operation = String(value || 'replace_contents');
        return [
          'replace_contents',
          'insert_before',
          'insert_after',
          'prepend_inside',
          'append_inside'
        ].indexOf(operation) !== -1 ? operation : 'replace_contents';
      }

      function createInsertionFragment(html) {
        var fragment = document.createDocumentFragment();
        var start = document.createComment('abtestkit-html-start');
        var end = document.createComment('abtestkit-html-end');
        var template = document.createElement('template');

        fragment.appendChild(start);
        template.innerHTML = html;

        if (template.content) {
          while (template.content.firstChild) {
            fragment.appendChild(template.content.firstChild);
          }
        } else {
          var container = document.createElement('div');
          container.innerHTML = html;
          while (container.firstChild) {
            fragment.appendChild(container.firstChild);
          }
        }

        Array.prototype.slice.call(fragment.childNodes).forEach(function (node) {
          if (node && node.nodeType === 1 && node.setAttribute) {
            node.setAttribute(insertedAttribute, '1');
          }
        });

        fragment.appendChild(end);

        return {
          fragment: fragment,
          start: start,
          end: end
        };
      }

      function insertionIsConnected(state) {
        return !!(
          state &&
          state.start &&
          state.end &&
          state.start.isConnected &&
          state.end.isConnected &&
          state.start.parentNode === state.end.parentNode
        );
      }

      function applyChangeToElement(element, change, operation, changeKey) {
        if (!element) return false;

        var previousState = getAppliedState(element, changeKey);

        if (
          operation === 'replace_contents' &&
          previousState &&
          previousState.operation === operation &&
          previousState.value === element.innerHTML
        ) {
          element.removeAttribute(pendingAttribute);
          return false;
        }

        if (
          operation !== 'replace_contents' &&
          previousState &&
          previousState.operation === operation &&
          insertionIsConnected(previousState)
        ) {
          element.removeAttribute(pendingAttribute);
          return false;
        }

        if (operation === 'replace_contents') {
          element.innerHTML = change.html;

          setAppliedState(element, changeKey, {
            operation: operation,
            value: element.innerHTML
          });
        } else {
          var insertion = createInsertionFragment(change.html);

          if (operation === 'insert_before') {
            if (!element.parentNode) return false;
            element.parentNode.insertBefore(insertion.fragment, element);
          } else if (operation === 'insert_after') {
            if (!element.parentNode) return false;
            element.parentNode.insertBefore(insertion.fragment, element.nextSibling);
          } else if (operation === 'prepend_inside') {
            element.insertBefore(insertion.fragment, element.firstChild);
          } else {
            element.appendChild(insertion.fragment);
          }

          setAppliedState(element, changeKey, {
            operation: operation,
            start: insertion.start,
            end: insertion.end
          });
        }

        element.setAttribute(markerAttribute, '1');
        element.removeAttribute(pendingAttribute);
        return true;
      }

      function elementIsInsideInsertedContent(element) {
        var insideInsertedContent = !!(
          element &&
          element.closest &&
          element.closest('[' + insertedAttribute + ']')
        );

        if (insideInsertedContent && element.removeAttribute) {
          element.removeAttribute(pendingAttribute);
        }

        return insideInsertedContent;
      }

      function elementsForChange(change, matchMode, root) {
        var elements = [];

        if (matchMode === 'first') {
          var first = document.querySelector(change.selector);
          return first && !elementIsInsideInsertedContent(first) ? [first] : [];
        }

        if (root && root.nodeType === 1 && root.matches && root.matches(change.selector)) {
          elements.push(root);
        }

        if (root && root.querySelectorAll) {
          elements = elements.concat(Array.prototype.slice.call(root.querySelectorAll(change.selector)));
        }

        return elements.filter(function (element, index, allElements) {
          return allElements.indexOf(element) === index && !elementIsInsideInsertedContent(element);
        });
      }

      function applyChanges(root) {
        var replacementStates = [];
        root = root && root.querySelectorAll ? root : document;

        changes.forEach(function (change, changeIndex) {
          if (!change || !change.selector || typeof change.html !== 'string') return;

          var elements = [];
          var operation = normaliseOperation(change.operation);
          var matchMode = String(change.match_mode || 'all') === 'first' ? 'first' : 'all';
          var changeKey = 'change-' + String(changeIndex);

          try {
            elements = elementsForChange(change, matchMode, root);
          } catch (e) {
            return;
          }

          elements.forEach(function (element) {
            var didApply = applyChangeToElement(element, change, operation, changeKey);

            if (operation === 'replace_contents') {
              replacementStates.push({ element: element, changeKey: changeKey });
            }

            if (!didApply) return;

            try {
              element.dispatchEvent(new CustomEvent('abtestkit:html-applied', {
                bubbles: true,
                detail: {
                  selector: change.selector,
                  operation: operation,
                  matchMode: matchMode,
                  testId: testId
                }
              }));
            } catch (e) {}
          });
        });

        replacementStates.forEach(function (item) {
          var state = getAppliedState(item.element, item.changeKey);

          if (!state || state.operation !== 'replace_contents') return;

          state.value = item.element.innerHTML;
          setAppliedState(item.element, item.changeKey, state);
        });
      }

      function rootContains(parent, child) {
        if (parent === document) return true;
        return !!(parent && child && parent.contains && parent.contains(child));
      }

      function queueRoot(root) {
        if (stopped || !root || (root.nodeType !== 1 && root !== document)) return;

        if (root === document || queuedRoots.length >= 50) {
          queuedRoots = [document];
        } else {
          if (queuedRoots.some(function (queuedRoot) {
            return rootContains(queuedRoot, root);
          })) {
            return;
          }

          queuedRoots = queuedRoots.filter(function (queuedRoot) {
            return !rootContains(root, queuedRoot);
          });
          queuedRoots.push(root);
        }

        if (applyQueued) return;
        applyQueued = true;

        window.requestAnimationFrame(function () {
          if (stopped) return;

          var roots = queuedRoots.slice();
          queuedRoots = [];
          applyQueued = false;

          roots.forEach(applyChanges);
        });
      }

      function scheduleApply() {
        queueRoot(document);
      }

      function collectHealthCounts() {
        var counts = {
          matched: 0,
          missing: 0,
          invalid: 0,
          total: changes.length
        };

        changes.forEach(function (change) {
          if (!change || !change.selector || typeof change.html !== 'string') {
            counts.invalid += 1;
            return;
          }

          try {
            var elements = Array.prototype.slice.call(document.querySelectorAll(change.selector)).filter(function (element) {
              return !elementIsInsideInsertedContent(element);
            });

            if (elements.length) {
              counts.matched += 1;
            } else {
              counts.missing += 1;
            }
          } catch (e) {
            counts.invalid += 1;
          }
        });

        return counts;
      }

      function reportHealth() {
        healthTimer = null;
        lastHealthCheckAt = Date.now();

        if (!healthReportingEnabled || !healthEndpoint || !window.fetch || stopped) return;

        var counts = collectHealthCounts();
        var signature = [
          counts.matched,
          counts.missing,
          counts.invalid,
          counts.total
        ].join(':');

        if (signature === lastHealthSignature) return;

        var storageKey = 'abtestkitHtmlHealth:' + testId + ':' + String(controlId);

        try {
          var previous = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}');
          if (
            previous &&
            previous.signature === signature &&
            Number(previous.savedAt || 0) > Date.now() - (15 * 60 * 1000)
          ) {
            lastHealthSignature = signature;
            return;
          }
        } catch (e) {}

        window.fetch(healthEndpoint, {
          method: 'POST',
          credentials: 'same-origin',
          keepalive: true,
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            test_id: testId,
            control_id: controlId,
            matched: counts.matched,
            missing: counts.missing,
            invalid: counts.invalid,
            total: counts.total
          })
        }).then(function (response) {
          if (!response.ok) {
            scheduleHealthCheck(30000);
            return;
          }

          lastHealthSignature = signature;
          try {
            window.sessionStorage.setItem(storageKey, JSON.stringify({
              signature: signature,
              savedAt: Date.now()
            }));
          } catch (e) {}
        }).catch(function () {
          scheduleHealthCheck(30000);
        });
      }

      function scheduleHealthCheck(delay) {
        if (!healthReportingEnabled || stopped || healthTimer) return;

        var wait = Math.max(0, Number(delay || 0));
        if (lastHealthCheckAt > 0) {
          wait = Math.max(wait, 30000 - (Date.now() - lastHealthCheckAt));
        }

        healthTimer = window.setTimeout(reportHealth, wait);
      }

      function finishInitialApplication() {
        if (stopped) return;

        applyChanges(document);
        initialApplicationFinished = true;
        scheduleHealthCheck(3000);
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', finishInitialApplication, { once: true });
      } else {
        finishInitialApplication();
      }

      window.addEventListener('load', scheduleApply, { once: true });

      [100, 500, 1200, 2500].forEach(function (delay) {
        window.setTimeout(scheduleApply, delay);
      });

      function scheduleAfterInteraction() {
        window.setTimeout(scheduleApply, 0);
        window.setTimeout(scheduleApply, 150);
      }

      document.addEventListener('click', scheduleAfterInteraction, true);
      document.addEventListener('focusin', scheduleAfterInteraction, true);
      document.addEventListener('abtestkit:refresh-html', scheduleApply, true);
      window.addEventListener('popstate', scheduleApply);
      window.addEventListener('hashchange', scheduleApply);

      if (window.MutationObserver && document.documentElement) {
        observer = new MutationObserver(function (records) {
          records.forEach(function (record) {
            Array.prototype.forEach.call(record.addedNodes || [], function (node) {
              if (node && node.nodeType === 1) {
                if (initialApplicationFinished) {
                  queueRoot(node);
                } else {
                  applyChanges(node);
                }
              }
            });
          });

          if (initialApplicationFinished) {
            scheduleHealthCheck(2000);
          }
        });

        observer.observe(document.documentElement, {
          childList: true,
          subtree: true
        });
      }

      window.addEventListener('pagehide', function () {
        stopped = true;
        queuedRoots = [];

        if (observer) {
          observer.disconnect();
        }

        if (healthTimer) {
          window.clearTimeout(healthTimer);
          healthTimer = null;
        }
      }, { once: true });
    })();
    </script>
    <?php
}, 2 );


/**
 * Destination URL goal tracking.
 *
 * This deliberately logs a normal "click" conversion event so the existing
 * stats, timeline, winner logic, dashboard and performance pages all keep
 * working without a database schema change.
 */
function abtestkit_pt_normalize_destination_url_for_match( string $url ) : string {
    $url = trim( $url );

    if ( $url === '' ) {
        return '';
    }

    // Allow users to paste wildcard targets such as /thank-you*.
    $url = rtrim( $url, '*' );

    $parts = wp_parse_url( $url );

    if ( ! is_array( $parts ) ) {
        return '';
    }

    $path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

    if ( $path === '' ) {
        $path = '/';
    }

    if ( $path[0] !== '/' ) {
        $path = '/' . $path;
    }

    $path = preg_replace( '#/+#', '/', $path );
    $path = rawurldecode( $path );
    $path = rtrim( $path, '/' );

    if ( $path === '' ) {
        $path = '/';
    }

    $query = isset( $parts['query'] ) ? (string) $parts['query'] : '';

    if ( $query !== '' ) {
        $query_vars = [];
        wp_parse_str( $query, $query_vars );

        foreach ( [ 'abtestkit_preview', 'abtestkit_force', 'abtestkit_shadow_preview_id', 'abtestkit_r', 'abtestkit_token' ] as $preview_key ) {
            unset( $query_vars[ $preview_key ] );
        }

        $query = http_build_query( $query_vars, '', '&', PHP_QUERY_RFC3986 );
    }

    return strtolower( $path . ( $query !== '' ? '?' . $query : '' ) );
}

function abtestkit_pt_current_destination_url_for_match() : string {
    $request_uri = '';

    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        // Read-only request URI; sanitized before matching.
        $request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
    }

    if ( $request_uri === '' ) {
        return '';
    }

    return abtestkit_pt_normalize_destination_url_for_match( $request_uri );
}

function abtestkit_pt_destination_target_matches( string $target, string $current_url ) : bool {
    $target = trim( $target );

    if ( $target === '' || $current_url === '' ) {
        return false;
    }

    $is_wildcard = substr( $target, -1 ) === '*';
    $target_url  = abtestkit_pt_normalize_destination_url_for_match( $target );

    if ( $target_url === '' ) {
        return false;
    }

    if ( ! $is_wildcard ) {
        return $current_url === $target_url;
    }

    // Do not let root wildcards match the whole site.
    if ( $target_url === '/' ) {
        return $current_url === '/';
    }

    return (
        $current_url === $target_url ||
        strpos( $current_url, $target_url . '/' ) === 0 ||
        strpos( $current_url, $target_url . '?' ) === 0
    );
}

function abtestkit_pt_destination_targets_for_test( array $test ) : array {
    return abtestkit_pt_click_targets_for_test( $test );
}

function abtestkit_pt_destination_assigned_variant_for_test( array $test ) : string {
    $test_id = isset( $test['id'] ) ? abtestkit_sanitize_test_id( (string) $test['id'] ) : '';

    if ( $test_id === '' ) {
        return '';
    }

    $variant = abtestkit_safe_get_cookie_value( 'abtestkit_pt_' . $test_id );

    if ( in_array( $variant, [ 'A', 'B' ], true ) ) {
        return $variant;
    }

    if ( ( $test['kind'] ?? '' ) === 'reusable_section' ) {
        $seen_raw = abtestkit_safe_get_cookie_value( 'abtestkit_reusable_seen_' . $test_id );

        if ( $seen_raw !== '' ) {
            $seen = json_decode( $seen_raw, true );

            if ( is_array( $seen ) && isset( $seen['variant'] ) && in_array( $seen['variant'], [ 'A', 'B' ], true ) ) {
                return (string) $seen['variant'];
            }
        }
    }

    return '';
}

function abtestkit_pt_maybe_track_destination_url_goal() : void {
    if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || wp_is_json_request() ) {
        return;
    }

    if ( abtestkit_is_exempt_viewer() ) {
        return;
    }

    if ( ! abtestkit_request_is_real_https() ) {
        return;
    }

    // Read-only preview flags; never count dashboard/wizard previews as conversions.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['abtestkit_preview'] ) || isset( $_GET['abtestkit_force'] ) ) {
        return;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $current_url = abtestkit_pt_current_destination_url_for_match();

    if ( $current_url === '' ) {
        return;
    }

    foreach ( abtestkit_pt_all() as $test ) {
        if ( ! is_array( $test ) ) {
            continue;
        }

        $test_id = isset( $test['id'] ) ? abtestkit_sanitize_test_id( (string) $test['id'] ) : '';

        if ( $test_id === '' ) {
            continue;
        }

        if ( ( $test['status'] ?? 'paused' ) !== 'running' ) {
            continue;
        }

        $goal = isset( $test['goal'] ) ? abtestkit_pt_normalize_goal_for_display( $test['goal'] ) : '';

        if ( $goal !== 'destination_url' ) {
            continue;
        }

        $targets = abtestkit_pt_destination_targets_for_test( $test );

        if ( empty( $targets ) ) {
            continue;
        }

        $matched = false;

        foreach ( $targets as $target ) {
            if ( abtestkit_pt_destination_target_matches( (string) $target, $current_url ) ) {
                $matched = true;
                break;
            }
        }

        if ( ! $matched ) {
            continue;
        }

        $variant = abtestkit_pt_destination_assigned_variant_for_test( $test );

        // Only visitors already exposed to the test have an assignment.
        if ( ! in_array( $variant, [ 'A', 'B' ], true ) ) {
            continue;
        }

        $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;

        if ( $control_id <= 0 ) {
            continue;
        }

        $dedupe_cookie = 'abtestkit_pt_destination_' . md5( $test_id . '|' . $variant . '|' . $current_url );

        if ( abtestkit_safe_get_cookie_value( $dedupe_cookie ) === '1' ) {
            continue;
        }

        abtestkit_safe_set_cookie( $dedupe_cookie, '1', 0 );
        $_COOKIE[ $dedupe_cookie ] = '1';

        abtestkit_log_event_to_db(
            'click',
            $control_id,
            $test_id,
            $variant,
            [
                'protocol' => 'https',
            ]
        );

        abtestkit_pt_maybe_lock_winner( $test_id );
    }
}
add_action( 'template_redirect', 'abtestkit_pt_maybe_track_destination_url_goal', 20 );

function abtestkit_pt_status_priority( string $status ) : int {
    // Lower number = higher priority
    if ( $status === 'running' )  return 0;
    if ( $status === 'paused' )   return 1;
    if ( $status === 'draft' )    return 2;
    if ( $status === 'complete' ) return 9;
    return 5;
}

function abtestkit_pt_pick_best_test_for_control( array $tests, int $control_id ) : ?array {
    $matches = [];

    foreach ( $tests as $t ) {
        if ( ! is_array( $t ) ) continue;
        if ( ( $t['kind'] ?? '' ) !== 'product' ) continue;
        if ( (int)( $t['control_id'] ?? 0 ) !== $control_id ) continue;
        $matches[] = $t;
    }

    if ( empty( $matches ) ) return null;

    usort( $matches, function( $a, $b ) {

        $pa = abtestkit_pt_status_priority( (string)($a['status'] ?? '') );
        $pb = abtestkit_pt_status_priority( (string)($b['status'] ?? '') );

        if ( $pa !== $pb ) {
            return $pa <=> $pb;
        }

        $sa = (int) ( $a['started_at'] ?? 0 );
        $sb = (int) ( $b['started_at'] ?? 0 );

        if ( $sa !== $sb ) {
            return $sb <=> $sa; // newest first
        }

        return strcmp(
            (string)($b['id'] ?? ''),
            (string)($a['id'] ?? '')
        );
    });

    return $matches[0];
}

/**
 * Helper: get the active product test (if any) and the viewer's variant
 * for a given WooCommerce product.
 */
function abtestkit_get_active_product_test_for_product( $product ) : array {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return [ null, '' ];
    }

    if ( abtestkit_is_custom_css_picker_preview_request() ) {
        return [ null, '' ];
    }

    // Allow explicit preview overrides for admin/exempt viewers when using the
    // dashboard preview links (abtestkit_preview=1&abtestkit_force=A|B).
    $force_variant = '';
    $preview_flag  = '';
    $raw_force     = '';

    // Read-only GET flags; no state is changed here.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['abtestkit_preview'] ) ) {
        $preview_flag = sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_preview'] ) );
    }

    if ( isset( $_GET['abtestkit_force'] ) ) {
        $raw_force = strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_force'] ) ) );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $preview_flag === '1' && ( $raw_force === 'A' || $raw_force === 'B' ) ) {
        $force_variant = $raw_force;
    }

    // If we're previewing Version B for a product *before the test exists*,
    // allow passing either:
    // - a temporary preview token containing overrides, or
    // - an explicit shadow product ID from the wizard.
    $preview_token     = '';
    $shadow_preview_id = 0;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['abtestkit_token'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $preview_token = sanitize_text_field( wp_unslash( (string) $_GET['abtestkit_token'] ) );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['abtestkit_shadow_preview_id'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $shadow_preview_id = absint( wp_unslash( (string) $_GET['abtestkit_shadow_preview_id'] ) );
    }

    if ( $force_variant === 'B' && $preview_token ) {
        $key  = 'abtestkit_product_preview_' . $preview_token;
        $data = get_transient( $key );

        if ( is_array( $data ) && ! empty( $data['overrides'] ) ) {
            // Ensure the preview token matches this product ID.
            // $product can be a WC_Product OR a numeric product ID depending on hook context.
            $pid = 0;

            if ( $product instanceof WC_Product ) {
                $pid = (int) $product->get_id();
            } elseif ( is_numeric( $product ) ) {
                $pid = absint( $product );
            } elseif ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
                $pid = (int) $product->get_id();
            } else {
                // Last-ditch attempt: try to normalise to a WC_Product
                if ( function_exists( 'wc_get_product' ) ) {
                    $maybe = wc_get_product( $product );
                    if ( $maybe instanceof WC_Product ) {
                        $pid = (int) $maybe->get_id();
                    }
                }
            }

            if ( $pid > 0 && isset( $data['control_id'] ) && (int) $data['control_id'] === $pid ) {
                $fake_test = [
                    'id'               => 'preview-' . $preview_token,
                    'status'           => 'preview',
                    'goal'             => 'add_to_cart',
                    'control_id'        => $pid,
                    'variant_id'        => 0,
                    'min_conversions'   => 5,
                    'overrides'         => (array) $data['overrides'],
                    'product_overrides' => (array) $data['overrides'],
                ];

                // Return as "variant" so your existing override filters apply.
                return [ $fake_test, 'B' ];
            }
        }
    }

    // Never apply product test overrides inside normal wp-admin screens
    // (product list, editor, etc.) or for exempt viewers.
    //
    // Important: some frontend WooCommerce/theme add-to-cart flows use
    // admin-ajax.php. In those requests is_admin() is true, but the request is
    // still a shopper-facing cart request and must keep the visitor's A/B
    // assignment so cart metadata and totals stay consistent.
    $is_frontend_ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();

    if (
        $force_variant === ''
        && (
            ( is_admin() && ! $is_frontend_ajax )
            || abtestkit_is_exempt_viewer()
        )
    ) {
        return [ null, '' ];
    }

    // Normalise to a WC_Product.
    if ( $product instanceof WC_Product ) {
        $wc_product = $product;
    } else {
        $wc_product = wc_get_product( $product );
        if ( ! $wc_product ) {
            return [ null, '' ];
        }
    }

    $product_id = function_exists( 'abtestkit_pt_get_product_test_control_id' )
        ? abtestkit_pt_get_product_test_control_id( $wc_product )
        : (int) $wc_product->get_id();

    // Wizard pre-save preview: if the wizard tells us exactly which shadow product
    // to use, prefer that over guessed/stored lookup.
    if ( $force_variant === 'B' && $shadow_preview_id > 0 ) {
        if (
            function_exists( 'abtestkit_is_shadow_product' )
            && abtestkit_is_shadow_product( $shadow_preview_id )
            && (int) get_post_meta( $shadow_preview_id, '_abtestkit_shadow_of', true ) === $product_id
        ) {
            $fake_test = [
                'id'              => 'preview-shadow-' . (int) $shadow_preview_id,
                'status'          => 'preview',
                'goal'            => 'add_to_cart',
                'control_id'      => (int) $product_id,
                'variant_id'      => (int) $shadow_preview_id,
                'kind'            => 'product',
                'min_conversions' => 5,
            ];

            return [ $fake_test, 'B' ];
        }
    }

    // If we're forcing A/B via preview link, allow resolving paused tests too.
    if ( $force_variant !== '' ) {
        [ $test, $role ] = abtestkit_pt_find_by_post_any_status( $product_id );
    } else {
        [ $test, $role ] = abtestkit_pt_find_by_post( $product_id );
    }

    if ( ! $test || ( $test['kind'] ?? '' ) !== 'product' ) {
        // Wizard pre-save preview: if an admin forces Version B before the test exists,
        // use the last duplicated shadow product created in this wizard session.
        if ( $force_variant === 'B' ) {
            $shadow_id = abtestkit_pt_get_last_duplicate_for_user( $product_id, (int) get_current_user_id() );

            if (
                $shadow_id
                && abtestkit_is_shadow_product( (int) $shadow_id )
                && (int) get_post_meta( (int) $shadow_id, '_abtestkit_shadow_of', true ) === (int) $product_id
            ) {
                $fake_test = [
                    'id'              => 'preview-shadow-' . (int) $shadow_id,
                    'status'          => 'preview',
                    'goal'            => 'add_to_cart',
                    'control_id'      => (int) $product_id,
                    'variant_id'      => (int) $shadow_id,
                    'kind'            => 'product',
                    'min_conversions' => 5,
                ];

                return [ $fake_test, 'B' ];
            }
        }

        return [ null, '' ];
    }

    // If we're on a preview with an explicit forced variant, honour it and bail.
    if ( $force_variant === 'A' || $force_variant === 'B' ) {
        return [ $test, $force_variant ];
    }

    // Live product tests must never assign or render Version B on HTTP.
    // HTTP is diagnostics-only and always treated as control/no-track.
    if ( ! abtestkit_request_is_real_https() ) {
        return [ $test, 'A' ];
    }

    $variant = '';

    // 1) Prefer the assignment that template_redirect already made for this request.
    if (
        isset( $GLOBALS['abtestkit_current_pt_assignment'] )
        && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
    ) {
        $ctx       = $GLOBALS['abtestkit_current_pt_assignment'];
        $ctx_test  = $ctx['test']    ?? null;
        $ctx_var   = $ctx['variant'] ?? '';
        $ctx_id    = ( is_array( $ctx_test ) && isset( $ctx_test['id'] ) ) ? (string) $ctx_test['id'] : '';

        if ( $ctx_id !== '' && $ctx_id === (string) $test['id'] ) {
            $variant = $ctx_var;
        }
    }

    // 2) Fallback to the cookie (archives, menus, carts, or if global isn't set).
    if ( $variant !== 'A' && $variant !== 'B' ) {
        $cookie_name = 'abtestkit_pt_' . (string) $test['id'];
        $cookie_val  = '';

        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cookie_raw = wp_unslash( $_COOKIE[ $cookie_name ] );
            $cookie_val = sanitize_text_field( $cookie_raw );
        }

        if ( $cookie_val === 'A' || $cookie_val === 'B' ) {
            // Existing assignment – just reuse it.
            $variant = $cookie_val;
        } else {
            // No cookie yet (first ever view, often from a category/archive).
            // Assign now using the same split + TTL rules as template_redirect,
            // and write the cookie so subsequent views stay consistent.
            $ttl   = max( 1, (int) ( $test['cookie_ttl_days'] ?? 30 ) );
            $split = max( 0, min( 100, (int) ( $test['split'] ?? 50 ) ) );

            // Same rule as template_redirect: random A/B based on "split" (chance to show B).
            $assigned = ( wp_rand( 1, 100 ) <= (int) $split ) ? 'B' : 'A';

            abtestkit_safe_set_cookie(
                $cookie_name,
                $assigned,
                time() + ( (int) $ttl ) * DAY_IN_SECONDS
            );

            // Keep the current request in sync even if the helper had to fall back
            // to Woo session because headers were already sent.
            $_COOKIE[ $cookie_name ] = $assigned;
            $variant                 = $assigned;
        }
    }

    return [ $test, $variant ];
}

/**
 * Prepare an override string exactly once:
 * - decode HTML entities (&#91;shortcode&#93; / &lt;div&gt; etc)
 * - normalise smart quotes
 * DO NOT run wpautop/shortcodes/blocks here — let the normal filter pipeline do that.
 */
function abtestkit_prepare_override_raw( $html ) : string {
    $out = (string) $html;

    $charset = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'charset' ) : 'UTF-8';
    $out = html_entity_decode( $out, ENT_QUOTES | ENT_HTML5, $charset );

    $out = str_replace(
        [ "“", "”", "‘", "’" ],
        [ '"', '"', "'", "'" ],
        $out
    );

    return $out;
}

function abtestkit_render_product_override_html( $html ) : string {
    $out = abtestkit_prepare_override_raw( $html );

    // Render the override without using the_content, because Elementor can hijack it
    // and replace our override entirely with its own template output.
    if ( function_exists( 'do_blocks' ) ) {
        $out = do_blocks( $out );
    }

    $out = wpautop( $out );
    $out = shortcode_unautop( $out );
    $out = do_shortcode( $out );

    return $out;
}


// WooCommerce product field overrides for "virtual B" product tests.
add_action( 'plugins_loaded', function () {

    // When Variant B has a real variant_id (a duplicated product / Elementor document),
    // enqueue Elementor's per-post CSS for that variant so styling actually appears.
    add_action( 'wp_enqueue_scripts', function() {

        if ( is_admin() ) return;

        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return; // Elementor not active
        }

        global $post;
        if ( ! ( $post instanceof WP_Post ) ) {
            return;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
        if ( ! $product ) {
            return;
        }

        [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
        if ( ! $test || $variant !== 'B' ) {
            return;
        }

        $variant_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        if ( $variant_id <= 0 ) {
            return; // "virtual B" preview tokens have variant_id = 0
        }

        // Base Elementor frontend styles.
        \Elementor\Plugin::instance()->frontend->enqueue_styles();

    }, 20 );

    if ( ! function_exists( 'wc_get_product' ) ) {
        // WooCommerce not active – nothing to do.
        return;
    }

        /**
     * Return the shadow WC_Product for a product test (Version B), or null.
     */
    function abtestkit_pt_get_shadow_product_for_test( $test ) {
        if ( ! is_array( $test ) ) return null;

        $vid = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        if ( $vid <= 0 ) return null;

        static $cache = [];

        // Use array_key_exists so null is cached too.
        if ( array_key_exists( $vid, $cache ) ) {
            return $cache[ $vid ];
        }

        if ( get_post_type( $vid ) !== 'product' ) {
            $cache[ $vid ] = null;
            return null;
        }

        $p = null;

        // Fast path.
        if ( function_exists( 'wc_get_product' ) ) {
            $maybe = wc_get_product( $vid );
            if ( $maybe instanceof WC_Product ) {
                $p = $maybe;
            }
        }

        // Fallback: explicitly include non-public statuses.
        if ( ! $p && function_exists( 'wc_get_products' ) ) {
            $found = wc_get_products( [
                'include' => [ $vid ],
                'limit'   => 1,
                'status'  => [ 'publish', 'draft', 'pending', 'private', 'future' ],
                'return'  => 'objects',
            ] );

            if ( is_array( $found ) && ! empty( $found ) && $found[0] instanceof WC_Product ) {
                $p = $found[0];
            }
        }

        // Last resort: construct a WC_Product directly (bypasses frontend status restrictions).
        if ( ! $p && class_exists( 'WC_Product_Factory' ) ) {
            try {
                $factory = new WC_Product_Factory();

                $type = '';
                if ( method_exists( $factory, 'get_product_type' ) ) {
                    $type = (string) $factory->get_product_type( $vid );
                }
                if ( $type === '' ) {
                    $type = 'simple';
                }

                $classname = 'WC_Product_Simple';
                if ( method_exists( 'WC_Product_Factory', 'get_product_classname' ) ) {
                    $maybe_class = WC_Product_Factory::get_product_classname( $vid, $type );
                    if ( is_string( $maybe_class ) && $maybe_class !== '' && class_exists( $maybe_class ) ) {
                        $classname = $maybe_class;
                    }
                }

                $maybe = new $classname( $vid );
                if ( $maybe instanceof WC_Product ) {
                    $p = $maybe;
                }
            } catch ( \Throwable $e ) {
                // ignore
            }
        }

        $cache[ $vid ] = ( $p instanceof WC_Product ) ? $p : null;
        return $cache[ $vid ];
    }


    /**
     * Shadow products should never be purchasable or visible.
     */
    add_filter( 'woocommerce_is_purchasable', function( $purchasable, $product ) {
        $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : (int) $product;
        if ( $pid > 0 && (int) get_post_meta( $pid, '_abtestkit_shadow', true ) === 1 ) {
            return false;
        }
        return $purchasable;
    }, 10, 2 );

    add_filter( 'woocommerce_product_is_visible', function( $visible, $product_id ) {
        $pid = (int) $product_id;
        if ( $pid > 0 && (int) get_post_meta( $pid, '_abtestkit_shadow', true ) === 1 ) {
            return false;
        }
        return $visible;
    }, 10, 2 );

    // Noindex shadow products if they ever become accessible.
    add_filter( 'wp_robots', function( $robots ) {
        if ( function_exists( 'is_product' ) && is_product() ) {
            $pid = get_queried_object_id();
            if ( $pid > 0 && (int) get_post_meta( $pid, '_abtestkit_shadow', true ) === 1 ) {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;
            }
        }
        return $robots;
    } );

// Name/title
add_filter( 'woocommerce_product_get_name', function ( $name, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $name;
    }

    static $in = false;
    if ( $in ) return $name;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) return $name;
    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) return $name;

    $n = $shadow->get_name( 'edit' );
    return $n !== '' ? $n : $name;

}, 10, 2 );

// Descriptions
add_filter( 'woocommerce_product_get_short_description', function( $val, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $val;
    }

    static $in = false;
    if ( $in ) return $val;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) return $val;
    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) return $val;

    $v = $shadow->get_short_description( 'edit' );
    return $v !== '' ? $v : $val;

}, 10, 2 );

add_filter( 'woocommerce_product_get_description', function( $val, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $val;
    }

    static $in = false;
    if ( $in ) return $val;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) return $val;
    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) return $val;

    $v = $shadow->get_description( 'edit' );
    return $v !== '' ? $v : $val;

}, 10, 2 );

// Images
$abtestkit_shadow_image_id_filter = function( $image_id, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $image_id;
    }

    static $in = false;
    if ( $in ) {
        return $image_id;
    }

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) {
        return $image_id;
    }

    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) {
        return $image_id;
    }

    $iid = (int) $shadow->get_image_id( 'edit' );
    return $iid > 0 ? $iid : $image_id;
};

add_filter( 'woocommerce_product_get_image_id', $abtestkit_shadow_image_id_filter, 10, 2 );
add_filter( 'woocommerce_product_variation_get_image_id', $abtestkit_shadow_image_id_filter, 10, 2 );

add_filter( 'woocommerce_product_get_gallery_image_ids', function( $ids, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $ids;
    }

    static $in = false;
    if ( $in ) return $ids;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) return $ids;
    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) return $ids;

    $g = $shadow->get_gallery_image_ids( 'edit' );
    return ( is_array( $g ) && ! empty( $g ) ) ? $g : $ids;

}, 10, 2 );

// Variable-product display helpers
add_filter( 'woocommerce_get_price_html', function( $price_html, $product ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( ! ( $product instanceof WC_Product ) ) {
        return $price_html;
    }

    if (
        ! function_exists( 'abtestkit_pt_product_supports_shadow_children' )
        || ! abtestkit_pt_product_supports_shadow_children( $product )
    ) {
        return $price_html;
    }

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $price_html;
    }

    static $in = false;
    if ( $in ) return $price_html;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) {
        return $price_html;
    }

    if (
        ! function_exists( 'abtestkit_pt_product_supports_shadow_children' )
        || ! abtestkit_pt_product_supports_shadow_children( $shadow )
    ) {
        return $price_html;
    }

    $shadow_html = $shadow->get_price_html();

    return $shadow_html !== '' ? $shadow_html : $price_html;

}, 10, 2 );

add_filter( 'woocommerce_subscriptions_product_price_string', function( $subscription_string, $product, $include ) {

    if ( ! ( $product instanceof WC_Product ) ) {
        return $subscription_string;
    }

    $pid = (int) $product->get_id();

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $subscription_string;
    }

    static $in = false;
    if ( $in ) {
        return $subscription_string;
    }

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) {
        return $subscription_string;
    }

    if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! method_exists( 'WC_Subscriptions_Product', 'get_subscription_price_string' ) ) {
        return $subscription_string;
    }

    $shadow_string = WC_Subscriptions_Product::get_subscription_price_string( $shadow, $include );

    return $shadow_string !== '' ? $shadow_string : $subscription_string;

}, 10, 3 );

add_filter( 'woocommerce_available_variation', function( $data, $product, $variation ) {

    if ( ! $variation instanceof WC_Product_Variation ) {
        return $data;
    }

    $variation_id = (int) $variation->get_id();
    if ( $variation_id > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $variation_id ) ) {
        return $data;
    }

    static $in = false;
    if ( $in ) return $data;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow_variation = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $variation, $test )
        : null;
    $in = false;

    if ( ! ( $shadow_variation instanceof WC_Product_Variation ) ) {
        return $data;
    }

    $shadow_price         = $shadow_variation->get_price( 'edit' );
    $shadow_regular_price = $shadow_variation->get_regular_price( 'edit' );
    $shadow_description   = $shadow_variation->get_description( 'edit' );
    $shadow_price_html    = $shadow_variation->get_price_html();
    $shadow_image_id      = (int) $shadow_variation->get_image_id( 'edit' );

    if ( class_exists( 'WC_Subscriptions_Product' ) && method_exists( 'WC_Subscriptions_Product', 'get_subscription_price_string' ) ) {
        $subscription_price_html = WC_Subscriptions_Product::get_subscription_price_string( $shadow_variation );
        if ( is_string( $subscription_price_html ) && $subscription_price_html !== '' ) {
            $shadow_price_html = $subscription_price_html;
        }
    }

    if ( $shadow_price !== '' && $shadow_price !== null ) {
        $data['display_price'] = (float) $shadow_price;
    }

    if ( $shadow_regular_price !== '' && $shadow_regular_price !== null ) {
        $data['display_regular_price'] = (float) $shadow_regular_price;
    }

    if ( $shadow_price_html !== '' ) {
        $data['price_html'] = $shadow_price_html;
    }

    if ( $shadow_description !== '' ) {
        $data['variation_description'] = $shadow_description;
    }

    foreach ( [
        '_subscription_sign_up_fee'     => 'subscription_sign_up_fee',
        '_subscription_trial_length'    => 'subscription_trial_length',
        '_subscription_trial_period'    => 'subscription_trial_period',
        '_subscription_period_interval' => 'subscription_period_interval',
        '_subscription_period'          => 'subscription_period',
        '_subscription_length'          => 'subscription_length',
    ] as $meta_key => $payload_key ) {
        $raw = get_post_meta( (int) $shadow_variation->get_id(), $meta_key, true );

        if ( $raw === '' || $raw === null ) {
            continue;
        }

        $data[ $payload_key ] = is_numeric( $raw ) ? (float) $raw : (string) $raw;
    }

    if ( $shadow_image_id > 0 && function_exists( 'wc_get_product_attachment_props' ) ) {
        $props = wc_get_product_attachment_props( $shadow_image_id );

        if ( is_array( $props ) ) {
            $data['image_id'] = $shadow_image_id;
            $data['image']    = $props;
        }
    }

    return $data;

}, 10, 3 );

// ───────────────────────────────────────────────────────────
// Variant B "Shadow Overlay" (builder-agnostic, fast)
// - Keep REAL product (A) as the WooCommerce ID for SKU/stock/orders
// - Overlay *display* layer (meta/content/featured image/builder data/ACF/etc) from shadow product (B)
// - Enable only on single product pages AND only when visitor is Variant B
// ───────────────────────────────────────────────────────────

if ( ! function_exists( 'abtestkit_pt_shadow_ctx_get' ) ) {
    function abtestkit_pt_shadow_ctx_get() : array {
        $ctx = $GLOBALS['abtestkit_pt_shadow_ctx'] ?? null;
        return is_array( $ctx ) ? $ctx : [];
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_ctx_set' ) ) {
    function abtestkit_pt_shadow_ctx_set( int $control_id, int $shadow_id, array $test ) : void {
        $GLOBALS['abtestkit_pt_shadow_ctx'] = [
            'control_id' => $control_id,
            'shadow_id'  => $shadow_id,
            'test_id'    => isset( $test['id'] ) ? (string) $test['id'] : '',
        ];
    }
}

/**
 * Meta keys that must ALWAYS come from the real product (A).
 * These are the "commerce truth" keys that should never be shadowed.
 */
if ( ! function_exists( 'abtestkit_pt_shadow_protected_meta_keys' ) ) {
    function abtestkit_pt_shadow_protected_meta_keys() : array {
        return [
            '_sku',
            '_manage_stock',
            '_stock',
            '_stock_status',
            '_backorders',
            '_sold_individually',
            '_abtestkit_shadow',
            '_abtestkit_shadow_of',
        ];
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_is_meta_key_protected' ) ) {
    function abtestkit_pt_shadow_is_meta_key_protected( $meta_key ) : bool {
        $meta_key = (string) $meta_key;

        if ( $meta_key === '' ) {
            return false;
        }

        if ( in_array( $meta_key, abtestkit_pt_shadow_protected_meta_keys(), true ) ) {
            return true;
        }

        // Elementor/builder meta must stay attached to the real control product.
        // We render the shadow product's content explicitly where needed instead of
        // globally proxying these keys onto Version A.
        if ( strpos( $meta_key, '_elementor' ) === 0 ) {
            return true;
        }

        if ( strpos( $meta_key, '_oembed_' ) === 0 ) {
            return true;
        }

        return false;
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_get_post_metadata' ) ) {
function abtestkit_pt_shadow_get_post_metadata( $value, $object_id, $meta_key, $single ) {
        // Only frontend (AJAX is fine)
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $value;
        }

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $value;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];

        // Safety: never shadow onto itself (can cause recursion / weirdness)
        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id ) {
            return $value;
        }

        $pid = (int) $object_id;
        if ( $pid !== $control_id ) {
            return $value; // only overlay the control product
        }

        static $guard = false;
        if ( $guard ) {
            return $value;
        }

        // If meta_key is empty, WP core is asking for the whole meta cache.
        // ACF (especially repeaters/flexible) often does this, so we must return the shadow's full meta array,
        // but keep "commerce truth" keys from the real product (A).
        if ( ! is_string( $meta_key ) || $meta_key === '' ) {
            $guard = true;

            // Fetch ALL meta arrays.
            $shadow_all  = get_post_meta( $shadow_id );
            $control_all = get_post_meta( $control_id );

            $guard = false;

            if ( ! is_array( $shadow_all ) ) {
                return $value;
            }

            // Start with shadow meta, then overwrite protected keys with control meta.
            $all_keys = array_unique(
                array_merge(
                    array_keys( $shadow_all ),
                    array_keys( $control_all )
                )
            );

            foreach ( $all_keys as $k ) {
                if ( ! abtestkit_pt_shadow_is_meta_key_protected( $k ) ) {
                    continue;
                }

                if ( isset( $control_all[ $k ] ) ) {
                    $shadow_all[ $k ] = $control_all[ $k ];
                } else {
                    unset( $shadow_all[ $k ] );
                }
            }

            // get_post_metadata filters must return an array for full meta.
            return $shadow_all;
        }

        // Never shadow protected runtime/meta-system keys.
        if ( abtestkit_pt_shadow_is_meta_key_protected( $meta_key ) ) {
            return $value;
        }

        // IMPORTANT: don't build our own cache here (it explodes memory).
        // WP already caches post meta internally via update_meta_cache().
        $guard = true;
        $shadow_val = get_post_meta( $shadow_id, $meta_key, $single );
        $guard = false;

        // If shadow has nothing meaningful for this key, keep original behaviour.
        if ( $single ) {
            if ( $shadow_val === '' || $shadow_val === null ) {
                return $value; // let WP fetch A normally
            }

            // IMPORTANT: WP core expects an ARRAY from get_post_metadata filters.
            // When $single=true, core returns $check[0], so index 0 must exist.
            return [ $shadow_val ];
        }

        return ( is_array( $shadow_val ) && ! empty( $shadow_val ) ) ? $shadow_val : $value;
    }
}

/**
 * ACF compatibility: force ACF to resolve field values against the shadow product ID
 * when a visitor is assigned to Variant B.
 *
 * ACF calls: apply_filters( 'acf/pre_load_post_id', null, $post_id );
 */
if ( ! function_exists( 'abtestkit_pt_shadow_acf_pre_load_post_id' ) ) {
    function abtestkit_pt_shadow_acf_pre_load_post_id( $null, $post_id ) {
        // Only frontend (AJAX is fine)
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $null;
        }

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $null;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];

        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id ) {
            return $null;
        }

        $pid = 0;

        if ( is_numeric( $post_id ) ) {
            $pid = (int) $post_id;
        } elseif ( is_string( $post_id ) && preg_match( '/_(\\d+)$/', $post_id, $m ) ) {
            $pid = (int) $m[1];
        }

        if ( $pid !== $control_id ) {
            return $null;
        }

        return $shadow_id;
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_the_content' ) ) {

    // Keep the filter priority in one place so we can safely remove/re-add it.
    if ( ! defined( 'ABTESTKIT_PT_SHADOW_CONTENT_PRIORITY' ) ) {
        define( 'ABTESTKIT_PT_SHADOW_CONTENT_PRIORITY', 9999 );
    }

    /**
     * Return a cache-busting revision token for a shadow product.
     * - post_modified is usually enough
     * - _abtestkit_rev is a lightweight “bump” value we can update on saves/meta edits
     */
    function abtestkit_pt_shadow_rev_token( int $shadow_id ) : string {
        $shadow_id = (int) $shadow_id;
        $mod = (string) get_post_modified_time( 'U', true, $shadow_id );
        $rev = (string) get_post_meta( $shadow_id, '_abtestkit_rev', true );
        if ( $rev === '' ) {
            $rev = '0';
        }
        return $mod . ':' . $rev;
    }

    function abtestkit_pt_shadow_cache_key( int $shadow_id, string $token ) : string {
        // token already changes when content/meta changes
        return 'abtestkit_pt_shadow_html_' . $shadow_id . '_' . md5( $token );
    }

    function abtestkit_pt_shadow_cache_get( string $key ) {
        // Prefer object cache if present; fallback to transient.
        $group = 'abtestkit';
        $val = wp_cache_get( $key, $group );
        if ( $val !== false && is_string( $val ) ) {
            return $val;
        }

        $val = get_transient( $key );
        if ( is_string( $val ) && $val !== '' ) {
            wp_cache_set( $key, $val, $group, 3600 );
            return $val;
        }
        return false;
    }

    function abtestkit_pt_shadow_cache_set( string $key, string $html ) : void {
        $group = 'abtestkit';
        // Keep it long; the key changes automatically when the shadow changes.
        set_transient( $key, $html, 30 * DAY_IN_SECONDS );
        wp_cache_set( $key, $html, $group, 3600 );
    }

    /**
     * Render a product post's content through the normal WP pipeline.
     * This lets Elementor/ACF/shortcodes run against the correct post object.
     */
    function abtestkit_pt_render_product_post_content_for_display( int $post_id ) : string {
        $post_obj = get_post( $post_id );
        if ( ! $post_obj || empty( $post_obj->post_content ) ) {
            return '';
        }

        $orig_post = $GLOBALS['post'] ?? null;

        // Switch global post context to the requested product post.
        $GLOBALS['post'] = $post_obj;
        setup_postdata( $post_obj );

        // Prevent recursion / double swaps.
        remove_filter( 'the_content', 'abtestkit_pt_shadow_the_content', ABTESTKIT_PT_SHADOW_CONTENT_PRIORITY );

        // Let Elementor/ACF/shortcodes/blocks run as they normally would for THIS post.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally invoking core 'the_content' filters to render builder content for the product post.
        $html = apply_filters( 'the_content', $post_obj->post_content );

        // Restore.
        add_filter( 'the_content', 'abtestkit_pt_shadow_the_content', ABTESTKIT_PT_SHADOW_CONTENT_PRIORITY );
        wp_reset_postdata();

        if ( $orig_post ) {
            $GLOBALS['post'] = $orig_post;
        }

        return is_string( $html ) ? $html : '';
    }

    /**
     * Back-compat wrapper used elsewhere in the file.
     */
    function abtestkit_pt_render_shadow_content_for_display( int $shadow_id ) : string {
        return abtestkit_pt_render_product_post_content_for_display( $shadow_id );
    }

    function abtestkit_pt_shadow_normalize_content_for_compare( string $html ) : string {
        $html = preg_replace( '/<!--(.|\\s)*?-->/', '', (string) $html );
        $html = preg_replace( '/\\s+/', ' ', trim( (string) $html ) );
        return is_string( $html ) ? $html : '';
    }

    function abtestkit_pt_get_cached_rendered_product_content( int $post_id, string $scope = 'shadow' ) : string {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return '';
        }

        $token = $scope . ':' . abtestkit_pt_shadow_rev_token( $post_id );
        $cache_key = abtestkit_pt_shadow_cache_key( $post_id, $token );
        $cached = abtestkit_pt_shadow_cache_get( $cache_key );

        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $html = abtestkit_pt_render_product_post_content_for_display( $post_id );
        $html = is_string( $html ) ? trim( $html ) : '';

        if ( $html !== '' ) {
            abtestkit_pt_shadow_cache_set( $cache_key, $html );
        }

        return $html;
    }

    function abtestkit_pt_shadow_description_tab_callback() : void {
        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['shadow_id'] ) ) {
            the_content();
            return;
        }

        $html = abtestkit_pt_get_cached_rendered_product_content( (int) $ctx['shadow_id'], 'shadow-desc' );
        if ( $html !== '' ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered through the normal content pipeline.
            return;
        }

        the_content();
    }

    function abtestkit_pt_shadow_product_tabs( $tabs ) {
        if ( ! is_array( $tabs ) ) {
            return $tabs;
        }

        if ( is_admin() ) {
            return $tabs;
        }

        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return $tabs;
        }

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $tabs;
        }

        if ( (int) get_the_ID() !== (int) $ctx['control_id'] ) {
            return $tabs;
        }

        if ( isset( $tabs['description'] ) && is_array( $tabs['description'] ) ) {
            $tabs['description']['callback'] = 'abtestkit_pt_shadow_description_tab_callback';
        }

        return $tabs;
    }

    /**
     * Swap the final rendered product description content to Version B ONLY when
     * the content currently being filtered matches the control product's own
     * rendered description/content output.
     *
     * This avoids replacing larger template fragments on themes/builders that may
     * also pass other product-page HTML through the_content().
     */
    function abtestkit_pt_shadow_the_content( $content ) {
        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $content;
        }

        if ( is_admin() ) {
            return $content;
        }

        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return $content;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];
        $current_id = (int) get_the_ID();

        if ( $control_id <= 0 || $shadow_id <= 0 || $current_id !== $control_id ) {
            return $content;
        }

        static $compare_cache = [];

        if ( ! isset( $compare_cache[ $control_id ] ) ) {
            $control_html = abtestkit_pt_get_cached_rendered_product_content( $control_id, 'control-desc' );
            $compare_cache[ $control_id ] = abtestkit_pt_shadow_normalize_content_for_compare( $control_html );
        }

        $current_html = abtestkit_pt_shadow_normalize_content_for_compare( (string) $content );
        if ( $current_html === '' || $current_html !== $compare_cache[ $control_id ] ) {
            return $content;
        }

        $shadow_html = abtestkit_pt_get_cached_rendered_product_content( $shadow_id, 'shadow-desc' );
        return $shadow_html !== '' ? $shadow_html : $content;
    }

    /**
     * Bump a lightweight revision flag whenever a shadow product is saved.
     * This avoids needing to diff meta (ACF etc.) — cache key changes automatically.
     */
    add_action( 'save_post_product', function( $post_id, $post, $update ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return;
        }

        // Only for shadow products.
        if ( function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $post_id ) ) {
            update_post_meta( $post_id, '_abtestkit_rev', (string) time() );
        }
    }, 20, 3 );

    // If ACF is installed, bump on ACF saves too (covers edge cases).
    if ( function_exists( 'acf_add_action' ) ) {
        add_action( 'acf/save_post', function( $post_id ) {
            $pid = is_numeric( $post_id ) ? (int) $post_id : 0;
            if ( $pid <= 0 ) {
                return;
            }

            if ( function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
                update_post_meta( $pid, '_abtestkit_rev', (string) time() );
            }
        }, 20 );
    }
}

/**
 * Enable Version B (shadow product) when needed.
 * IMPORTANT:
 * - Hook on 'wp' (earlier than template_redirect) so Elementor/meta consumers see the shadowed meta.
 * - Also shadow WP fields used by Woo templates: title + excerpt.
 */
if ( ! function_exists( 'abtestkit_pt_shadow_the_title' ) ) {
    function abtestkit_pt_shadow_the_title( $title, $post_id ) {
        if ( is_admin() ) return $title;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return $title;

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return $title;

        if ( (int) $post_id !== (int) $ctx['control_id'] ) return $title;

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_title ) && $shadow_post->post_title !== '' ) {
            return $shadow_post->post_title;
        }

        return $title;
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_get_the_excerpt' ) ) {
    function abtestkit_pt_shadow_get_the_excerpt( $excerpt, $post ) {
        if ( is_admin() ) return $excerpt;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return $excerpt;

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return $excerpt;

        $pid = 0;
        if ( $post instanceof WP_Post ) {
            $pid = (int) $post->ID;
        } elseif ( is_numeric( $post ) ) {
            $pid = (int) $post;
        } else {
            $pid = (int) get_the_ID();
        }

        if ( $pid !== (int) $ctx['control_id'] ) return $excerpt;

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_excerpt ) && $shadow_post->post_excerpt !== '' ) {
            return $shadow_post->post_excerpt;
        }

        return $excerpt;
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_woocommerce_short_description' ) ) {
    function abtestkit_pt_shadow_woocommerce_short_description( $desc ) {
        if ( is_admin() ) return $desc;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return $desc;

        $ctx = abtestkit_pt_shadow_ctx_get();
        if ( empty( $ctx['shadow_id'] ) ) return $desc;

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_excerpt ) && $shadow_post->post_excerpt !== '' ) {
            return $shadow_post->post_excerpt;
        }

        return $desc;
    }
}

if ( ! function_exists( 'abtestkit_pt_shadow_maybe_activate' ) ) {
    function abtestkit_pt_shadow_maybe_activate() {
        static $did = false;
        if ( $did ) return;

        if ( is_admin() || is_feed() || is_embed() ) return;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

        $control_id = (int) get_queried_object_id();
        if ( $control_id <= 0 ) return;

        // Decide variant/test (supports preview force flags too).
        [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $control_id );
        if ( ! is_array( $test ) || ( $test['kind'] ?? '' ) !== 'product' || $variant !== 'B' ) return;

        // Do NOT depend on wc_get_product() for draft shadows; use the stored ID directly.
        $shadow_id = isset( $test['variant_id'] ) ? (int) $test['variant_id'] : 0;
        if ( $shadow_id <= 0 || $shadow_id === $control_id ) return;

        abtestkit_pt_shadow_ctx_set( $control_id, $shadow_id, (array) $test );

        // Register once.
        $did = true;

        // Shadow meta (Elementor/ACF/builder data mostly lives in post meta).
        add_filter( 'get_post_metadata', 'abtestkit_pt_shadow_get_post_metadata', 9999, 4 );

        // ACF: make get_field()/Elementor ACF integrations resolve against the shadow product.
        add_filter( 'acf/pre_load_post_id', 'abtestkit_pt_shadow_acf_pre_load_post_id', 9999, 2 );

        // Shadow WP fields used by Woo templates.
        add_filter( 'the_title', 'abtestkit_pt_shadow_the_title', 9999, 2 );
        add_filter( 'get_the_excerpt', 'abtestkit_pt_shadow_get_the_excerpt', 9999, 2 );
        add_filter( 'woocommerce_short_description', 'abtestkit_pt_shadow_woocommerce_short_description', 1 );

        // Shadow main product content/tab content (when it *is* used).
        add_filter( 'the_content', 'abtestkit_pt_shadow_the_content', 9999 );
        add_filter( 'woocommerce_product_tabs', 'abtestkit_pt_shadow_product_tabs', 9999 );
    }
}

add_action( 'wp', 'abtestkit_pt_shadow_maybe_activate', 1 );

// ───────────────────────────────────────────────────────────
// Page/Post draft shadow overlay (single public URL like products)
// - Keep A permalink public
// - When visitor is assigned B, render the shadow draft's content/meta on A
// - Avoid redirecting live traffic to the draft B permalink
// ───────────────────────────────────────────────────────────

if ( ! function_exists( 'abtestkit_pt_page_shadow_ctx_get' ) ) {
    function abtestkit_pt_page_shadow_ctx_get() : array {
        $ctx = $GLOBALS['abtestkit_pt_page_shadow_ctx'] ?? null;
        return is_array( $ctx ) ? $ctx : [];
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_ctx_set' ) ) {
    function abtestkit_pt_page_shadow_ctx_set( int $control_id, int $shadow_id, array $test ) : void {
        $GLOBALS['abtestkit_pt_page_shadow_ctx'] = [
            'control_id' => $control_id,
            'shadow_id'  => $shadow_id,
            'test_id'    => isset( $test['id'] ) ? (string) $test['id'] : '',
        ];
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_get_post_metadata' ) ) {
    function abtestkit_pt_page_shadow_get_post_metadata( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $value;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $value;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];
        $pid        = (int) $object_id;

        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id || $pid !== $control_id ) {
            return $value;
        }

        static $guard = false;
        if ( $guard ) {
            return $value;
        }

        // Never shadow these housekeeping/meta relationship keys.
        $protected = [
            '_abtestkit_shadow',
            '_abtestkit_shadow_of',
            '_abtestkit_variant_in_use',
            '_abtestkit_variant_of',
            '_abtestkit_variant_test_id',
        ];

        if ( ! is_string( $meta_key ) || $meta_key === '' ) {
            $guard = true;
            $shadow_all  = get_post_meta( $shadow_id );
            $control_all = get_post_meta( $control_id );
            $guard = false;

            if ( ! is_array( $shadow_all ) ) {
                return $value;
            }

            foreach ( $protected as $k ) {
                if ( isset( $control_all[ $k ] ) ) {
                    $shadow_all[ $k ] = $control_all[ $k ];
                } else {
                    unset( $shadow_all[ $k ] );
                }
            }

            return $shadow_all;
        }

        if ( in_array( $meta_key, $protected, true ) ) {
            return $value;
        }

        $guard = true;
        $shadow_val = get_post_meta( $shadow_id, $meta_key, $single );
        $guard = false;

        if ( $single ) {
            if ( $shadow_val === '' || $shadow_val === null ) {
                return $value;
            }
            return [ $shadow_val ];
        }

        return ( is_array( $shadow_val ) && ! empty( $shadow_val ) ) ? $shadow_val : $value;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_acf_pre_load_post_id' ) ) {
    function abtestkit_pt_page_shadow_acf_pre_load_post_id( $null, $post_id ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $null;
        }

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) {
            return $null;
        }

        $control_id = (int) $ctx['control_id'];
        $shadow_id  = (int) $ctx['shadow_id'];

        if ( $control_id <= 0 || $shadow_id <= 0 || $control_id === $shadow_id ) {
            return $null;
        }

        $pid = 0;

        if ( is_numeric( $post_id ) ) {
            $pid = (int) $post_id;
        } elseif ( is_string( $post_id ) && preg_match( '/_(\\d+)$/', $post_id, $m ) ) {
            $pid = (int) $m[1];
        }

        if ( $pid !== $control_id ) {
            return $null;
        }

        return $shadow_id;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_the_title' ) ) {
    function abtestkit_pt_page_shadow_the_title( $title, $post_id ) {
        if ( is_admin() ) return $title;
        if ( ! is_singular( [ 'page', 'post' ] ) ) return $title;

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return $title;

        if ( (int) $post_id !== (int) $ctx['control_id'] ) return $title;

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_title ) && $shadow_post->post_title !== '' ) {
            return $shadow_post->post_title;
        }

        return $title;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_get_the_excerpt' ) ) {
    function abtestkit_pt_page_shadow_get_the_excerpt( $excerpt, $post ) {
        if ( is_admin() ) return $excerpt;
        if ( ! is_singular( [ 'page', 'post' ] ) ) return $excerpt;

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return $excerpt;

        $pid = 0;
        if ( $post instanceof WP_Post ) {
            $pid = (int) $post->ID;
        } elseif ( is_numeric( $post ) ) {
            $pid = (int) $post;
        } else {
            $pid = (int) get_the_ID();
        }

        if ( $pid !== (int) $ctx['control_id'] ) return $excerpt;

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_excerpt ) && $shadow_post->post_excerpt !== '' ) {
            return $shadow_post->post_excerpt;
        }

        return $excerpt;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_the_content' ) ) {
    function abtestkit_pt_page_shadow_the_content( $content ) {
        if ( is_admin() ) return $content;
        if ( ! is_singular( [ 'page', 'post' ] ) ) return $content;

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return $content;

        $current_id = (int) get_the_ID();
        if ( $current_id !== (int) $ctx['control_id'] ) {
            return $content;
        }

        $shadow_post = get_post( (int) $ctx['shadow_id'] );
        if ( $shadow_post && isset( $shadow_post->post_content ) ) {
            return $shadow_post->post_content;
        }

        return $content;
    }
}

if ( ! function_exists( 'abtestkit_pt_page_shadow_activate' ) ) {
    function abtestkit_pt_page_shadow_activate() : void {
        static $did = false;
        if ( $did ) return;

        if ( is_admin() || is_feed() || is_embed() ) return;
        if ( ! is_singular( [ 'page', 'post' ] ) ) return;

        $ctx = abtestkit_pt_page_shadow_ctx_get();
        if ( empty( $ctx['control_id'] ) || empty( $ctx['shadow_id'] ) ) return;

        $did = true;

        add_filter( 'get_post_metadata', 'abtestkit_pt_page_shadow_get_post_metadata', 9999, 4 );
        add_filter( 'acf/pre_load_post_id', 'abtestkit_pt_page_shadow_acf_pre_load_post_id', 9999, 2 );
        add_filter( 'the_title', 'abtestkit_pt_page_shadow_the_title', 9999, 2 );
        add_filter( 'get_the_excerpt', 'abtestkit_pt_page_shadow_get_the_excerpt', 9999, 2 );

        // Priority 1 lets normal WP content filters still render blocks/shortcodes afterwards.
        add_filter( 'the_content', 'abtestkit_pt_page_shadow_the_content', 1 );
    }
}

// Optional: enqueue Elementor CSS for page/post shadow variants too.
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( [ 'page', 'post' ] ) ) return;

    $ctx = abtestkit_pt_page_shadow_ctx_get();
    if ( empty( $ctx['shadow_id'] ) ) return;

    $shadow_id = (int) $ctx['shadow_id'];
    if ( $shadow_id <= 0 ) return;

    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) return;

    $rel  = '/elementor/css/post-' . $shadow_id . '.css';
    $path = rtrim( (string) $uploads['basedir'], '/' ) . $rel;
    $url  = rtrim( (string) $uploads['baseurl'], '/' ) . $rel;

    if ( file_exists( $path ) ) {
        $ver = (string) @filemtime( $path );
        wp_enqueue_style( 'abtestkit-pt-page-shadow-' . $shadow_id, $url, [], $ver );
    }
}, 100 );

/**
 * Optional: Elementor per-post CSS file for the shadow.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

    $ctx = abtestkit_pt_shadow_ctx_get();
    if ( empty( $ctx['shadow_id'] ) ) return;

    $shadow_id = (int) $ctx['shadow_id'];
    if ( $shadow_id <= 0 ) return;

    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) return;

    $rel  = '/elementor/css/post-' . $shadow_id . '.css';
    $path = rtrim( (string) $uploads['basedir'], '/' ) . $rel;
    $url  = rtrim( (string) $uploads['baseurl'], '/' ) . $rel;

    if ( file_exists( $path ) ) {
        $ver = (string) @filemtime( $path );
        wp_enqueue_style( 'abtestkit-pt-elementor-shadow-' . $shadow_id, $url, [], $ver );
    }
}, 100 );

        /**
     * When visitor is Variant B on a product test, attach shadow product ID to the cart item.
     * Product ID remains the REAL product (A), so SKU/stock/orders stay correct.
     */

add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id, $variation_id ) {
            $control_product_id = function_exists( 'abtestkit_pt_get_product_test_control_id' )
                ? abtestkit_pt_get_product_test_control_id( (int) $product_id )
                : (int) $product_id;

            [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $control_product_id );

            if ( $test && in_array( $variant, [ 'A', 'B' ], true ) ) {
                $cart_item_data['abtestkit_pt_id']      = (string) $test['id'];
                $cart_item_data['abtestkit_pt_variant'] = ( $variant === 'B' ? 'B' : 'A' );

                if ( $variation_id > 0 ) {
                    $cart_item_data['abtestkit_control_variation_id'] = (int) $variation_id;
                }

                if ( $variant === 'B' ) {
                    $shadow = abtestkit_pt_get_shadow_product_for_test( $test );
                    if ( $shadow ) {
                        $cart_item_data['abtestkit_shadow_product_id'] = (int) $shadow->get_id();

                        if ( $variation_id > 0 && function_exists( 'abtestkit_pt_get_matching_shadow_variation_id' ) ) {
                            $shadow_variation_id = abtestkit_pt_get_matching_shadow_variation_id(
                                (int) $variation_id,
                                (int) $shadow->get_id()
                            );

                            if ( $shadow_variation_id > 0 ) {
                                $cart_item_data['abtestkit_shadow_variation_id'] = (int) $shadow_variation_id;
                            }
                        }
                    }
                }

                return $cart_item_data;
            }

            // Reusable Section Tests are not tied to one product ID.
            // If the visitor saw a tested shortcode section before adding to cart,
            // attribute the later order/revenue to that reusable section test.
            foreach ( abtestkit_pt_all() as $maybe_reusable ) {
                if ( ! is_array( $maybe_reusable ) ) {
                    continue;
                }

                if ( ( $maybe_reusable['status'] ?? 'paused' ) !== 'running' ) {
                    continue;
                }

                if ( ( $maybe_reusable['kind'] ?? '' ) !== 'reusable_section' ) {
                    continue;
                }

                $test_id = isset( $maybe_reusable['id'] ) ? abtestkit_sanitize_test_id( (string) $maybe_reusable['id'] ) : '';
                if ( $test_id === '' ) {
                    continue;
                }

                $cookie_name = 'abtestkit_reusable_seen_' . $test_id;
                $raw         = abtestkit_safe_get_cookie_value( $cookie_name );

                if ( $raw === '' ) {
                    continue;
                }

                $decoded = json_decode( $raw, true );
                if ( ! is_array( $decoded ) ) {
                    continue;
                }

                $seen_variant = isset( $decoded['variant'] ) ? sanitize_text_field( (string) $decoded['variant'] ) : '';
                if ( ! in_array( $seen_variant, [ 'A', 'B' ], true ) ) {
                    continue;
                }

                $cart_item_data['abtestkit_pt_id']      = $test_id;
                $cart_item_data['abtestkit_pt_variant'] = $seen_variant;

                break;
            }

            return $cart_item_data;
        }, 10, 3 );

$abtestkit_pt_apply_runtime_price = function( $price, $product, $getter ) {

    $pid = ( $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    if ( $pid > 0 && function_exists( 'abtestkit_is_shadow_product' ) && abtestkit_is_shadow_product( $pid ) ) {
        return $price;
    }

    static $in = false;
    if ( $in ) return $price;

    $in = true;
    [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
    $shadow = ( $test && $variant === 'B' && function_exists( 'abtestkit_pt_get_shadow_product_for_runtime' ) )
        ? abtestkit_pt_get_shadow_product_for_runtime( $product, $test )
        : null;
    $in = false;

    if ( ! ( $shadow instanceof WC_Product ) ) return $price;
    if ( $pid > 0 && (int) $shadow->get_id() === $pid ) return $price;
    if ( ! method_exists( $shadow, $getter ) ) return $price;

    $p = $shadow->{$getter}( 'edit' );
    return ( $p !== '' && $p !== null ) ? $p : $price;

};

add_filter( 'woocommerce_product_get_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_price' );
}, 10, 2 );

add_filter( 'woocommerce_product_variation_get_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_price' );
}, 10, 2 );

add_filter( 'woocommerce_product_get_regular_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_regular_price' );
}, 10, 2 );

add_filter( 'woocommerce_product_variation_get_regular_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_regular_price' );
}, 10, 2 );

add_filter( 'woocommerce_product_get_sale_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_sale_price' );
}, 10, 2 );

add_filter( 'woocommerce_product_variation_get_sale_price', function( $price, $product ) use ( $abtestkit_pt_apply_runtime_price ) {
    return $abtestkit_pt_apply_runtime_price( $price, $product, 'get_sale_price' );
}, 10, 2 );

    /**
     * Resolve/display Version B product data for Woo cart items.
     */
    if ( ! function_exists( 'abtestkit_pt_cart_shadow_product' ) ) {
        function abtestkit_pt_cart_shadow_product( array $cart_item ) {
            if ( empty( $cart_item['abtestkit_shadow_product_id'] ) || ! function_exists( 'wc_get_product' ) ) {
                return null;
            }

            $shadow_display = function_exists( 'abtestkit_pt_get_shadow_product_for_cart_item' )
                ? abtestkit_pt_get_shadow_product_for_cart_item( $cart_item )
                : null;

            if ( $shadow_display instanceof WC_Product ) {
                return $shadow_display;
            }

            if ( ! empty( $cart_item['abtestkit_shadow_variation_id'] ) ) {
                $shadow_variation = wc_get_product( (int) $cart_item['abtestkit_shadow_variation_id'] );
                if ( $shadow_variation instanceof WC_Product ) {
                    return $shadow_variation;
                }
            }

            $shadow_parent = wc_get_product( (int) $cart_item['abtestkit_shadow_product_id'] );
            return ( $shadow_parent instanceof WC_Product ) ? $shadow_parent : null;
        }
    }

    if ( ! function_exists( 'abtestkit_pt_cart_shadow_price' ) ) {
        function abtestkit_pt_cart_shadow_price( array $cart_item ) {
            $shadow_product = abtestkit_pt_cart_shadow_product( $cart_item );

            if ( ! ( $shadow_product instanceof WC_Product ) ) {
                return null;
            }

            $price = $shadow_product->get_price( 'edit' );

            if ( ( $price === '' || $price === null ) && ! empty( $cart_item['abtestkit_shadow_product_id'] ) && function_exists( 'wc_get_product' ) ) {
                $shadow_parent = wc_get_product( (int) $cart_item['abtestkit_shadow_product_id'] );
                if ( $shadow_parent instanceof WC_Product ) {
                    $price = $shadow_parent->get_price( 'edit' );
                }
            }

            if ( $price === '' || $price === null ) {
                return null;
            }

            return (float) wc_format_decimal( $price );
        }
    }

    if ( ! function_exists( 'abtestkit_pt_cart_has_shadow_items' ) ) {
        function abtestkit_pt_cart_has_shadow_items( $cart ): bool {
            if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
                return false;
            }

            foreach ( $cart->get_cart() as $cart_item ) {
                if ( ! empty( $cart_item['abtestkit_shadow_product_id'] ) ) {
                    return true;
                }
            }

            return false;
        }
    }

    if ( ! function_exists( 'abtestkit_pt_force_shadow_cart_item_price' ) ) {
        function abtestkit_pt_force_shadow_cart_item_price( array &$cart_item ) : bool {
            if ( empty( $cart_item['abtestkit_shadow_product_id'] ) ) {
                return false;
            }

            $b_price = abtestkit_pt_cart_shadow_price( $cart_item );

            if ( $b_price === null ) {
                return false;
            }

            if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
                $cart_item['data']->set_price( $b_price );
                return true;
            }

            return false;
        }
    }

    if ( ! function_exists( 'abtestkit_pt_force_shadow_cart_prices' ) ) {
        function abtestkit_pt_force_shadow_cart_prices( $cart ): bool {
            if ( is_admin() && ! wp_doing_ajax() ) {
                return false;
            }

            if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
                return false;
            }

            $changed = false;

            foreach ( $cart->get_cart() as $cart_item ) {
                if ( abtestkit_pt_force_shadow_cart_item_price( $cart_item ) ) {
                    $changed = true;
                }
            }

            return $changed;
        }
    }

    if ( ! function_exists( 'abtestkit_pt_format_shadow_cart_price' ) ) {
        function abtestkit_pt_format_shadow_cart_price( array $cart_item, int $qty = 1 ) {
            $shadow_product = abtestkit_pt_cart_shadow_product( $cart_item );
            $b_price        = abtestkit_pt_cart_shadow_price( $cart_item );

            if ( ! ( $shadow_product instanceof WC_Product ) || $b_price === null ) {
                return '';
            }

            $qty           = max( 1, $qty );
            $display_price = function_exists( 'wc_get_price_to_display' )
                ? wc_get_price_to_display( $shadow_product, [ 'qty' => $qty, 'price' => $b_price ] )
                : ( $b_price * $qty );

            return wc_price( $display_price );
        }
    }

    /**
     * Set the B price as soon as Woo creates/loads the cart item.
     * This is the server-side fix for themes that add to cart through admin-ajax.php
     * and then build fragments before the normal full-page cart path runs.
     */
    add_filter( 'woocommerce_add_cart_item', function( $cart_item ) {
        if ( is_array( $cart_item ) ) {
            abtestkit_pt_force_shadow_cart_item_price( $cart_item );
        }

        return $cart_item;
    }, 20, 1 );

    add_filter( 'woocommerce_get_cart_item_from_session', function( $cart_item, $values, $cart_item_key ) {
        if ( ! is_array( $cart_item ) || ! is_array( $values ) ) {
            return $cart_item;
        }

        foreach ( [
            'abtestkit_pt_id',
            'abtestkit_pt_variant',
            'abtestkit_control_variation_id',
            'abtestkit_shadow_product_id',
            'abtestkit_shadow_variation_id',
        ] as $meta_key ) {
            if ( ! isset( $cart_item[ $meta_key ] ) && isset( $values[ $meta_key ] ) ) {
                $cart_item[ $meta_key ] = $values[ $meta_key ];
            }
        }

        abtestkit_pt_force_shadow_cart_item_price( $cart_item );

        return $cart_item;
    }, 20, 3 );

    /**
     * Ensure cart/checkout totals match Version B price (if B differs).
     * Run early and late so theme/dynamic-pricing hooks cannot accidentally
     * put the A price back onto a B-assigned shadow cart item before totals are built.
     */
    $abtestkit_pt_before_calculate_totals = function( $cart ) {
        abtestkit_pt_force_shadow_cart_prices( $cart );
    };

    add_action( 'woocommerce_before_calculate_totals', $abtestkit_pt_before_calculate_totals, 1 );
    add_action( 'woocommerce_before_calculate_totals', $abtestkit_pt_before_calculate_totals, 9999 );
    /**
     * Keep cart/mini-cart item prices aligned even in AJAX fragment requests
     * where a theme renders fragments before Woo has fully recalculated totals.
     */
    add_filter( 'woocommerce_cart_item_price', function( $price_html, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['abtestkit_shadow_product_id'] ) ) {
            return $price_html;
        }

        $shadow_price_html = abtestkit_pt_format_shadow_cart_price( (array) $cart_item, 1 );
        return ( $shadow_price_html !== '' ) ? $shadow_price_html : $price_html;
    }, 20, 3 );

    add_filter( 'woocommerce_cart_item_subtotal', function( $subtotal_html, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['abtestkit_shadow_product_id'] ) ) {
            return $subtotal_html;
        }

        $qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
        $shadow_subtotal_html = abtestkit_pt_format_shadow_cart_price( (array) $cart_item, $qty );

        return ( $shadow_subtotal_html !== '' ) ? $shadow_subtotal_html : $subtotal_html;
    }, 20, 3 );

    /**
     * Fragment robustness:
     * - prepare the cart state before theme fragment filters build header totals;
     * - regenerate only the standard mini-cart fragment late, because Woo renders it
     *   before this filter is applied;
     * - only overwrite Woodmart-specific fragment keys if Woodmart already added them.
     */
    add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
        if ( function_exists( 'WC' ) && WC()->cart && abtestkit_pt_cart_has_shadow_items( WC()->cart ) ) {
            abtestkit_pt_force_shadow_cart_prices( WC()->cart );
            WC()->cart->calculate_totals();
        }

        return $fragments;
    }, 0 );

    add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart || ! abtestkit_pt_cart_has_shadow_items( WC()->cart ) ) {
            return $fragments;
        }

        static $refreshing = false;

        if ( $refreshing ) {
            return $fragments;
        }

        $refreshing = true;

        abtestkit_pt_force_shadow_cart_prices( WC()->cart );
        WC()->cart->calculate_totals();

        if ( function_exists( 'woocommerce_mini_cart' ) ) {
            ob_start();
            woocommerce_mini_cart();
            $mini_cart = ob_get_clean();

            if ( $mini_cart !== '' ) {
                $fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';
            }
        }

        if ( array_key_exists( 'span.wd-cart-number_wd', $fragments ) ) {
            $count       = WC()->cart->get_cart_contents_count();
            $count_label = _n( 'item', 'items', $count, 'abtestkit' );

            $fragments['span.wd-cart-number_wd'] = sprintf(
                "\t\t<span class=\"wd-cart-number wd-tools-count\">%d <span>%s</span></span>\n\t\t",
                (int) $count,
                esc_html( $count_label )
            );
        }

        if ( array_key_exists( 'span.wd-cart-subtotal_wd', $fragments ) ) {
            $fragments['span.wd-cart-subtotal_wd'] = "\t\t<span class=\"wd-cart-subtotal\">" . WC()->cart->get_cart_subtotal() . "</span>\n\t\t";
        }

        $refreshing = false;

        return $fragments;
    }, 9999 );

    /**
     * Display B title + thumbnail in cart/checkout.
     */
    add_filter( 'woocommerce_cart_item_name', function( $name, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['abtestkit_shadow_product_id'] ) ) return $name;

        $shadow_display = abtestkit_pt_cart_shadow_product( (array) $cart_item );

        if ( $shadow_display instanceof WC_Product ) {
            $shadow_name = $shadow_display->get_name( 'edit' );
            if ( $shadow_name !== '' ) {
                return $shadow_name;
            }
        }

        $shadow_id = (int) $cart_item['abtestkit_shadow_product_id'];
        $t = get_the_title( $shadow_id );
        return $t ? $t : $name;
    }, 10, 3 );

    add_filter( 'woocommerce_cart_item_thumbnail', function( $thumb, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['abtestkit_shadow_product_id'] ) ) return $thumb;
        if ( ! function_exists( 'wc_get_product' ) ) return $thumb;

        $shadow_display = abtestkit_pt_cart_shadow_product( (array) $cart_item );

        $iid = 0;
        if ( $shadow_display instanceof WC_Product ) {
            $iid = (int) $shadow_display->get_image_id( 'edit' );
        }

        if ( $iid <= 0 ) {
            $shadow_parent = wc_get_product( (int) $cart_item['abtestkit_shadow_product_id'] );
            if ( $shadow_parent instanceof WC_Product ) {
                $iid = (int) $shadow_parent->get_image_id( 'edit' );
            }
        }

        if ( $iid <= 0 ) return $thumb;

        return wp_get_attachment_image( $iid, 'woocommerce_thumbnail' );
    }, 10, 3 );

    /**
     * Persist variant info into the order line item so orders can be attributed back to A/B.
     */
    add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['abtestkit_shadow_product_id'] ) ) {
            $item->add_meta_data( '_abtestkit_shadow_product_id', (int) $values['abtestkit_shadow_product_id'], true );
        }

        if ( ! empty( $values['abtestkit_shadow_variation_id'] ) ) {
            $item->add_meta_data( '_abtestkit_shadow_variation_id', (int) $values['abtestkit_shadow_variation_id'], true );
        }

        if ( ! empty( $values['abtestkit_control_variation_id'] ) ) {
            $item->add_meta_data( '_abtestkit_control_variation_id', (int) $values['abtestkit_control_variation_id'], true );
        }

        if ( ! empty( $values['abtestkit_pt_id'] ) ) {
            $item->add_meta_data( '_abtestkit_pt_id', (string) $values['abtestkit_pt_id'], true );
        }

        if ( ! empty( $values['abtestkit_pt_variant'] ) && in_array( $values['abtestkit_pt_variant'], [ 'A', 'B' ], true ) ) {
            $item->add_meta_data( '_abtestkit_pt_variant', (string) $values['abtestkit_pt_variant'], true );
        }
    }, 10, 4 );

    /**
     * Log one purchase event per order per test.
     * Revenue is based on the matched product line total after discounts, excluding shipping.
     */
    function abtestkit_log_wc_purchase_for_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order_id = absint( $order_id );
        if ( $order_id <= 0 ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $grouped = [];

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
                continue;
            }

            $test_id = sanitize_text_field( (string) $item->get_meta( '_abtestkit_pt_id', true ) );
            $variant = sanitize_text_field( (string) $item->get_meta( '_abtestkit_pt_variant', true ) );

            if ( $test_id === '' || ! in_array( $variant, [ 'A', 'B' ], true ) ) {
                continue;
            }

            $test = abtestkit_pt_get( $test_id );
            if ( ! is_array( $test ) ) {
                continue;
            }

            $goal = isset( $test['goal'] ) ? sanitize_key( (string) $test['goal'] ) : '';
            if ( $goal !== 'purchase' ) {
                continue;
            }

            $line_total = 0.0;
            if ( method_exists( $item, 'get_total' ) ) {
                $line_total = (float) $item->get_total();
            }

            if ( ! isset( $grouped[ $test_id ] ) ) {
                $grouped[ $test_id ] = [
                    'variant' => $variant,
                    'amount'  => 0.0,
                    'post_id' => isset( $test['control_id'] ) ? (int) $test['control_id'] : 0,
                ];
            }

            $grouped[ $test_id ]['amount'] += $line_total;
        }

        foreach ( $grouped as $test_id => $row ) {
            $meta_key = '_abtestkit_purchase_logged_' . sanitize_key( (string) $test_id );

            if ( $order->get_meta( $meta_key, true ) ) {
                continue;
            }

            abtestkit_log_event_to_db(
                'purchase',
                (int) $row['post_id'],
                (string) $test_id,
                (string) $row['variant'],
                [
                    'order_id' => $order_id,
                    'amount'   => (float) $row['amount'],
                ]
            );

            $order->update_meta_data( $meta_key, 1 );
        }

        $order->save();
    }

    add_action( 'woocommerce_order_status_processing', 'abtestkit_log_wc_purchase_for_order', 10, 1 );
    add_action( 'woocommerce_order_status_completed', 'abtestkit_log_wc_purchase_for_order', 10, 1 );

    // Show B name on order line items (emails / order received / My Account).
    add_filter( 'woocommerce_order_item_name', function( $name, $item ) {
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) return $name;
        if ( ! function_exists( 'wc_get_product' ) ) return $name;

        $shadow_variation_id = (int) $item->get_meta( '_abtestkit_shadow_variation_id', true );
        if ( $shadow_variation_id > 0 ) {
            $shadow_variation = wc_get_product( $shadow_variation_id );
            if ( $shadow_variation instanceof WC_Product ) {
                $shadow_name = $shadow_variation->get_name( 'edit' );
                if ( $shadow_name !== '' ) {
                    return $shadow_name;
                }
            }
        }

        $shadow_id = (int) $item->get_meta( '_abtestkit_shadow_product_id', true );
        if ( $shadow_id <= 0 ) return $name;

        $shadow_product = wc_get_product( $shadow_id );
        if ( $shadow_product instanceof WC_Product ) {
            $shadow_name = $shadow_product->get_name( 'edit' );
            if ( $shadow_name !== '' ) {
                return $shadow_name;
            }
        }

        $t = get_the_title( $shadow_id );
        return $t ? $t : $name;
    }, 10, 2 );

    // --- Extra safety for themes that use the_title() / the_excerpt() directly on products ---

    // Override the_title() for product posts on the frontend so single-product
    // templates and shop loops that use the_title() pick up the B title.
    add_filter( 'the_title', function ( $title, $post_id ) {
        if ( is_admin() ) {
            return $title;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return $title;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return $title;
        }

        [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
        if ( ! $test || $variant !== 'B' ) {
            return $title;
        }

        $overrides = ( isset( $test['overrides'] ) && is_array( $test['overrides'] ) ) ? $test['overrides'] : [];
        if ( isset( $overrides['title'] ) && $overrides['title'] !== '' ) {
            return $overrides['title'];
        }

        return $title;
    }, 20, 2 );

    // Override get_the_excerpt() for product posts on the frontend so single-product
    // templates and shop loops that use the_excerpt() pick up the B short description.
    add_filter( 'get_the_excerpt', function ( $excerpt, $post ) {
        if ( is_admin() ) {
            return $excerpt;
        }

        if ( ! $post instanceof WP_Post ) {
            return $excerpt;
        }

        if ( $post->post_type !== 'product' ) {
            return $excerpt;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return $excerpt;
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return $excerpt;
        }

        [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
        if ( ! $test || $variant !== 'B' ) {
            return $excerpt;
        }

        $overrides = ( isset( $test['overrides'] ) && is_array( $test['overrides'] ) ) ? $test['overrides'] : [];
        if ( isset( $overrides['short_description'] ) && $overrides['short_description'] !== '' ) {
            return abtestkit_render_product_override_html( $overrides['short_description'] );
        }

        return $excerpt;
    }, 20, 2 );

    // Override the main product description content on single-product pages (B only).
    add_filter( 'the_content', function( $content ) {
        if ( is_admin() ) {
            return $content;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return $content;
        }

        global $post;

        if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'product' ) {
            return $content;
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return $content;
        }

        [ $test, $variant ] = abtestkit_get_active_product_test_for_product( $product );
        if ( ! $test || $variant !== 'B' ) {
            return $content;
        }

        $overrides = ( isset( $test['overrides'] ) && is_array( $test['overrides'] ) ) ? $test['overrides'] : [];

        if ( isset( $overrides['description'] ) && $overrides['description'] !== '' ) {
            return abtestkit_render_product_override_html( $overrides['description'] );
        }

        return $content;
    }, 20, 2 );
} );


// Client-side click tracker for Page Tests (Gutenberg buttons)
    add_action( 'wp_footer', function () {
    // Read-only flag to skip click tracker in wizard iframe.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['abtestkit_preview'] ) ) { return; }

    if ( ! is_singular( [ 'post', 'page', 'product' ] ) ) return;
    if ( abtestkit_is_exempt_viewer() ) return;

    $post_id = get_the_ID();
    [ $test, $role ] = abtestkit_pt_find_by_post( $post_id );
    if ( ! $test ) return;

    $assigned = '';

    if (
        isset( $GLOBALS['abtestkit_current_pt_assignment'] )
        && is_array( $GLOBALS['abtestkit_current_pt_assignment'] )
    ) {
        $ctx      = $GLOBALS['abtestkit_current_pt_assignment'];
        $ctx_test = $ctx['test'] ?? null;
        $ctx_id   = ( is_array( $ctx_test ) && isset( $ctx_test['id'] ) ) ? (string) $ctx_test['id'] : '';
        $ctx_var  = isset( $ctx['variant'] ) ? (string) $ctx['variant'] : '';

        if ( $ctx_id !== '' && $ctx_id === (string) $test['id'] && in_array( $ctx_var, [ 'A', 'B' ], true ) ) {
            $assigned = $ctx_var;
        }
    }

    if ( $assigned !== 'A' && $assigned !== 'B' ) {
        // Derive the variant from the page we are rendering.
        // If we're on the variant page, it's B; if on control, it's usually A.
        $assigned = ( $role === 'variant' ) ? 'B' : 'A';
    }

    // Optional: defensive fallback to cookie (read-only; sanitize before use).
    if ( $assigned !== 'A' && $assigned !== 'B' ) {
        $cookie_name = 'abtestkit_pt_' . (string) $test['id'];

        $cookie_val_raw = '';
        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            // Immediately unslash; sanitized below.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            $cookie_val_raw = wp_unslash( $_COOKIE[ $cookie_name ] );
        }

        $cookie_val = sanitize_text_field( $cookie_val_raw );

        if ( in_array( $cookie_val, array( 'A', 'B' ), true ) ) {
            $assigned = $cookie_val;
        } else {
            $assigned = 'A';
        }
    }

    $control_id = (int) $test['control_id'];
    $rest  = esc_url_raw( rest_url( 'abtestkit/v1' ) );
    $nonce = wp_create_nonce( 'wp_rest' );

    // Conversion goal for this page test: 'clicks' | 'form' | 'add_to_cart' | 'destination_url' | 'scroll_depth'
    $goal = isset( $test['goal'] ) ? abtestkit_pt_normalize_goal_for_display( $test['goal'] ) : '';

    // Targets come from the wizard's "links" field: hrefs or CSS selectors
    $targets = array_values(
        array_filter(
            array_map( 'strval', (array) ( $test['links'] ?? [] ) )
        )
    );

    // If goal is WooCommerce add_to_cart and no explicit targets were set,
    // default to common single-product add to cart selectors.
    if ( $goal === 'add_to_cart' && empty( $targets ) ) {
        $targets = [
            'form.cart',
            '.single_add_to_cart_button',
            'form.cart button[type="submit"]',
            'form.cart input[type="submit"]',
        ];
    }

    $scroll_depth = abtestkit_pt_scroll_depth_for_test( $test );
    $engagement_sample_rate = abtestkit_engagement_sample_rate();

    ?>
<script>
(function(){
  var cfg = {
    rest: "<?php echo esc_js( $rest ); ?>",
    nonce: "<?php echo esc_js( $nonce ); ?>",
    postId: <?php echo (int) $control_id; ?>,
    abTestId: "<?php echo esc_js( $test['id'] ); ?>",
    variant: "<?php echo ( $assigned === 'B' ? 'B' : 'A' ); ?>",
    goal: "<?php echo esc_js( $goal ); ?>",
    targets: <?php echo wp_json_encode( $targets ); ?>, // hrefs or CSS selectors
    scrollDepth: <?php echo (int) $scroll_depth; ?>,
    engagementSampleRate: <?php echo wp_json_encode( $engagement_sample_rate ); ?>
  };

  if (!Array.isArray(cfg.targets)) cfg.targets = [];

  // Sample engagement locally, then make at most one small request when the
  // sampled pageview exits. There is no timer-based polling or repeated write.
  (function trackSampledEngagement(){
    var sampleRate = Number(cfg.engagementSampleRate);
    if (
      window.location.protocol !== "https:" ||
      !isFinite(sampleRate) ||
      sampleRate <= 0 ||
      Math.random() >= Math.min(1, sampleRate)
    ) {
      return;
    }

    var sent = false;
    var maxPageDepth = 0;
    var activeMilliseconds = 0;
    var visibleStartedAt = document.visibilityState === "visible" ? Date.now() : 0;
    var depthFrame = 0;

    function updateMaxPageDepth() {
      maxPageDepth = Math.max(maxPageDepth, getScrollDepthPercent());
    }

    function queueDepthUpdate() {
      if (depthFrame) return;
      depthFrame = window.requestAnimationFrame(function(){
        depthFrame = 0;
        updateMaxPageDepth();
      });
    }

    function pauseVisibleClock() {
      if (!visibleStartedAt) return;
      activeMilliseconds += Math.max(0, Date.now() - visibleStartedAt);
      visibleStartedAt = 0;
    }

    function resumeVisibleClock() {
      if (!visibleStartedAt) visibleStartedAt = Date.now();
    }

    function reportEngagement() {
      if (sent) return;
      sent = true;
      pauseVisibleClock();
      updateMaxPageDepth();

      fetch(cfg.rest + "/track?t=" + Date.now(), {
        method: "POST",
        credentials: "same-origin",
        keepalive: true,
        cache: "no-store",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": cfg.nonce
        },
        body: JSON.stringify({
          type: "engagement",
          abTestId: cfg.abTestId,
          postId: cfg.postId,
          index: 0,
          variant: cfg.variant,
          scroll: Math.max(0, Math.min(100, Math.round(maxPageDepth))),
          seconds: Math.max(0, Math.round(activeMilliseconds / 1000)),
          protocol: "https"
        })
      }).catch(function(){});
    }

    document.addEventListener("visibilitychange", function(){
      if (document.visibilityState === "visible") {
        resumeVisibleClock();
        queueDepthUpdate();
      } else {
        pauseVisibleClock();
      }
    }, { passive: true });
    window.addEventListener("scroll", queueDepthUpdate, { passive: true });
    window.addEventListener("resize", queueDepthUpdate, { passive: true });
    window.addEventListener("pagehide", reportEngagement, { once: true });
    window.addEventListener("load", queueDepthUpdate, { once: true, passive: true });
    queueDepthUpdate();
  })();

  // --- Helpers --------------------------------------------------------------
  function trackClickOnce() {
    var key = "ab-pt-clicked-" + cfg.abTestId;
    if (sessionStorage.getItem(key) === "1") return;
    sessionStorage.setItem(key, "1");
    fetch(cfg.rest + "/track?t=" + Date.now(), {
      method: "POST",
      credentials: "same-origin",
      keepalive: true,
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce
      },
      body: JSON.stringify({
        type: "click",
        abTestId: cfg.abTestId,
        postId: cfg.postId,
        index: 0,
        variant: cfg.variant,
        protocol: (window.location.protocol === "https:" ? "https" : "http")
      })
    }).catch(function(){});
  }

  function isCssSelector(s) {
    // Rough check: starts with ., #, [, contains combinators, or starts with a normal clickable/container tag.
    return /^[.#\[]/.test(s) || /\s|>|\+|~/.test(s) || /^(a|button|form|input|select|textarea|img|p|span|div|section|article|main|header|footer|nav)\b/i.test(s);
  }

  function selectorFallbacks(sel) {
    sel = String(sel || '');
    var out = [];

    function add(s) {
      if (s && out.indexOf(s) === -1) {
        out.push(s);
      }
    }

    // Common theme/header logo patterns, including Astra.
    if (sel.indexOf('.custom-logo-link') !== -1) {
      add('a.custom-logo-link, .custom-logo-link');
    }

    if (sel.indexOf('.site-title') !== -1) {
      add('.site-title a, a.site-title');
    }

    if (sel.indexOf('.site-branding') !== -1) {
      add('.site-branding a[href], .site-branding a');
    }

    // Gutenberg button fallback.
    if (sel.indexOf('.wp-block-button__link') !== -1) {
      add('a.wp-block-button__link, .wp-block-button__link');
    }

    // WordPress author/meta links.
    if (sel.indexOf('.posted-by') !== -1 || sel.indexOf('a.url.fn') !== -1) {
      add('.posted-by a, .entry-meta a[href*="/author/"], a.url.fn');
    }

    // WooCommerce / theme add-to-cart buttons, including Woodmart AJAX add to cart.
    if (sel.indexOf('.single_add_to_cart_button') !== -1 || sel.indexOf('.add_to_cart_button') !== -1 || /^button\b/i.test(sel) || /^input\b/i.test(sel)) {
      add('.single_add_to_cart_button, .add_to_cart_button, form.cart button[type="submit"], form.cart input[type="submit"]');
    }

    return out;
  }

  function matchesCssTarget(el, sel) {
    if (!el || !sel || !el.closest) return false;

    try {
      if (el.closest(sel)) return true;
    } catch(e) {
      // Invalid/fragile selector: try safer fallbacks below.
    }

    var fallbacks = selectorFallbacks(sel);
    for (var i = 0; i < fallbacks.length; i++) {
      try {
        if (el.closest(fallbacks[i])) return true;
      } catch(e) {}
    }

    return false;
  }

  function normalizeUrl(u) {
    try {
      var a = document.createElement('a');
      a.href = u;
      // compare path + query (ignore origin + hash), trim trailing slash
      var path = a.pathname.replace(/\/+$/,'') || '/';
      var search = a.search || '';
      return (path + search).toLowerCase();
    } catch(e) {
      return (u || '').toLowerCase();
    }
  }

  function hrefMatches(targetHref, elementHref) {
    var t = normalizeUrl(targetHref);
    var e = normalizeUrl(elementHref);
    if (!t || !e) return false;

    // Allow wildcard suffix: "/pricing*" matches "/pricing" and "/pricing?x=1"
    if (/\*$/.test(t)) {
      t = t.slice(0, -1);

      // Never allow a blank/root wildcard to match every internal URL.
      if (!t) return false;
      if (t === '/') return e === '/';

      return e === t || e.indexOf(t + '/') === 0 || e.indexOf(t + '?') === 0;
    }
    // Exact path+query match
    return e === t;
  }

  function matchesAnyTarget(el) {
    if (!cfg.targets.length || !el) return false;

    // If it's an <a>, try href matches first
    var anchor = el.closest && el.closest('a');
    if (anchor && anchor.href) {
      for (var i=0; i<cfg.targets.length; i++) {
        var t = cfg.targets[i];
        if (!t) continue;
        if (!isCssSelector(t) && hrefMatches(t, anchor.href)) return true;
      }
    }

    // CSS selectors: match by closest()
    for (var j=0; j<cfg.targets.length; j++) {
      var sel = cfg.targets[j];
      if (!sel) continue;
      if (isCssSelector(sel) && matchesCssTarget(el, sel)) {
        return true;
      }
    }
    return false;
  }

  // For goals handled elsewhere, avoid attaching these generic click/submit handlers.
  // - add_to_cart is tracked centrally in assets/js/frontend.js.
  // - destination_url is tracked server-side when the visitor lands on the destination.
  if (cfg.goal === "add_to_cart" || cfg.goal === "destination_url") {
    return;
  }

  if (window.location.protocol !== "https:") {
    return;
  }

  function getScrollDepthPercent() {
    var doc = document.documentElement;
    var body = document.body;
    var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
    var viewport = window.innerHeight || doc.clientHeight || 0;
    var height = Math.max(
      body ? body.scrollHeight : 0,
      body ? body.offsetHeight : 0,
      doc ? doc.clientHeight : 0,
      doc ? doc.scrollHeight : 0,
      doc ? doc.offsetHeight : 0
    );

    if (!height || height <= viewport) {
      return 100;
    }

    return Math.min(100, Math.round(((scrollTop + viewport) / height) * 100));
  }

  function maybeTrackScrollDepth() {
    var depth = parseInt(cfg.scrollDepth, 10);
    if (!depth || depth < 1) depth = 50;

    if (getScrollDepthPercent() >= depth) {
      trackClickOnce();
      window.removeEventListener("scroll", maybeTrackScrollDepth, true);
      window.removeEventListener("resize", maybeTrackScrollDepth, true);
      window.removeEventListener("load", maybeTrackScrollDepth, true);
    }
  }

  if (cfg.goal === "scroll_depth") {
    window.addEventListener("scroll", maybeTrackScrollDepth, { passive: true, capture: true });
    window.addEventListener("resize", maybeTrackScrollDepth, { passive: true, capture: true });
    window.addEventListener("load", maybeTrackScrollDepth, { passive: true, capture: true });
    setTimeout(maybeTrackScrollDepth, 250);
    return;
  }

  // --- Listeners ------------------------------------------------------------
  document.addEventListener("click", function(ev){
    var t = ev.target;
    if (!t || typeof t.closest !== "function") return;

    var clickable = t.closest(
      'a[href],button,[role="button"],[onclick],input[type="submit"],.wp-block-button__link'
    );
    if (!clickable) return;

    if (matchesAnyTarget(clickable)) {
      trackClickOnce();
    }
  }, {passive:true, capture:true});

  // Forms: if a selector target points to a form, track on submit
  document.addEventListener("submit", function(ev){
    var f = ev.target;
    if (!f || !(f instanceof HTMLFormElement)) return;

    // Only bother if user configured any selector that could match forms
    var anyFormish = cfg.targets.some(function(sel){ return sel && isCssSelector(sel); });
    if (!anyFormish) return;

    if (matchesAnyTarget(f)) {
      trackClickOnce();
    }
  }, {capture:true}); // capture to fire even if page handlers stopPropagation

})();
</script>
<?php
}, 99 );

register_uninstall_hook(__FILE__, 'abtestkit_uninstall');
function abtestkit_uninstall() {
    global $wpdb;

    // --- 1) Fully remove ABTestKit-owned runtime/content state ---
    abtestkit_nuke_owned_plugin_state();

    // --- 2) Drop events table ---
    $table     = defined( 'ABTESTKIT_EVENTS_TABLE' ) ? ABTESTKIT_EVENTS_TABLE : ( $wpdb->prefix . 'abtestkit_events' );
    $table_esc = esc_sql( $table );

    // Schema change on a custom table. Identifier is safe (prefix + fixed suffix).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->query( 'DROP TABLE IF EXISTS `' . $table_esc . '`' );

    // --- 3) Strip AB attributes from saved block content ---
    // This ensures blocks re-render as normal Gutenberg blocks everywhere.
    $post_types = get_post_types( [ 'public' => true ], 'names' );

    if ( ! empty( $post_types ) ) {
        foreach ( $post_types as $pt ) {
            $paged = 1;

            do {
                $q = new WP_Query(
                    [
                        'post_type'      => $pt,
                        'post_status'    => 'any',
                        'posts_per_page' => 200,
                        'paged'          => $paged,
                        'no_found_rows'  => true,
                        'fields'         => 'ids',
                    ]
                );

                if ( empty( $q->posts ) ) {
                    break;
                }

                foreach ( $q->posts as $pid ) {
                    $pid     = (int) $pid;
                    $content = get_post_field( 'post_content', $pid );

                    if ( ! $content ) {
                        continue;
                    }

                    $changed = false;
                    $blocks  = parse_blocks( $content );
                    $blocks  = abtestkit_strip_ab_attrs_from_blocks( $blocks, $changed );

                    if ( $changed ) {
                        wp_update_post(
                            [
                                'ID'           => $pid,
                                'post_content' => serialize_blocks( $blocks ),
                            ]
                        );
                    }

                    delete_post_meta( $pid, '_abtestkit_variants' );
                }

                $paged++;
            } while ( true );
        }
    }

    // --- 4) Best-effort cache flush so no stale HTML remains in caches/CDNs ---
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    if ( function_exists( 'w3tc_flush_all' ) ) {
        w3tc_flush_all();
    }

    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }

    if ( function_exists( 'autoptimize_flush_cache' ) ) {
        autoptimize_flush_cache();
    }

    // Purge LiteSpeed Cache via documented external hook.
    if ( class_exists( 'LiteSpeed_Cache' ) ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache external purge hook.
        do_action( 'litespeed_purge_all' );
    }

    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        wp_cache_clear_cache();
    }
}

function abtestkit_pt_evaluation_for_test( array $test ) {
    $test_id    = isset( $test['id'] ) ? (string) $test['id'] : '';
    $control_id = isset( $test['control_id'] ) ? (int) $test['control_id'] : 0;

    if ( $test_id === '' || $control_id <= 0 ) {
        return null;
    }

    $req = new WP_REST_Request( 'GET', '/abtestkit/v1/evaluate' );
    $req->set_param( 'abTestId', $test_id );
    $req->set_param( 'post_id', $control_id );

    $res  = abtestkit_handle_evaluate( $req );
    $data = ( $res instanceof WP_REST_Response ) ? $res->get_data() : (array) $res;

    if ( ! is_array( $data ) || ! isset( $data['probA'], $data['probB'] ) ) {
        return null;
    }

    return [
        'probA'   => (float) $data['probA'],
        'probB'   => (float) $data['probB'],
        'winner'  => isset( $data['winner'] ) ? (string) $data['winner'] : '',
        'message' => isset( $data['message'] ) ? (string) $data['message'] : '',
    ];
}

function abtestkit_events_table_has_column( string $column ) : bool {
    global $wpdb;

    static $columns = null;

    if ( ! is_array( $columns ) ) {
        $table = ABTESTKIT_EVENTS_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = $wpdb->get_col(
            $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ),
            0
        );

        $columns = is_array( $found ) ? $found : [];
    }

    return in_array( $column, $columns, true );
}

function abtestkit_create_event_table() {
    global $wpdb;

    $table           = ABTESTKIT_EVENTS_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        time DATETIME NOT NULL,
        post_id BIGINT,
        ab_test_id VARCHAR(64),
        variant CHAR(1),
        event_type ENUM('impression','click','purchase','engagement','decision','decision_applied','stale','protocol_warning'),
        order_id BIGINT NULL,
        amount DECIMAL(18,2) NULL,
        protocol VARCHAR(10) NULL,
        excluded_reason VARCHAR(50) NULL,
        scroll_pct TINYINT UNSIGNED NULL,
        time_sec INT UNSIGNED NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        KEY idx_time (time),
        KEY idx_post_test (post_id, ab_test_id),
        KEY idx_variant_type (variant, event_type),
        KEY idx_order_id (order_id),
        KEY idx_protocol_reason (protocol, excluded_reason),
        KEY idx_test_type_variant_time (ab_test_id, event_type, variant, time),
        KEY idx_test_time (ab_test_id, time),
        KEY idx_test_variant (ab_test_id, variant)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required schema creation/upgrades for plugin-owned custom table.
    dbDelta( $sql );

    // Force-heal older installs where dbDelta/version flags do not fully catch up.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $columns = $wpdb->get_col(
        $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ),
        0
    );

    // dbDelta can leave an existing ENUM unchanged, so verify the new event
    // type explicitly before engagement rows are accepted on upgraded sites.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $event_type_definition = $wpdb->get_row(
        $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'event_type' ),
        ARRAY_A
    );
    $event_type_sql = is_array( $event_type_definition )
        ? (string) ( $event_type_definition['Type'] ?? $event_type_definition['type'] ?? '' )
        : '';

    if ( $event_type_sql !== '' && strpos( $event_type_sql, "'engagement'" ) === false ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "ALTER TABLE %i MODIFY `event_type` ENUM('impression','click','purchase','engagement','decision','decision_applied','stale','protocol_warning')",
                $table
            )
        );
    }

    if ( is_array( $columns ) ) {
        if ( ! in_array( 'order_id', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `order_id` BIGINT NULL AFTER `event_type`',
                    $table
                )
            );
        }

        if ( ! in_array( 'amount', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `amount` DECIMAL(18,2) NULL AFTER `order_id`',
                    $table
                )
            );
        }

        if ( ! in_array( 'protocol', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `protocol` VARCHAR(10) NULL AFTER `amount`',
                    $table
                )
            );
        }

        if ( ! in_array( 'excluded_reason', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `excluded_reason` VARCHAR(50) NULL AFTER `protocol`',
                    $table
                )
            );
        }

        if ( ! in_array( 'scroll_pct', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `scroll_pct` TINYINT UNSIGNED NULL AFTER `excluded_reason`',
                    $table
                )
            );
        }

        if ( ! in_array( 'time_sec', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD COLUMN `time_sec` INT UNSIGNED NULL AFTER `scroll_pct`',
                    $table
                )
            );
        }
    }

    // Add missing indexes if needed.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $order_index = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW INDEX FROM %i WHERE Key_name = %s',
            $table,
            'idx_order_id'
        )
    );

    if ( ! $order_index ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD KEY `idx_order_id` (`order_id`)',
                $table
            )
        );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $protocol_index = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW INDEX FROM %i WHERE Key_name = %s',
            $table,
            'idx_protocol_reason'
        )
    );

    if ( ! $protocol_index ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD KEY `idx_protocol_reason` (`protocol`, `excluded_reason`)',
                $table
            )
        );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $test_type_variant_time_index = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW INDEX FROM %i WHERE Key_name = %s',
            $table,
            'idx_test_type_variant_time'
        )
    );

    if ( ! $test_type_variant_time_index ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD KEY `idx_test_type_variant_time` (`ab_test_id`, `event_type`, `variant`, `time`)',
                $table
            )
        );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $test_time_index = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW INDEX FROM %i WHERE Key_name = %s',
            $table,
            'idx_test_time'
        )
    );

    if ( ! $test_time_index ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD KEY `idx_test_time` (`ab_test_id`, `time`)',
                $table
            )
        );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $test_variant_index = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW INDEX FROM %i WHERE Key_name = %s',
            $table,
            'idx_test_variant'
        )
    );

    if ( ! $test_variant_index ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD KEY `idx_test_variant` (`ab_test_id`, `variant`)',
                $table
            )
        );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

    update_option( 'abtestkit_events_schema_version', 5, false );
}

add_action( 'admin_init', function() {
    // Always run the schema healer on admin requests.
    // It is cheap, and it fixes older live sites where the version flag says "2"
    // but the table is still missing newer columns like order_id / amount.
    abtestkit_create_event_table();
} );

// Align abtestkit admin menu icon
add_action( 'admin_head', function () {
    ?>
    <style>
        #adminmenu #toplevel_page_abtestkit-dashboard .wp-menu-image img {
            width: 20px !important;
            height: 20px !important;
            padding-top: 5px;
        }
    </style>
    <?php
});
