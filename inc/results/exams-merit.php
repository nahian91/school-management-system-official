<?php
/**
 * Enterprise Merit List & Position Roster Module
 * File: inc/results/exams-merit.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. AJAX Handler for Dynamic Section Loading based on Class
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_merit', 'ifs_educore_get_sections_by_class_merit_handler' );
function ifs_educore_get_sections_by_class_merit_handler() {
    check_ajax_referer( 'ifs_educore_merit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

function educore_merit_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Capability Check
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view academic merit rankings.', 'ifsedu-school-management' ) );
    }

    // GET Filter Parameters Sanitization
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM `{$table_exams}` ORDER BY id DESC" );

    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    // phpcs:enable

    if ( ! empty( $all_classes_raw ) && is_array( $all_classes_raw ) ) {
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // Available sections for chosen class
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
                $filter_class
            )
        );
        // phpcs:enable
    }

    // Pull Dynamic Institutional Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }
    
    $base_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'results',
            'sub'  => 'merit',
        ),
        admin_url( 'admin.php' )
    );
    $back_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );
    ?>
    <div class="ifs-educore-merit-root">

        <!-- Top Navigation Header -->
        <div class="ifs-educore-header-block no-print">
            <h2>
                <span class="dashicons dashicons-awards" style="color:#00523c;"></span>
                <?php esc_html_e( 'Merit List & Position Ranking Roster', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Exams Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Merit List Filter Console Bento Card -->
        <div class="ifs-educore-bento-card no-print">

            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreMeritFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="results">
                <input type="hidden" name="sub" value="merit">

                <div class="ifs-educore-filter-grid">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_merit_class_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_classes_raw as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Section Filter', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_merit_section_select" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate Roster', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_merit_nonce" ) ); ?>';

            $('#ifs_educore_merit_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect = $('#ifs_educore_merit_section_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class_merit',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $secSelect.html(options);
                        } else {
                            $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            });
        });
        </script>

        <?php
        if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );

            // 1. Fetch ALL students of the class to determine Global Class Rank
            $all_class_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class
                )
            );
            // phpcs:enable

            if ( ! empty( $all_class_students ) ) {
                $all_ranked_pool = array();

                foreach ( $all_class_students as $s ) {
                    $student_internal_id = absint( $s->id );

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT obtained_marks, grade, gpa FROM `{$table_results}` WHERE exam_id = %d AND student_id = %d",
                            $filter_exam,
                            $student_internal_id
                        )
                    );
                    // phpcs:enable

                    if ( empty( $results ) ) {
                        continue;
                    }

                    $total_obt = 0;
                    $sum_gpa   = 0;
                    $sub_count = count( $results );
                    $has_fail  = false;

                    foreach ( $results as $res ) {
                        $total_obt += floatval( $res->obtained_marks );
                        $sum_gpa   += floatval( $res->gpa );
                        if ( strtoupper( trim( (string) $res->grade ) ) === 'F' || floatval( $res->gpa ) <= 0 ) {
                            $has_fail = true;
                        }
                    }

                    $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                    $final_gpa = $has_fail ? 0.00 : round( $avg_gpa, 2 );

                    $all_ranked_pool[] = array(
                        'student' => $s,
                        'total'   => $total_obt,
                        'gpa'     => $final_gpa,
                        'failed'  => $has_fail,
                        'section' => $s->section_name ? trim( (string) $s->section_name ) : '',
                    );
                }

                // Global Sort: Passed students first -> GPA (DESC) -> Total Score (DESC)
                usort( $all_ranked_pool, function( $a, $b ) {
                    if ( $a['failed'] !== $b['failed'] ) {
                        return $a['failed'] ? 1 : -1;
                    }
                    if ( $b['gpa'] != $a['gpa'] ) {
                        return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                    }
                    return ( $b['total'] < $a['total'] ) ? -1 : 1;
                } );

                // Assign Global Class Positions & Section Positions
                $class_pos_counter = 1;
                $section_counters  = array();
                $display_roster    = array();

                $total_passed_count = 0;
                $sum_passed_gpa     = 0;
                $top_performer_name = '—';

                foreach ( $all_ranked_pool as $item ) {
                    $sec = $item['section'];
                    if ( ! isset( $section_counters[ $sec ] ) ) {
                        $section_counters[ $sec ] = 1;
                    }

                    if ( ! $item['failed'] ) {
                        $item['class_position']   = $class_pos_counter++;
                        $item['section_position'] = $section_counters[ $sec ]++;
                        
                        if ( empty( $filter_section ) || $sec === $filter_section ) {
                            $total_passed_count++;
                            $sum_passed_gpa += $item['gpa'];
                            if ( '—' === $top_performer_name ) {
                                $top_performer_name = $item['student']->full_name;
                            }
                        }
                    } else {
                        $item['class_position']   = 0;
                        $item['section_position'] = 0;
                    }

                    if ( empty( $filter_section ) || $sec === $filter_section ) {
                        $display_roster[] = $item;
                    }
                }

                $total_students_count = count( $display_roster );
                $pass_rate_pct = ( $total_students_count > 0 ) ? round( ( $total_passed_count / $total_students_count ) * 100, 1 ) : 0;
                $cohort_avg_gpa = ( $total_passed_count > 0 ) ? number_format( $sum_passed_gpa / $total_passed_count, 2 ) : '0.00';

                if ( ! empty( $display_roster ) ) :
                ?>

                <!-- Summary Metrics Bento Grid -->
                <div class="ifs-educore-bento-grid-stats no-print">
                    <div class="ifs-educore-stat-card stat-top">
                        <div class="ifs-educore-stat-icon" style="background: #fefce8; color: #eab308;">
                            <span class="dashicons dashicons-star-filled" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Top Performer', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #854d0e; font-size:16px;"><?php echo esc_html( $top_performer_name ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-stat-card stat-avg">
                        <div class="ifs-educore-stat-icon" style="background: #ecfdf5; color: #00523c;">
                            <span class="dashicons dashicons-chart-bar" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Class Avg. GPA', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #00523c;"><?php echo esc_html( $cohort_avg_gpa ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-stat-card stat-pass">
                        <div class="ifs-educore-stat-icon" style="background: #f0f9ff; color: #0284c7;">
                            <span class="dashicons dashicons-groups" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Passed Students', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #0284c7;"><?php echo esc_html( $total_passed_count . ' / ' . $total_students_count ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-stat-card stat-rate">
                        <div class="ifs-educore-stat-icon" style="background: #f5f3ff; color: #8b5cf6;">
                            <span class="dashicons dashicons-saved" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Pass Percentage', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #6d28d9;"><?php echo esc_html( $pass_rate_pct ); ?>%</span>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 20px;" class="no-print">
                    <button type="button" onclick="window.print();" class="ifs-educore-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e( 'Print Official Merit Position Roster', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <!-- Printable Official Roster Card -->
                <div class="ifs-educore-tabulation-container">
                    <div class="ifs-educore-tabulation-header">
                        <div class="ifs-educore-header-brand-row">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-roster-logo">
                            <?php endif; ?>
                            <h3 class="ifs-educore-tabulation-title"><?php echo esc_html( $school_name ); ?></h3>
                        </div>
                        <?php if ( ! empty( $school_tagline ) ) : ?>
                            <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">
                                <?php echo esc_html( $school_tagline ); ?>
                            </div>
                        <?php endif; ?>
                        <h5 class="ifs-educore-tabulation-sub">
                            <?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Official Merit Ranking & Position Roster', 'ifsedu-school-management' ); ?>
                        </h5>
                        <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 8px; border: 1px solid #cbd5e1;">
                            <?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_class ); ?>
                            <?php if ( ! empty( $filter_section ) ) : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_section ); ?>
                            <?php else : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'All Sections Combined', 'ifsedu-school-management' ); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="ifs-educore-tabulation-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%;"><?php esc_html_e( 'Class Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Sec. Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 13%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align: left;"><?php esc_html_e( 'Student Full Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 12%;"><?php esc_html_e( 'Total Score', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $display_roster as $item ) : 
                                    $s = $item['student'];
                                    $c_pos = $item['class_position'];
                                    $s_pos = $item['section_position'];

                                    // Rank Badge Styling Logic
                                    $rank_class = 'rank-norm';
                                    if ( 1 === $c_pos ) {
                                        $rank_class = 'rank-gold';
                                    } elseif ( 2 === $c_pos ) {
                                        $rank_class = 'rank-silver';
                                    } elseif ( 3 === $c_pos ) {
                                        $rank_class = 'rank-bronze';
                                    }
                                ?>
                                <tr>
                                    <!-- Class Position -->
                                    <td>
                                        <?php if ( ! $item['failed'] && $c_pos > 0 ) : ?>
                                            <span class="ifs-educore-rank-badge <?php echo esc_attr( $rank_class ); ?>">
                                                <?php if ( 1 === $c_pos ) : ?>🏆<?php endif; ?>
                                                #<?php echo esc_html( $c_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Section Position -->
                                    <td>
                                        <?php if ( ! $item['failed'] && $s_pos > 0 ) : ?>
                                            <span class="ifs-educore-rank-badge <?php echo ( 1 === $s_pos ) ? 'rank-gold' : 'rank-norm'; ?>">
                                                #<?php echo esc_html( $s_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                    <td><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                    <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                    <td><span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;"><?php echo esc_html( ! empty( $s->section_name ) ? $s->section_name : 'N/A' ); ?></span></td>
                                    <td><strong><?php echo esc_html( floatval( $item['total'] ) ); ?></strong></td>
                                    <td style="font-weight: 800; color: <?php echo $item['failed'] ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( number_format( floatval( $item['gpa'] ), 2 ) ); ?></td>
                                    <td>
                                        <span class="ifs-educore-badge-status <?php echo $item['failed'] ? 'status-fail' : 'status-pass'; ?>">
                                            <?php echo $item['failed'] ? esc_html__( 'FAIL', 'ifsedu-school-management' ) : esc_html__( 'PASS', 'ifsedu-school-management' ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ifs-educore-sign-row">
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Tabulator Signature', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-roster-sig-img">
                            <?php endif; ?>
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Headmaster / Principal', 'ifsedu-school-management' ); ?></div>
                        </div>
                    </div>
                </div>

                <?php 
                else :
                    echo '<div class="ifs-educore-status-banner">' . esc_html__( 'No published exam results found for students in the selected section.', 'ifsedu-school-management' ) . '</div>';
                endif;

            } else {
                echo '<div class="ifs-educore-status-banner">' . esc_html__( 'No active students found in this class.', 'ifsedu-school-management' ) . '</div>';
            }
        }
        ?>

    </div>
    <?php
}