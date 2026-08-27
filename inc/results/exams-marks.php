<?php
/**
 * High-End Marks Entry Matrix & Grading Evaluation Engine
 * File: inc/results/exams-marks.php
 * Text Domain: ifsedu-school-management
 * Architecture: Neo-Bento Interface with Real-time Auto Grading, Auto-Draft Session Storage & Unsaved Warning
 * Teacher Scope: Restricts Class/Section/Subject dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS (Filtered by Teacher Assignments)
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_marks', 'ifs_educore_get_sections_by_class_marks_handler' );
function ifs_educore_get_sections_by_class_marks_handler() {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin && ! $is_staff ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_units              = $wpdb->prefix . 'sms_academic_units';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $class_name               = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT u.section_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != '' 
                     ORDER BY u.section_name ASC",
                    $teacher_id,
                    $class_name
                )
            );
            // phpcs:enable
            wp_send_json_success( is_array( $sections ) ? $sections : array() );
        }
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

add_action( 'wp_ajax_ifs_educore_get_subjects_for_marks_matrix', 'ifs_educore_get_subjects_for_marks_matrix_handler' );
function ifs_educore_get_subjects_for_marks_matrix_handler() {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin && ! $is_staff ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $class_name             = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $subjects = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id 
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s 
                     ORDER BY s.subject_name ASC",
                    $teacher_id,
                    $class_name
                )
            );
            // phpcs:enable
            wp_send_json_success( is_array( $subjects ) ? $subjects : array() );
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $subjects = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass 
             FROM `{$table_subjects}` s 
             INNER JOIN `{$table_units}` u ON s.class_id = u.id 
             WHERE u.class_name = %s 
             ORDER BY s.subject_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $subjects ) ? $subjects : array() );
}

// --------------------------------------------------------------------------
// 2. STANDARD BD NCTB GRADING FUNCTION
// --------------------------------------------------------------------------
if ( ! function_exists( 'educore_calculate_grade' ) ) {
    function educore_calculate_grade( $obtained, $total = 100 ) {
        $total = floatval( $total ) > 0 ? floatval( $total ) : 100;
        $pct   = ( floatval( $obtained ) / $total ) * 100;

        if ( $pct >= 80 ) {
            return array( 'A+', 5.00 );
        } elseif ( $pct >= 70 ) {
            return array( 'A', 4.00 );
        } elseif ( $pct >= 60 ) {
            return array( 'A-', 3.50 );
        } elseif ( $pct >= 50 ) {
            return array( 'B', 3.00 );
        } elseif ( $pct >= 40 ) {
            return array( 'C', 2.00 );
        } elseif ( $pct >= 33 ) {
            return array( 'D', 1.00 );
        } else {
            return array( 'F', 0.00 );
        }
    }
}

// --------------------------------------------------------------------------
// 3. MAIN MARKS ENTRY MATRIX VIEW
// --------------------------------------------------------------------------
function educore_exams_marks_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_exams            = $wpdb->prefix . 'sms_exams';
    $table_results          = $wpdb->prefix . 'sms_results';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Procedural Capability Validation
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }
    // phpcs:enable

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to enter examination marks.', 'ifsedu-school-management' ) );
    }

    $raw_req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $current_uri = remove_query_arg( array( 'status', 'msg' ), $raw_req_uri );
    $base_url    = esc_url_raw( $current_uri );
    $notice_msg  = '';

    // Unified Parameter Resolution
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_REQUEST['exam_id'] ) ? absint( $_REQUEST['exam_id'] ) : 0;
    $filter_class   = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_subject = isset( $_REQUEST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['subject_name'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // --------------------------------------------------------------------------
    // RESOLVE TEACHER SUBJECT & CLASS ALLOCATIONS
    // --------------------------------------------------------------------------
    $teacher_assigned_classes = array();
    $teacher_assigned_subs    = array();
    $teacher_id               = 0;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $allocations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.class_name, u.section_name, s.subject_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id 
                     WHERE ts.teacher_id = %d",
                    $teacher_id
                )
            );

            if ( ! empty( $allocations ) ) {
                foreach ( $allocations as $al ) {
                    $c_val = trim( (string) $al->class_name );
                    $s_val = trim( (string) $al->subject_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $teacher_assigned_classes, true ) ) {
                        $teacher_assigned_classes[] = $c_val;
                    }
                    if ( ! empty( $s_val ) ) {
                        $teacher_assigned_subs[ $c_val ][] = $s_val;
                    }
                }
            }
        }
    }
    // phpcs:enable

    // --------------------------------------------------------------------------
    // FORM SUBMISSION (SAVE/UPDATE BULK MARKS MATRIX)
    // --------------------------------------------------------------------------
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $request_method && isset( $_POST['educore_save_marks_matrix'] ) ) {
        if ( isset( $_POST['ifs_educore_marks_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_marks_nonce'] ) ), 'save_marks_action' ) ) {
            
            // Authorization Check for Teachers
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ( ! in_array( $filter_class, $teacher_assigned_classes, true ) || ! in_array( $filter_subject, (array) ( $teacher_assigned_subs[ $filter_class ] ?? array() ), true ) ) ) {
                wp_die( esc_html__( 'Security Check: You are not authorized to submit marks for this class/subject allocation.', 'ifsedu-school-management' ) );
            }

            $total_marks  = isset( $_POST['total_marks_limit'] ) ? floatval( $_POST['total_marks_limit'] ) : 100.00;
            $pass_marks   = isset( $_POST['pass_marks_limit'] ) ? floatval( $_POST['pass_marks_limit'] ) : 33.00;
            $cq_lim_post  = isset( $_POST['cq_marks_limit'] ) ? floatval( $_POST['cq_marks_limit'] ) : 70.00;
            $mcq_lim_post = isset( $_POST['mcq_marks_limit'] ) ? floatval( $_POST['mcq_marks_limit'] ) : 30.00;
            $pr_lim_post  = isset( $_POST['pr_marks_limit'] ) ? floatval( $_POST['pr_marks_limit'] ) : 0.00;

            $cq_pass      = isset( $_POST['cq_pass_limit'] ) ? floatval( $_POST['cq_pass_limit'] ) : 0.00;
            $mcq_pass     = isset( $_POST['mcq_pass_limit'] ) ? floatval( $_POST['mcq_pass_limit'] ) : 0.00;
            $pr_pass      = isset( $_POST['pr_pass_limit'] ) ? floatval( $_POST['pr_pass_limit'] ) : 0.00;

            $raw_cq       = ( isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_marks'] ) ) : array();
            $raw_mcq      = ( isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_marks'] ) ) : array();
            $raw_pr       = ( isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['practical_marks'] ) ) : array();

            $students_cq  = array_map( 'floatval', $raw_cq );
            $students_mcq = array_map( 'floatval', $raw_mcq );
            $students_pr  = array_map( 'floatval', $raw_pr );

            $saved_count = 0;
            if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
                foreach ( $students_cq as $s_id => $val_cq ) {
                    $s_id_int = absint( $s_id );
                    
                    $cq_raw   = floatval( $val_cq );
                    $cq_val   = max( 0, min( $cq_raw, $cq_lim_post ) );

                    $mcq_raw  = isset( $students_mcq[ $s_id_int ] ) ? floatval( $students_mcq[ $s_id_int ] ) : 0.00;
                    $mcq_val  = max( 0, min( $mcq_raw, $mcq_lim_post ) );

                    $pr_raw   = isset( $students_pr[ $s_id_int ] ) ? floatval( $students_pr[ $s_id_int ] ) : 0.00;
                    $pr_val   = max( 0, min( $pr_raw, $pr_lim_post ) );

                    $obtained = min( $cq_val + $mcq_val + $pr_val, $total_marks );

                    $has_failed = false;
                    if ( $cq_pass > 0 && $cq_val < $cq_pass ) {
                        $has_failed = true;
                    }
                    if ( $mcq_pass > 0 && $mcq_val < $mcq_pass ) {
                        $has_failed = true;
                    }
                    if ( $pr_pass > 0 && $pr_val < $pr_pass ) {
                        $has_failed = true;
                    }
                    if ( $obtained < $pass_marks ) {
                        $has_failed = true;
                    }

                    if ( $has_failed ) {
                        $grade = 'F';
                        $gpa   = 0.00;
                    } else {
                        $grade_eval = educore_calculate_grade( $obtained, $total_marks );
                        $grade      = $grade_eval[0];
                        $gpa        = $grade_eval[1];
                    }

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $existing_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_results}` WHERE exam_id = %d AND student_id = %d AND subject_name = %s LIMIT 1",
                            $filter_exam,
                            $s_id_int,
                            $filter_subject
                        )
                    );

                    $data = array(
                        'exam_id'         => $filter_exam,
                        'student_id'      => $s_id_int,
                        'class_name'      => $filter_class,
                        'section_name'    => $filter_section,
                        'subject_name'    => $filter_subject,
                        'total_marks'     => $total_marks,
                        'obtained_marks'  => $obtained,
                        'cq_marks'        => $cq_val,
                        'mcq_marks'       => $mcq_val,
                        'practical_marks' => $pr_val,
                        'grade'           => $grade,
                        'gpa'             => $gpa,
                    );

                    $format = array( '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%f' );

                    if ( $existing_id > 0 ) {
                        $wpdb->update( $table_results, $data, array( 'id' => $existing_id ), $format, array( '%d' ) );
                    } else {
                        $wpdb->insert( $table_results, $data, $format );
                    }
                    // phpcs:enable
                    $saved_count++;
                }

                if ( function_exists( 'educore_log_activity' ) ) {
                    /* translators: 1: Number of students, 2: Subject name */
                    educore_log_activity( sprintf( __( 'Evaluated and saved marks for %1$d students in %2$s', 'ifsedu-school-management' ), $saved_count, $filter_subject ) );
                }

                $notice_msg = sprintf(
                    /* translators: %d: Number of students whose marks were saved */
                    esc_html__( 'Successfully evaluated and saved marks for %d students.', 'ifsedu-school-management' ),
                    $saved_count
                );
            }
        }
    }

    // Fetch Examinations
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM `{$table_exams}` ORDER BY id DESC" );

    // Fetch Academic Classes
    $academic_classes = array();
    if ( ! $is_admin && ! empty( $teacher_assigned_classes ) ) {
        $academic_classes = $teacher_assigned_classes;
    } else {
        $raw_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
        if ( ! empty( $raw_classes ) && is_array( $raw_classes ) ) {
            $academic_classes = array_values( array_unique( $raw_classes ) );
            usort( $academic_classes, 'strnatcasecmp' );
        }
    }

    // Pre-populate Available Sections
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        if ( ! $is_admin && $teacher_id > 0 ) {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT u.section_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != '' 
                     ORDER BY u.section_name ASC",
                    $teacher_id,
                    $filter_class
                )
            );
        } else {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
                    $filter_class
                )
            );
        }
    }

    // Fetch Mapped Subjects with Component Limits
    $available_subjects = array();
    $active_subject_obj = null;

    if ( ! empty( $filter_class ) ) {
        if ( ! $is_admin && $teacher_id > 0 ) {
            $available_subjects = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT s.* 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id 
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s 
                     ORDER BY s.subject_name ASC",
                    $teacher_id,
                    $filter_class
                )
            );
        } else {
            $available_subjects = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT s.* FROM `{$table_subjects}` s 
                     INNER JOIN `{$table_units}` u ON s.class_id = u.id 
                     WHERE u.class_name = %s 
                     ORDER BY s.subject_name ASC",
                    $filter_class
                )
            );
        }

        if ( ! empty( $filter_subject ) && ! empty( $available_subjects ) ) {
            foreach ( $available_subjects as $sub_item ) {
                if ( $sub_item->subject_name === $filter_subject ) {
                    $active_subject_obj = $sub_item;
                    break;
                }
            }
        }
    }

    // Fetch Active Students Dataset & Pre-existing Marks
    $students_list = array();
    $saved_marks   = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        if ( ! empty( $filter_section ) ) {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s AND section_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class
                )
            );
        }

        $existing_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT student_id, cq_marks, mcq_marks, practical_marks, obtained_marks, grade, gpa 
                 FROM `{$table_results}` 
                 WHERE exam_id = %d AND class_name = %s AND subject_name = %s",
                $filter_exam,
                $filter_class,
                $filter_subject
            ),
            OBJECT_K
        );

        if ( ! empty( $existing_results ) ) {
            $saved_marks = $existing_results;
        }
    }
    // phpcs:enable
    ?>

    <div class="ifs-educore-marks-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="notice notice-success is-dismissible" style="padding:12px; margin:0; font-weight:700; border-left:4px solid #00523c; background:#ecfdf5; color:#065f46; border-radius:8px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:4px;"></span>
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- Search & Selection Bento Filter Card -->
        <div class="ifs-educore-bento-card">

            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreMarksFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'class_name', 'section_name', 'subject_name' ), true ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                <input type="hidden" name="sub" value="marks">

                <div class="ifs-educore-filter-grid">
                    <!-- 1. Select Exam -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="ifs_educore_marks_exam_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_marks_class_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : 
                                $display_class_name = preg_match( '/^class\s+/i', (string) $cls_name ) ? $cls_name : 'Class ' . $cls_name;
                            ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $display_class_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Section (Optional)', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_marks_section_select" class="ifs-educore-select">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_name ) : ?>
                                <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $filter_section, $sec_name ); ?>>
                                    <?php echo esc_html( $sec_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Subject Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '4. Target Subject', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="ifs_educore_marks_subject_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_subjects as $sub_item ) : ?>
                                <option value="<?php echo esc_attr( $sub_item->subject_name ); ?>" <?php selected( $filter_subject, $sub_item->subject_name ); ?>>
                                    <?php echo esc_html( $sub_item->subject_name . ( $sub_item->subject_code ? ' (' . $sub_item->subject_code . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 5. Submit Filter -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-primary">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e( 'Load Matrix', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Cascading Dropdown Scripts -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_marks_nonce" ) ); ?>';

            $('#ifs_educore_marks_class_select').on('change', function() {
                var selectedClass  = $(this).val();
                var $secSelect     = $('#ifs_educore_marks_section_select');
                var $subjectSelect = $('#ifs_educore_marks_subject_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');
                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                // Load Sections
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class_marks',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $secSelect.html(options);
                        } else {
                            $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });

                // Load Subjects
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_subjects_for_marks_matrix',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var subOptions = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                subOptions += '<option value="' + sub.subject_name + '">' + sub.subject_name + codeStr + '</option>';
                            });
                            $subjectSelect.html(subOptions);
                        } else {
                            $subjectSelect.html('<option value=""><?php echo esc_js( __( 'No Mapped Subjects Found', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            });
        });
        </script>

        <!-- Marks Entry Matrix Table -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : 
            $tot_limit = $active_subject_obj ? floatval( $active_subject_obj->total_marks ) : 100.00;
            $pass_lim  = $active_subject_obj ? floatval( $active_subject_obj->pass_marks ) : 33.00;
            $cq_lim    = $active_subject_obj ? floatval( $active_subject_obj->cq_marks ) : 70.00;
            $cq_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->cq_pass ) : 23.00;
            $mcq_lim   = $active_subject_obj ? floatval( $active_subject_obj->mcq_marks ) : 30.00;
            $mcq_p_lim = $active_subject_obj ? floatval( $active_subject_obj->mcq_pass ) : 10.00;
            $pr_lim    = $active_subject_obj ? floatval( $active_subject_obj->practical_marks ) : 0.00;
            $pr_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->practical_pass ) : 0.00;
        ?>
            <div class="ifs-educore-bento-card">
                <form method="POST" id="educoreMarksMatrixForm" action="<?php echo esc_url( add_query_arg( array( 'exam_id' => $filter_exam, 'class_name' => $filter_class, 'section_name' => $filter_section, 'subject_name' => $filter_subject, 'sub' => 'marks' ), $base_url ) ); ?>">
                    <?php wp_nonce_field( 'save_marks_action', 'ifs_educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">

                    <input type="hidden" name="total_marks_limit" id="total_marks_limit" value="<?php echo esc_attr( $tot_limit ); ?>">
                    <input type="hidden" name="pass_marks_limit" id="pass_marks_limit" value="<?php echo esc_attr( $pass_lim ); ?>">
                    <input type="hidden" name="cq_marks_limit" id="cq_marks_limit" value="<?php echo esc_attr( $cq_lim ); ?>">
                    <input type="hidden" name="mcq_marks_limit" id="mcq_marks_limit" value="<?php echo esc_attr( $mcq_lim ); ?>">
                    <input type="hidden" name="pr_marks_limit" id="pr_marks_limit" value="<?php echo esc_attr( $pr_lim ); ?>">

                    <input type="hidden" name="cq_pass_limit" id="cq_pass_limit" value="<?php echo esc_attr( $cq_p_lim ); ?>">
                    <input type="hidden" name="mcq_pass_limit" id="mcq_pass_limit" value="<?php echo esc_attr( $mcq_p_lim ); ?>">
                    <input type="hidden" name="pr_pass_limit" id="pr_pass_limit" value="<?php echo esc_attr( $pr_p_lim ); ?>">

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong style="font-size:16px; color:#0f172a;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:12px; color:#64748b; margin-left:8px;">(Total: <?php echo esc_html( $tot_limit ); ?> | Pass: <?php echo esc_html( $pass_lim ); ?>)</span>
                        </div>
                        <div>
                            <button type="submit" name="educore_save_marks_matrix" class="ifs-educore-btn-submit">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="ifs-educore-matrix-table" id="dptMarksEntryTable">
                            <thead>
                                <tr>
                                    <th style="width: 6%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 12%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align: left; width: 22%;"><?php esc_html_e( 'Student Full Name', 'ifsedu-school-management' ); ?></th>
                                    
                                    <!-- MCQ Column -->
                                    <th style="width: 14%;">
                                        <?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?><br>
                                        <span class="ifs-educore-criteria-pill">Max: <?php echo esc_html( $mcq_lim ); ?> | &ge; <?php echo esc_html( $mcq_p_lim ); ?></span>
                                    </th>

                                    <!-- CQ Column -->
                                    <th style="width: 14%;">
                                        <?php esc_html_e( 'CQ Theory', 'ifsedu-school-management' ); ?><br>
                                        <span class="ifs-educore-criteria-pill">Max: <?php echo esc_html( $cq_lim ); ?> | &ge; <?php echo esc_html( $cq_p_lim ); ?></span>
                                    </th>

                                    <?php if ( $pr_lim > 0 ) : ?>
                                        <th style="width: 14%;">
                                            <?php esc_html_e( 'Practical', 'ifsedu-school-management' ); ?><br>
                                            <span class="ifs-educore-criteria-pill">Max: <?php echo esc_html( $pr_lim ); ?> | &ge; <?php echo esc_html( $pr_p_lim ); ?></span>
                                        </th>
                                    <?php endif; ?>
                                    <th style="width: 10%;"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $student_internal_id = absint( $s->id );
                                    $curr_res = isset( $saved_marks[ $student_internal_id ] ) ? $saved_marks[ $student_internal_id ] : null;
                                    $curr_cq  = $curr_res ? floatval( $curr_res->cq_marks ) : '';
                                    $curr_mcq = $curr_res ? floatval( $curr_res->mcq_marks ) : '';
                                    $curr_pr  = $curr_res ? floatval( $curr_res->practical_marks ) : '';
                                    $curr_tot = $curr_res ? number_format( floatval( $curr_res->obtained_marks ), 2, '.', '' ) : '0.00';
                                    $curr_grd = $curr_res ? esc_html( (string) $curr_res->grade ) : '—';
                                    $curr_gpa = $curr_res ? number_format( floatval( $curr_res->gpa ), 2 ) : '0.00';
                                    $is_fail  = ( 'F' === $curr_grd );
                                ?>
                                    <tr data-student-id="<?php echo esc_attr( $student_internal_id ); ?>">
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                        <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                        
                                        <!-- MCQ Input -->
                                        <td>
                                            <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $mcq_lim ); ?>" 
                                                   name="mcq_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                   class="ifs-educore-mark-cell-input inp-mcq" 
                                                   data-max="<?php echo esc_attr( $mcq_lim ); ?>" 
                                                   value="<?php echo esc_attr( $curr_mcq ); ?>" placeholder="0">
                                        </td>

                                        <!-- CQ Input -->
                                        <td>
                                            <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $cq_lim ); ?>" 
                                                   name="cq_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                   class="ifs-educore-mark-cell-input inp-cq" 
                                                   data-max="<?php echo esc_attr( $cq_lim ); ?>" 
                                                   value="<?php echo esc_attr( $curr_cq ); ?>" placeholder="0">
                                        </td>

                                        <!-- Practical Input -->
                                        <?php if ( $pr_lim > 0 ) : ?>
                                            <td>
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $pr_lim ); ?>" 
                                                       name="practical_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-pr" 
                                                       data-max="<?php echo esc_attr( $pr_lim ); ?>" 
                                                       value="<?php echo esc_attr( $curr_pr ); ?>" placeholder="0">
                                            </td>
                                        <?php else : ?>
                                            <input type="hidden" name="practical_marks[<?php echo esc_attr( $student_internal_id ); ?>]" class="inp-pr" data-max="0" value="0">
                                        <?php endif; ?>

                                        <!-- Calculated Total -->
                                        <td><strong class="cell-total-obt" style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $curr_tot ); ?></strong></td>

                                        <!-- Evaluated Grade -->
                                        <td>
                                            <span class="ifs-educore-badge-grade cell-grade <?php echo $is_fail ? 'grade-fail' : ( '—' !== $curr_grd ? 'grade-pass' : '' ); ?>">
                                                <?php echo esc_html( $curr_grd ); ?>
                                            </span>
                                        </td>

                                        <!-- Evaluated GPA -->
                                        <td><strong class="cell-gpa" style="color: <?php echo $is_fail ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( $curr_gpa ); ?></strong></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="<?php echo ( $pr_lim > 0 ) ? 9 : 8; ?>" style="padding: 40px; color: #94a3b8;">
                                            <?php esc_html_e( 'No active students found matching the selected academic parameters.', 'ifsedu-school-management' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin-top: 24px;">
                            <button type="submit" name="educore_save_marks_matrix" class="ifs-educore-btn-submit">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Real-time Grading, Clamping, Session Storage & Unsaved Warning -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var totalLimit = parseFloat(document.getElementById('total_marks_limit').value) || 100;
                var passLimit  = parseFloat(document.getElementById('pass_marks_limit').value) || 33;
                var cqPass     = parseFloat(document.getElementById('cq_pass_limit').value) || 0;
                var mcqPass    = parseFloat(document.getElementById('mcq_pass_limit').value) || 0;
                var prPass     = parseFloat(document.getElementById('pr_pass_limit').value) || 0;

                var examId      = '<?php echo esc_js( $filter_exam ); ?>';
                var className   = '<?php echo esc_js( $filter_class ); ?>';
                var sectionName = '<?php echo esc_js( $filter_section ); ?>';
                var subjectName = '<?php echo esc_js( $filter_subject ); ?>';
                var storageKey  = 'educore_marks_draft_' + examId + '_' + className + '_' + sectionName + '_' + subjectName;

                var isDirty = false;

                function computeGradeAndGpa(obtained, total) {
                    var pct = (obtained / total) * 100;
                    if (pct >= 80) return { grade: 'A+', gpa: '5.00' };
                    if (pct >= 70) return { grade: 'A',  gpa: '4.00' };
                    if (pct >= 60) return { grade: 'A-', gpa: '3.50' };
                    if (pct >= 50) return { grade: 'B',  gpa: '3.00' };
                    if (pct >= 40) return { grade: 'C',  gpa: '2.00' };
                    if (pct >= 33) return { grade: 'D',  gpa: '1.00' };
                    return { grade: 'F', gpa: '0.00' };
                }

                function enforceBounds(input) {
                    var maxAllowed = parseFloat(input.getAttribute('data-max')) || 0;
                    var val = parseFloat(input.value);

                    if (val > maxAllowed) {
                        input.value = maxAllowed;
                        input.classList.add('is-clamped');
                        setTimeout(function() { input.classList.remove('is-clamped'); }, 400);
                    } else if (val < 0) {
                        input.value = 0;
                    }
                }

                function evaluateRow(row) {
                    var inpCq  = row.querySelector('.inp-cq');
                    var inpMcq = row.querySelector('.inp-mcq');
                    var inpPr  = row.querySelector('.inp-pr');

                    if (inpCq) enforceBounds(inpCq);
                    if (inpMcq) enforceBounds(inpMcq);
                    if (inpPr && inpPr.type !== 'hidden') enforceBounds(inpPr);

                    var valCq  = parseFloat(inpCq ? inpCq.value : 0) || 0;
                    var valMcq = parseFloat(inpMcq ? inpMcq.value : 0) || 0;
                    var valPr  = parseFloat(inpPr ? inpPr.value : 0) || 0;

                    var obtained = Math.min(valCq + valMcq + valPr, totalLimit);
                    row.querySelector('.cell-total-obt').textContent = obtained.toFixed(2);

                    var failed = false;
                    if (cqPass > 0 && valCq < cqPass) failed = true;
                    if (mcqPass > 0 && valMcq < mcqPass) failed = true;
                    if (prPass > 0 && valPr < prPass) failed = true;
                    if (obtained < passLimit) failed = true;

                    var gradeBadge = row.querySelector('.cell-grade');
                    var gpaCell    = row.querySelector('.cell-gpa');

                    if (failed) {
                        gradeBadge.textContent = 'F';
                        gradeBadge.className   = 'ifs-educore-badge-grade cell-grade grade-fail';
                        gpaCell.textContent    = '0.00';
                        gpaCell.style.color    = '#dc2626';
                    } else {
                        var res = computeGradeAndGpa(obtained, totalLimit);
                        gradeBadge.textContent = res.grade;
                        gradeBadge.className   = 'ifs-educore-badge-grade cell-grade grade-pass';
                        gpaCell.textContent    = res.gpa;
                        gpaCell.style.color    = '#00523c';
                    }
                }

                var table = document.getElementById('dptMarksEntryTable');
                var form  = document.getElementById('educoreMarksMatrixForm');

                // 1. Restore unsaved draft data from sessionStorage across page refresh
                if (table) {
                    try {
                        var savedDraft = JSON.parse(sessionStorage.getItem(storageKey));
                        if (savedDraft && typeof savedDraft === 'object') {
                            var restoredAny = false;
                            Object.keys(savedDraft).forEach(function(inputName) {
                                var input = form.querySelector('[name="' + inputName + '"]');
                                if (input) {
                                    input.value = savedDraft[inputName];
                                    restoredAny = true;
                                }
                            });
                            if (restoredAny) {
                                isDirty = true;
                                table.querySelectorAll('tbody tr').forEach(function(row) {
                                    evaluateRow(row);
                                });
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load session draft', e);
                    }

                    // 2. Track changes & save input into sessionStorage
                    table.addEventListener('input', function(e) {
                        if (e.target.classList.contains('ifs-educore-mark-cell-input') || e.target.classList.contains('dpt-mark-cell-input')) {
                            isDirty = true;
                            var row = e.target.closest('tr');
                            if (row) evaluateRow(row);

                            try {
                                var draftData = JSON.parse(sessionStorage.getItem(storageKey)) || {};
                                draftData[e.target.name] = e.target.value;
                                sessionStorage.setItem(storageKey, JSON.stringify(draftData));
                            } catch (err) {
                                console.error('Failed to save to sessionStorage', err);
                            }
                        }
                    });
                }

                // 3. Clear draft and dirty state on explicit save submission
                if (form) {
                    form.addEventListener('submit', function() {
                        isDirty = false;
                        sessionStorage.removeItem(storageKey);
                    });
                }

                // 4. Trigger browser warning if trying to refresh / leave with unsaved changes
                window.addEventListener('beforeunload', function(e) {
                    if (isDirty) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}