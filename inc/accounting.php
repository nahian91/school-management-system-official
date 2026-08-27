<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * Master Accounting Module Loader
 * File: inc/accounting.php
 */

// Define directory path safely (already inside inc/)
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_accounting_dir = plugin_dir_path( __FILE__ ) . 'accounting/';

// Load required sub-files in order
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_accounting_files = array(
    'accounting-delete.php',    // Ledger deletion handler
    'accounting-add-edit.php',  // Record entry form view
    'accounting-list.php',      // Master ledger list view & summary stats
    'accounting-tab.php',       // Router function: educore_accounting_tab()
);

foreach ( $educore_accounting_files as $educore_file ) {
    $educore_file_path = $educore_accounting_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Accounting Error: Missing file ' . $educore_file_path );
    }
}