<?php
/**
 * Attendance Reports & Audit Log Workspace (Students, Staff & Exam Attendance)
 * File: inc/attendance/attendance-reports.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

// 1. AJAX Handler for Dynamic Subject Loading in Reports
add_action( 'wp_ajax_ifs_educore_get_subjects_by_class_report', 'ifs_educore_get_subjects_by_class_report_handler' );
function ifs_educore_get_subjects_by_class_report_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    // Allow Administrators, Teachers, and Staff who can edit posts or manage options
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $class_name   = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    if ( ! empty( $section_name ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $unit_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE (class_name = %s OR class_name = %s) AND (section_name = %s OR dept_name = %s)",
                $class_name,
                $clean_class,
                $section_name,
                $section_name
            )
        );
        // phpcs:enable
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $unit_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE (class_name = %s OR class_name = %s)",
                $class_name,
                $clean_class
            )
        );
        // phpcs:enable
    }

    $subjects = array();
    if ( ! empty( $unit_ids ) ) {
        $unit_ids_int   = array_map( 'absint', $unit_ids );
        $clean_unit_ids = implode( ',', $unit_ids_int );
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $subjects = $wpdb->get_results( "SELECT DISTINCT id, subject_name, subject_code FROM `{$wpdb->prefix}sms_subjects` WHERE class_id IN ($clean_unit_ids) ORDER BY subject_name ASC" );
        // phpcs:enable
    }

    if ( empty( $subjects ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $subjects = $wpdb->get_results( "SELECT DISTINCT id, subject_name, subject_code FROM `{$wpdb->prefix}sms_subjects` ORDER BY subject_name ASC" );
        // phpcs:enable
    }

    wp_send_json_success( ! empty( $subjects ) ? $subjects : array() );
}

function educore_student_attendance_log_view( $classes ) {
    global $wpdb;

    if ( ! empty( $classes ) && is_array( $classes ) ) {
        usort( $classes, 'strnatcasecmp' );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $report_mode       = isset( $_GET['report_mode'] ) ? sanitize_key( wp_unslash( $_GET['report_mode'] ) ) : 'student';
    $filter_class      = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_section    = isset( $_GET['filter_section'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_section'] ) ) : '';
    $filter_student_id = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    $filter_subject    = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';
    
    // Exam Attendance filters
    $filter_exam_id    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $exam_class        = isset( $_GET['exam_class'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_class'] ) ) : '';
    $exam_subject      = isset( $_GET['exam_subject'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_subject'] ) ) : '';

    $filter_staff_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';
    $filter_staff_id   = isset( $_GET['staff_id'] ) ? absint( wp_unslash( $_GET['staff_id'] ) ) : 0;

    $start_date        = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' );
    $end_date          = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $subject_person = null;
    $exam_meta      = null;
    $logs           = array();

    // 1. Fetch Records Based on Mode
    if ( 'student' === $report_mode && $filter_student_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $subject_person = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_students` WHERE id = %d LIMIT 1", $filter_student_id ) );
        if ( $subject_person ) {
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT attendance_date, status FROM `{$wpdb->prefix}sms_attendance` WHERE student_id = %d AND attendance_date BETWEEN %s AND %s ORDER BY attendance_date DESC",
                    $filter_student_id,
                    $start_date,
                    $end_date
                )
            );
        }
        // phpcs:enable
    } elseif ( 'exam' === $report_mode && $filter_exam_id > 0 && ! empty( $exam_class ) && ! empty( $exam_subject ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exam_meta = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_exams` WHERE id = %d LIMIT 1", $filter_exam_id ) );
        if ( $exam_meta ) {
            $clean_ex_class = trim( str_ireplace( 'Class ', '', $exam_class ) );
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ea.*, s.full_name, s.student_id as reg_id, s.roll_no 
                     FROM `{$wpdb->prefix}sms_exam_attendance` ea 
                     INNER JOIN `{$wpdb->prefix}sms_students` s ON ea.student_id = s.id 
                     WHERE ea.exam_id = %d AND (ea.class_name = %s OR ea.class_name = %s) AND ea.subject_name = %s 
                     ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.roll_no ASC",
                    $filter_exam_id,
                    $exam_class,
                    $clean_ex_class,
                    $exam_subject
                )
            );
        }
        // phpcs:enable
    } elseif ( 'staff' === $report_mode && $filter_staff_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $subject_person = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_staff` WHERE id = %d LIMIT 1", $filter_staff_id ) );
        if ( $subject_person ) {
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT attendance_date, status FROM `{$wpdb->prefix}sms_staff_attendance` WHERE staff_id = %d AND attendance_date BETWEEN %s AND %s ORDER BY attendance_date DESC",
                    $filter_staff_id,
                    $start_date,
                    $end_date
                )
            );
        }
        // phpcs:enable
    }

    // Pre-load lookup datasets
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_units    = $wpdb->get_results( "SELECT id, class_name, section_name FROM `{$wpdb->prefix}sms_academic_units` WHERE section_name != '' ORDER BY section_name ASC" );
    $all_students = $wpdb->get_results( "SELECT id, full_name, student_id, roll_no, class_name, section_name FROM `{$wpdb->prefix}sms_students` WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, full_name ASC" );
    $all_staff    = $wpdb->get_results( "SELECT id, full_name, name_bn, staff_id, designation, staff_type FROM `{$wpdb->prefix}sms_staff` WHERE status = 'Active' ORDER BY full_name ASC" );
    $all_exams    = $wpdb->get_results( "SELECT id, exam_name FROM `{$wpdb->prefix}sms_exams` ORDER BY id DESC" );
    
    $db_staff_types  = $wpdb->get_col( "SELECT DISTINCT staff_type FROM `{$wpdb->prefix}sms_staff` WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    $default_types   = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
    $all_staff_types = array_unique( array_merge( $default_types, is_array( $db_staff_types ) ? $db_staff_types : array() ) );

    $available_subjects = array();
    if ( ! empty( $filter_class ) ) {
        $clean_c = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $u_ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE (class_name = %s OR class_name = %s)", $filter_class, $clean_c ) );
        if ( ! empty( $u_ids ) ) {
            $u_ids_int   = array_map( 'absint', $u_ids );
            $clean_u_ids = implode( ',', $u_ids_int );
            $available_subjects = $wpdb->get_results( "SELECT DISTINCT subject_name, subject_code FROM `{$wpdb->prefix}sms_subjects` WHERE class_id IN ($clean_u_ids) ORDER BY subject_name ASC" );
        }
    }
    // phpcs:enable
    ?>

    <!-- Filter Controls Bento Card -->
    <div class="ifs-educore-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:24px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="" id="ifs_educore_reports_form">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="reports">

            <!-- Report Mode Switcher -->
            <div style="margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                <span style="font-size:13px; font-weight:700; color:#475569;"><?php esc_html_e( 'Audit Scope Target:', 'ifsedu-school-management' ); ?></span>
                
                <div class="ifs-educore-report-mode-segmented">
                    <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_student" name="report_mode" value="student" <?php checked( $report_mode, 'student' ); ?>>
                    <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_student">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php esc_html_e( 'Individual Student Log', 'ifsedu-school-management' ); ?>
                    </label>

                    <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_exam" name="report_mode" value="exam" <?php checked( $report_mode, 'exam' ); ?>>
                    <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_exam">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Exam Attendance Audit', 'ifsedu-school-management' ); ?>
                    </label>

                    <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_staff" name="report_mode" value="staff" <?php checked( $report_mode, 'staff' ); ?>>
                    <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_staff">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php esc_html_e( 'Employment Type (Faculty / Staff)', 'ifsedu-school-management' ); ?>
                    </label>
                </div>
            </div>

            <!-- 1. STUDENT MODE CONTROLS -->
            <div id="ifs_educore_wrapper_student_controls" style="display: <?php echo ( 'student' === $report_mode ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; align-items:flex-end;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_class" id="ifs_educore_rpt_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_section" id="ifs_educore_rpt_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Subject (Optional)', 'ifsedu-school-management' ); ?></label>
                    <select name="subject_name" id="ifs_educore_rpt_subject_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Subjects --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_subjects as $sub ) : ?>
                            <option value="<?php echo esc_attr( $sub->subject_name ); ?>" <?php selected( $filter_subject, $sub->subject_name ); ?>><?php echo esc_html( $sub->subject_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group" style="grid-column: span 2;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Student', 'ifsedu-school-management' ); ?> *</label>
                    <select name="student_id" id="ifs_educore_report_student_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- 2. EXAM ATTENDANCE MODE CONTROLS -->
            <div id="ifs_educore_wrapper_exam_controls" style="display: <?php echo ( 'exam' === $report_mode ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; align-items:flex-end;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Exam', 'ifsedu-school-management' ); ?> *</label>
                    <select name="exam_id" id="ifs_educore_rpt_exam_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $all_exams as $ex ) : ?>
                            <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?> *</label>
                    <select name="exam_class" id="ifs_educore_rpt_exam_class" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $exam_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Exam Subject', 'ifsedu-school-management' ); ?> *</label>
                    <select name="exam_subject" id="ifs_educore_rpt_exam_subject" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- 3. STAFF MODE CONTROLS -->
            <div id="ifs_educore_wrapper_staff_controls" style="display: <?php echo ( 'staff' === $report_mode ) ? 'flex' : 'none'; ?>; gap:16px; flex-wrap:wrap; align-items:flex-end;">
                <div class="ifs-educore-form-group" style="flex:1; min-width:200px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?> *</label>
                    <select name="staff_type" id="ifs_educore_report_staff_type" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $all_staff_types as $st_type ) : ?>
                            <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group" style="flex:2; min-width:220px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Employee', 'ifsedu-school-management' ); ?> *</label>
                    <select name="staff_id" id="ifs_educore_report_staff_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                        <option value=""><?php esc_html_e( '-- Choose Employee --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- DATE RANGE & SUBMIT ROW (Hidden during Exam Audit mode) -->
            <div id="ifs_educore_wrapper_date_range" style="display: <?php echo ( 'exam' === $report_mode ) ? 'none' : 'flex'; ?>; gap:16px; margin-top:16px; align-items:flex-end; flex-wrap:wrap;">
                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'From Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="start_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $start_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                </div>

                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'To Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="end_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $end_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                </div>

                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <button type="submit" style="width:100%; height:40px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Fetch Attendance Log', 'ifsedu-school-management' ); ?></button>
                </div>
            </div>

            <!-- Submit trigger specifically for Exam mode -->
            <div id="ifs_educore_wrapper_exam_submit" style="display: <?php echo ( 'exam' === $report_mode ) ? 'block' : 'none'; ?>; margin-top:16px;">
                <button type="submit" style="width:100%; max-width:240px; height:40px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Exam Roster Audit', 'ifsedu-school-management' ); ?></button>
            </div>
        </form>
    </div>

    <!-- STATEMENT RESULT -->
<?php if ( $subject_person || $exam_meta ) : 
    if ( 'exam' === $report_mode && $exam_meta ) {
        $display_title = $exam_meta->exam_name . ' — Class ' . $exam_class;
        $display_code  = 'Subject: ' . $exam_subject;
        $meta_line     = sprintf(
            /* translators: %d: Total number of candidates audited */
            esc_html__( 'Total Candidates Audited: %d Candidates', 'ifsedu-school-management' ),
            count( $logs )
        );
    } else {
        $display_title = ( 'student' === $report_mode ) ? $subject_person->full_name : ( ! empty( $subject_person->name_bn ) ? $subject_person->name_bn : $subject_person->full_name );
        $display_code  = ( 'student' === $report_mode ) ? $subject_person->student_id : ( ! empty( $subject_person->staff_id ) ? $subject_person->staff_id : '#' . $subject_person->id );
        $meta_line     = ( 'student' === $report_mode ) 
            ? sprintf(
                /* translators: 1: Class name, 2: Section name, 3: Subject name, 4: Start date, 5: End date */
                esc_html__( 'Class: %1$s %2$s %3$s | Log Period: %4$s to %5$s', 'ifsedu-school-management' ),
                esc_html( $subject_person->class_name ),
                esc_html( $subject_person->section_name ? '(' . $subject_person->section_name . ')' : '' ),
                esc_html( $filter_subject ? ' | Subject: ' . $filter_subject : '' ),
                esc_html( $start_date ),
                esc_html( $end_date )
            )
            : sprintf(
                /* translators: 1: Staff designation, 2: Staff employment type, 3: Start date, 4: End date */
                esc_html__( 'Designation: %1$s (%2$s) | Log Period: %3$s to %4$s', 'ifsedu-school-management' ),
                esc_html( $subject_person->designation ? $subject_person->designation : 'Faculty' ),
                esc_html( $subject_person->staff_type ),
                esc_html( $start_date ),
                esc_html( $end_date )
            );
    }
?>
        <div class="ifs-educore-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h3 style="margin:0; font-weight:800; font-size:18px; color:#0f172a;"><?php echo esc_html( $display_title ); ?> <small style="color:#64748b; font-size:14px;">(<?php echo esc_html( $display_code ); ?>)</small></h3>
                    <span style="color:#64748b; font-size:13px; font-weight:600;"><?php echo esc_html( $meta_line ); ?></span>
                </div>
                <button type="button" onclick="window.print();" class="no-print" style="height:36px; padding:0 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Print Log Statement', 'ifsedu-school-management' ); ?>
                </button>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <?php if ( 'exam' === $report_mode ) : ?>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:15%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Invigilator Notes', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%; text-align:right;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-school-management' ); ?></th>
                            <?php else : ?>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Day', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:right;"><?php esc_html_e( 'Attendance Status', 'ifsedu-school-management' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $l ) : 
                            $status   = isset( $l->status ) ? $l->status : 'Present';
                            $log_time = ! empty( $l->attendance_date ) ? strtotime( $l->attendance_date ) : false;
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <?php if ( 'exam' === $report_mode ) : ?>
                                    <td style="padding:12px 16px;"><strong>#<?php echo esc_html( $l->roll_no ); ?></strong></td>
                                    <td style="padding:12px 16px; font-weight:700; color:#0f172a;"><?php echo esc_html( $l->full_name ); ?> <small style="color:#64748b;">(<?php echo esc_html( strtoupper( (string) $l->reg_id ) ); ?>)</small></td>
                                    <td style="padding:12px 16px; color:#475569;"><?php echo esc_html( ! empty( $l->invigilator_remarks ) ? $l->invigilator_remarks : '—' ); ?></td>
                                    <td style="padding:12px 16px; text-align:right;">
                                        <?php if ( 'Present' === $status ) : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-present"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></span>
                                        <?php elseif ( 'Absent' === $status ) : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-absent"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></span>
                                        <?php else : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-late"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Late / Expelled', 'ifsedu-school-management' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php else : ?>
                                    <td style="padding:12px 16px;"><strong><?php echo esc_html( $log_time ? date_i18n( 'd F, Y', $log_time ) : '—' ); ?></strong></td>
                                    <td style="padding:12px 16px; color:#475569; font-weight:600;"><?php echo esc_html( $log_time ? date_i18n( 'l', $log_time ) : '—' ); ?></td>
                                    <td style="padding:12px 16px; text-align:right;">
                                        <?php if ( 'Present' === $status ) : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-present"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></span>
                                        <?php elseif ( 'Absent' === $status ) : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-absent"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></span>
                                        <?php else : ?>
                                            <span class="ifs-educore-att-status-badge ifs-educore-att-badge-late"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:40px; font-weight:600;"><?php esc_html_e( 'No attendance logs recorded matching the selected criteria.', 'ifsedu-school-management' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- DYNAMIC JS ENGINE: Robust Cascading Sections, Subjects & Students -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var ajaxUrl     = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';
        var reportNonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';

        var unitsMap    = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var studentsMap = <?php echo wp_json_encode( ! empty( $all_students ) ? $all_students : array() ); ?>;
        var staffMap    = <?php echo wp_json_encode( ! empty( $all_staff ) ? $all_staff : array() ); ?>;

        var currentSection   = "<?php echo esc_js( $filter_section ); ?>";
        var currentSubject   = "<?php echo esc_js( $filter_subject ); ?>";
        var currentStudentId = "<?php echo esc_js( $filter_student_id ); ?>";
        var currentExamSub   = "<?php echo esc_js( $exam_subject ); ?>";
        var currentStaffId   = "<?php echo esc_js( $filter_staff_id ); ?>";

        var modeRadios     = document.querySelectorAll('input[name="report_mode"]');
        var wrapperStudent = document.getElementById('ifs_educore_wrapper_student_controls');
        var wrapperExam    = document.getElementById('ifs_educore_wrapper_exam_controls');
        var wrapperStaff   = document.getElementById('ifs_educore_wrapper_staff_controls');
        var wrapperDate    = document.getElementById('ifs_educore_wrapper_date_range');
        var wrapperExSub   = document.getElementById('ifs_educore_wrapper_exam_submit');

        var classSelect   = document.getElementById('ifs_educore_rpt_class_select');
        var sectionSelect = document.getElementById('ifs_educore_rpt_section_select');
        var subjectSelect = document.getElementById('ifs_educore_rpt_subject_select');
        var studentSelect = document.getElementById('ifs_educore_report_student_id');

        var examClassSelect = document.getElementById('ifs_educore_rpt_exam_class');
        var examSubSelect   = document.getElementById('ifs_educore_rpt_exam_subject');

        var staffTypeSelect = document.getElementById('ifs_educore_report_staff_type');
        var staffSelect     = document.getElementById('ifs_educore_report_staff_id');

        // 1. Toggle Mode Visibility
        modeRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'student') {
                    wrapperStudent.style.display = 'grid';
                    wrapperExam.style.display    = 'none';
                    wrapperStaff.style.display   = 'none';
                    wrapperDate.style.display    = 'flex';
                    wrapperExSub.style.display   = 'none';
                } else if (this.value === 'exam') {
                    wrapperStudent.style.display = 'none';
                    wrapperExam.style.display    = 'grid';
                    wrapperStaff.style.display   = 'none';
                    wrapperDate.style.display    = 'none';
                    wrapperExSub.style.display   = 'block';
                    if (examClassSelect && examClassSelect.value) {
                        loadExamSubjects(examClassSelect.value, currentExamSub);
                    }
                } else {
                    wrapperStudent.style.display = 'none';
                    wrapperExam.style.display    = 'none';
                    wrapperStaff.style.display   = 'flex';
                    wrapperDate.style.display    = 'flex';
                    wrapperExSub.style.display   = 'none';
                    if (staffTypeSelect) {
                        populateStaffByType(staffTypeSelect.value, currentStaffId);
                    }
                }
            });
        });

        // 2. Populate Sections based on Class
        function populateSections(selectedClass, selectedSecName) {
            selectedSecName = selectedSecName || '';
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
            if (!selectedClass) return;

            var filtered = unitsMap.filter(function(item) { return item.class_name == selectedClass; });
            var uniqueSections = [];
            filtered.forEach(function(item) {
                if (item.section_name && uniqueSections.indexOf(item.section_name) === -1) {
                    uniqueSections.push(item.section_name);
                }
            });

            uniqueSections.sort(function(a, b) {
                return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
            });

            uniqueSections.forEach(function(secName) {
                var opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        // 3. Fetch & Populate Subjects dynamically via AJAX (Student Mode)
        function loadSubjects(selectedClass, selectedSection, targetSubject) {
            targetSubject = targetSubject || '';
            if (!subjectSelect) return;
            subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>';

            if (!selectedClass) {
                subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Subjects --', 'ifsedu-school-management' ) ); ?></option>';
                return;
            }

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ifs_educore_get_subjects_by_class_report',
                    security: reportNonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    subjectSelect.innerHTML = '';
                    var defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = '<?php echo esc_js( __( '-- All Subjects --', 'ifsedu-school-management' ) ); ?>';
                    subjectSelect.appendChild(defaultOpt);

                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(sub) {
                            var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                            var opt = document.createElement('option');
                            opt.value = sub.subject_name;
                            opt.textContent = sub.subject_name + codeStr;
                            if (sub.subject_name === targetSubject) {
                                opt.selected = true;
                            }
                            subjectSelect.appendChild(opt);
                        });
                    } else {
                        var noOpt = document.createElement('option');
                        noOpt.value = '';
                        noOpt.textContent = '<?php echo esc_js( __( 'No Subjects Configured', 'ifsedu-school-management' ) ); ?>';
                        subjectSelect.appendChild(noOpt);
                    }
                },
                error: function() {
                    subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Error Loading Subjects --', 'ifsedu-school-management' ) ); ?></option>';
                }
            });
        }

        // 4. Fetch & Populate Subjects dynamically via AJAX (Exam Mode)
        function loadExamSubjects(selectedClass, targetSubject) {
            targetSubject = targetSubject || '';
            if (!examSubSelect) return;
            examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>';

            if (!selectedClass) {
                examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';
                return;
            }

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ifs_educore_get_subjects_by_class_report',
                    security: reportNonce,
                    class_name: selectedClass,
                    section_name: ''
                },
                success: function(response) {
                    examSubSelect.innerHTML = '';
                    var defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = '<?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?>';
                    examSubSelect.appendChild(defaultOpt);

                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(sub) {
                            var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                            var opt = document.createElement('option');
                            opt.value = sub.subject_name;
                            opt.textContent = sub.subject_name + codeStr;
                            if (sub.subject_name === targetSubject) {
                                opt.selected = true;
                            }
                            examSubSelect.appendChild(opt);
                        });
                    } else {
                        var noOpt = document.createElement('option');
                        noOpt.value = '';
                        noOpt.textContent = '<?php echo esc_js( __( 'No Subjects Found', 'ifsedu-school-management' ) ); ?>';
                        examSubSelect.appendChild(noOpt);
                    }
                },
                error: function() {
                    examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Error --', 'ifsedu-school-management' ) ); ?></option>';
                }
            });
        }

        // 5. Populate Students based on Class and Section
        function populateStudents(selectedClass, selectedSecName, selectedStudentId) {
            selectedStudentId = selectedStudentId || '';
            if (!studentSelect) return;
            studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>';

            var filteredStudents = studentsMap;

            if (selectedClass) {
                filteredStudents = filteredStudents.filter(function(item) { return item.class_name == selectedClass; });
            }

            if (selectedSecName) {
                filteredStudents = filteredStudents.filter(function(item) { return item.section_name == selectedSecName; });
            }

            filteredStudents.forEach(function(stu) {
                var opt = document.createElement('option');
                opt.value = stu.id;
                opt.textContent = stu.roll_no ? '[Roll: ' + stu.roll_no + '] ' + stu.full_name + ' (' + stu.class_name + ')' : stu.full_name + ' (' + stu.class_name + ')';
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && subjectSelect && studentSelect) {
            populateSections(classSelect.value, currentSection);
            populateStudents(classSelect.value, currentSection, currentStudentId);
            if (classSelect.value && currentSubject) {
                loadSubjects(classSelect.value, currentSection, currentSubject);
            }

            classSelect.addEventListener('change', function() {
                populateSections(this.value, '');
                loadSubjects(this.value, '', '');
                populateStudents(this.value, '', '');
            });

            sectionSelect.addEventListener('change', function() {
                loadSubjects(classSelect.value, this.value, '');
                populateStudents(classSelect.value, this.value, '');
            });
        }

        if (examClassSelect) {
            if (examClassSelect.value) {
                loadExamSubjects(examClassSelect.value, currentExamSub);
            }
            examClassSelect.addEventListener('change', function() {
                loadExamSubjects(this.value, '');
            });
        }

        // 6. Staff Auto-populate
        function populateStaffByType(selectedType, selectedStaffId) {
            selectedStaffId = selectedStaffId || '';
            if (!staffSelect) return;
            staffSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Employee --', 'ifsedu-school-management' ) ); ?></option>';

            var filteredStaff = staffMap;

            if (selectedType && selectedType.trim() !== '') {
                var targetLower = selectedType.trim().toLowerCase();
                filteredStaff = staffMap.filter(function(item) {
                    var itemTypeLower = (item.staff_type || '').trim().toLowerCase();
                    return itemTypeLower === targetLower || itemTypeLower.indexOf(targetLower) !== -1 || targetLower.indexOf(itemTypeLower) !== -1;
                });

                if (filteredStaff.length === 0) {
                    filteredStaff = staffMap;
                }
            }

            filteredStaff.forEach(function(st) {
                var opt = document.createElement('option');
                opt.value = st.id;
                var name = st.name_bn ? st.name_bn : st.full_name;
                opt.textContent = st.designation ? name + ' (' + st.designation + ')' : name;
                
                if (String(st.id) === String(selectedStaffId)) {
                    opt.selected = true;
                }
                staffSelect.appendChild(opt);
            });
        }

        if (staffTypeSelect && staffSelect) {
            populateStaffByType(staffTypeSelect.value, currentStaffId);

            staffTypeSelect.addEventListener('change', function() {
                populateStaffByType(this.value);
            });
        }
    });
    </script>
<?php
}