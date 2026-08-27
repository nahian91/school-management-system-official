<?php
/**
 * High-End Academic Progress Marksheet & Tabulation Engine
 * File: inc/results/exams-report.php
 * Text Domain: ifsedu-school-management
 * Architecture: Neo-Bento Interface with Print-Ready Layouts & Security Controls
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. AJAX Handler for Dynamic Section Loading based on Class
add_action( 'wp_ajax_ifs_educore_get_sections_by_class', 'ifs_educore_get_sections_by_class_report_handler' );
function ifs_educore_get_sections_by_class_report_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

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

    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY section_name ASC",
            $class_name,
            $clean_class
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

// 2. AJAX Handler for Dynamic Student Fetching based on Class & Section
add_action( 'wp_ajax_ifs_educore_get_students_by_class', 'ifs_educore_get_students_by_class_handler' );
function ifs_educore_get_students_by_class_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

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

    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $clean_class,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $clean_class
            )
        );
    }
    // phpcs:enable

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $data[] = array(
                'id'         => absint( $s->id ),
                'full_name'  => esc_html( $s->full_name ),
                'student_id' => esc_html( (string) $s->student_id ),
                'roll_no'    => esc_html( $s->roll_no ),
            );
        }
    }

    wp_send_json_success( $data );
}

function educore_exams_report_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Security Validation
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
        wp_die( esc_html__( 'You do not have sufficient permissions to generate academic reports.', 'ifsedu-school-management' ) );
    }

    $base_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'results',
            'sub'  => 'reports',
        ),
        admin_url( 'admin.php' )
    );

    // Fetch Exams along with their associated class details
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM `{$table_exams}` ORDER BY id DESC" );
    // phpcs:enable

    // Build Exam-to-Classes Map
    $exam_class_map = array();
    foreach ( $exams as $ex_item ) {
        $exam_class_map[ $ex_item->id ] = array();
        if ( ! empty( $ex_item->class_name ) ) {
            $classes_array = array_map( 'trim', explode( ',', (string) $ex_item->class_name ) );
            $exam_class_map[ $ex_item->id ] = array_filter( $classes_array );
        }
    }

    // Global classes fallback
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    // phpcs:enable

    if ( ! empty( $all_classes_raw ) && is_array( $all_classes_raw ) ) {
        $all_classes_raw = array_values( array_unique( $all_classes_raw ) );
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // GET Filter Parameters Sanitization
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $report_type    = isset( $_GET['report_type'] ) ? sanitize_key( wp_unslash( $_GET['report_type'] ) ) : 'individual';
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    // Fetch available sections for selected class if present
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY section_name ASC",
                $filter_class,
                $clean_class
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

    $back_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'results',
            'sub'  => 'marks',
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <div class="ifs-educore-report-root">
        
        <!-- Header Block -->
        <div class="ifs-educore-header-block no-print">
            <h2>
                <span class="dashicons dashicons-clipboard" style="color:#00523c;"></span>
                <?php esc_html_e( 'Academic Progress Marksheet & Tabulation Engine', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Marks Entry', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Generator Control Bento Card -->
        <div class="ifs-educore-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreReportFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'report_type', 'class_name', 'section_name', 'student_id' ), true ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                
                <div class="ifs-educore-filter-grid">
                    <!-- 1. Exam Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="ifs_educore_report_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Report Type -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Report Type', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="report_type" id="ifs_educore_report_type" class="ifs-educore-select-field" required>
                            <option value="individual" <?php selected( $report_type, 'individual' ); ?>><?php esc_html_e( 'Student Marksheet', 'ifsedu-school-management' ); ?></option>
                            <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>><?php esc_html_e( 'Class Tabulation Sheet', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 3. Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Exam Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_class_filter" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Exam First --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '4. Section', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_section_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 5. Student Selection -->
                    <div class="ifs-educore-form-group" id="student_select_box" style="<?php echo ( 'tabulation' === $report_type ) ? 'display:none;' : ''; ?>">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '5. Target Student', 'ifsedu-school-management' ); ?></label>
                        <select name="student_id" id="ifs_educore_student_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                            <?php 
                            if ( ! empty( $filter_class ) ) {
                                $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
                                $student_list = array();
                                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                                if ( ! empty( $filter_section ) ) {
                                    $student_list = $wpdb->get_results(
                                        $wpdb->prepare(
                                            "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                                            $filter_class,
                                            $clean_class,
                                            $filter_section
                                        )
                                    );
                                } else {
                                    $student_list = $wpdb->get_results(
                                        $wpdb->prepare(
                                            "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                                            $filter_class,
                                            $clean_class
                                        )
                                    );
                                }
                                // phpcs:enable

                                if ( ! empty( $student_list ) ) {
                                    foreach ( $student_list as $s ) : 
                                        $student_internal_id = absint( $s->id );
                                    ?>
                                        <option value="<?php echo esc_attr( $student_internal_id ); ?>" <?php selected( $filter_student, $student_internal_id ); ?>>
                                            <?php
                                            printf(
                                                /* translators: 1: Student roll number, 2: Student full name, 3: Student ID */
                                                esc_html__( 'Roll %1$s: %2$s (%3$s)', 'ifsedu-school-management' ),
                                                esc_html( $s->roll_no ),
                                                esc_html( $s->full_name ),
                                                esc_html( (string) $s->student_id )
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach;
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- 6. Submit Button -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Dropdown AJAX Controller Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce        = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';
            var examClassMap = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
            var allClasses   = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
            var currentClass = "<?php echo esc_js( $filter_class ); ?>";
            var currentSection = "<?php echo esc_js( $filter_section ); ?>";

            function toggleStudentBox() {
                if ($('#ifs_educore_report_type').val() === 'tabulation') {
                    $('#student_select_box').hide();
                } else {
                    $('#student_select_box').show();
                }
            }

            $('#ifs_educore_report_type').on('change', function() {
                toggleStudentBox();
            });

            function populateExamClasses(examId, selectedClass) {
                var $classSelect = $('#ifs_educore_class_filter');
                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Class --', 'ifsedu-school-management' ) ); ?></option>');

                if (!examId) {
                    $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Exam First --', 'ifsedu-school-management' ) ); ?></option>');
                    $('#ifs_educore_section_filter').html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    $('#ifs_educore_student_filter').html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                var classesToLoad = (examClassMap[examId] && examClassMap[examId].length > 0) ? examClassMap[examId] : allClasses;

                $.each(classesToLoad, function(i, cls) {
                    var sel = (cls === selectedClass) ? 'selected' : '';
                    var displayCls = (/^class\s+/i.test(cls)) ? cls : 'Class ' + cls;
                    $classSelect.append('<option value="' + cls + '" ' + sel + '>' + displayCls + '</option>');
                });
            }

            $('#ifs_educore_report_exam_select').on('change', function() {
                populateExamClasses($(this).val(), '');
                $('#ifs_educore_class_filter').trigger('change');
            });

            $('#ifs_educore_class_filter').on('change', function() {
                var selectedClass  = $(this).val();
                var $sectionSelect = $('#ifs_educore_section_filter');

                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    reloadStudents();
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                var sel = (sec === currentSection) ? 'selected' : '';
                                secOptions += '<option value="' + sec + '" ' + sel + '>' + sec + '</option>';
                            });
                            $sectionSelect.html(secOptions);
                        }
                        reloadStudents();
                    }
                });
            });

            $('#ifs_educore_section_filter').on('change', function() {
                reloadStudents();
            });

            function reloadStudents() {
                var selectedClass   = $('#ifs_educore_class_filter').val();
                var selectedSection = $('#ifs_educore_section_filter').val();
                var $studentSelect  = $('#ifs_educore_student_filter');

                $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Students... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_students_by_class',
                        security: nonce,
                        class_name: selectedClass,
                        section_name: selectedSection
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(index, student) {
                                options += '<option value="' + student.id + '">Roll ' + student.roll_no + ': ' + student.full_name + ' (' + student.student_id + ')</option>';
                            });
                            $studentSelect.html(options);
                        } else {
                            $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            }

            if ($('#ifs_educore_report_exam_select').val()) {
                populateExamClasses($('#ifs_educore_report_exam_select').val(), currentClass);
            }
        });
        </script>

        <?php
        // ==========================================================================
        // CASE A: INDIVIDUAL STUDENT MARKSHEET REPORT
        // ==========================================================================
        if ( $filter_exam > 0 && 'individual' === $report_type && $filter_student > 0 ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_students}` WHERE id = %d LIMIT 1", $filter_student ) );
            $exam    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
            
            // Join with academic units & subjects to retrieve exact subject_order
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, COALESCE(s.subject_order, 999) AS subject_order 
                     FROM `{$table_results}` r
                     LEFT JOIN `{$table_units}` u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, 'Class ', '')))
                     LEFT JOIN `{$table_subjects}` s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                     WHERE r.exam_id = %d AND r.student_id = %d 
                     GROUP BY r.id
                     ORDER BY subject_order ASC, r.subject_name ASC",
                    $filter_exam,
                    $filter_student
                )
            );
            // phpcs:enable

            if ( ! $results ) {
                echo '<div class="ifs-educore-status-banner no-print">' . esc_html__( 'No published marks found for this student in the selected examination.', 'ifsedu-school-management' ) . '</div>';
                echo '</div>';
                return;
            }

            $total_sub          = count( $results );
            $sum_gpa            = 0;
            $total_marks_all    = 0;
            $obtained_marks_all = 0;
            $has_failed         = false;

            foreach ( $results as $r ) {
                $sum_gpa            += floatval( $r->gpa );
                $total_marks_all    += floatval( $r->total_marks );
                $obtained_marks_all += floatval( $r->obtained_marks );
                if ( strtoupper( trim( (string) $r->grade ) ) === 'F' || floatval( $r->gpa ) <= 0 ) {
                    $has_failed = true;
                }
            }

            $avg_gpa    = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
            $final_gpa  = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
            
            // Derive Final Grade
            $final_grade = 'F';
            if ( ! $has_failed ) {
                if ( $avg_gpa >= 5.0 ) {
                    $final_grade = 'A+';
                } elseif ( $avg_gpa >= 4.0 ) {
                    $final_grade = 'A';
                } elseif ( $avg_gpa >= 3.5 ) {
                    $final_grade = 'A-';
                } elseif ( $avg_gpa >= 3.0 ) {
                    $final_grade = 'B';
                } elseif ( $avg_gpa >= 2.0 ) {
                    $final_grade = 'C';
                } elseif ( $avg_gpa >= 1.0 ) {
                    $final_grade = 'D';
                }
            }
            ?>

            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button type="button" onclick="window.print();" class="ifs-educore-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Professional Marksheet', 'ifsedu-school-management' ); ?>
                </button>
            </div>

            <div class="ifs-educore-report-card-container">
                <div class="ifs-educore-report-header">
                    <div class="ifs-educore-header-brand-row">
                        <?php if ( ! empty( $school_logo ) ) : ?>
                            <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                        <?php endif; ?>
                        <h2 class="ifs-educore-header-title"><?php echo esc_html( $school_name ); ?></h2>
                    </div>
                    <?php if ( ! empty( $school_tagline ) ) : ?>
                        <div class="ifs-educore-header-sub"><?php echo esc_html( $school_tagline ); ?></div>
                    <?php endif; ?>
                    <h4 style="margin: 6px 0 4px 0; font-weight: 700; color: #1e293b; font-size: 15px;"><?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Academic Marksheet', 'ifsedu-school-management' ); ?></h4>
                </div>

                <!-- Grading Scale Reference -->
                <table class="ifs-educore-grading-legend-table">
                    <thead>
                        <tr>
                            <th>Marks</th>
                            <th>80-100%</th>
                            <th>70-79%</th>
                            <th>60-69%</th>
                            <th>50-59%</th>
                            <th>40-49%</th>
                            <th>33-39%</th>
                            <th>0-32%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Grade / GP</strong></td>
                            <td>A+ (5.00)</td>
                            <td>A (4.00)</td>
                            <td>A- (3.50)</td>
                            <td>B (3.00)</td>
                            <td>C (2.00)</td>
                            <td>D (1.00)</td>
                            <td>F (0.00)</td>
                        </tr>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 13px; line-height: 1.6;">
                    <div>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Student Name:', 'ifsedu-school-management' ); ?></strong> <span style="text-transform: uppercase; font-weight: 800; color:#0f172a;"><?php echo esc_html( $student->full_name ); ?></span></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Student ID:', 'ifsedu-school-management' ); ?></strong> <code><?php echo esc_html( (string) $student->student_id ); ?></code></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Guardian:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( ! empty( $student->guardian_name ) ? $student->guardian_name : $student->father_name ); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $student->class_name ); ?></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : __( 'N/A', 'ifsedu-school-management' ) ); ?></p>
                        <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Roll Number:', 'ifsedu-school-management' ); ?></strong> <span style="background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-weight: 800;">#<?php echo esc_html( $student->roll_no ); ?></span></p>
                    </div>
                </div>

                <table class="ifs-educore-marks-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 32%;"><?php esc_html_e( 'Subject Name', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Full Marks', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'CQ', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'PR', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Obtained', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'GP', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $results as $r ) : 
                            $row_failed = ( 'F' === strtoupper( trim( (string) $r->grade ) ) || floatval( $r->gpa ) <= 0 );
                        ?>
                        <tr>
                            <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $r->subject_name ); ?></td>
                            <td><?php echo floatval( $r->total_marks ); ?></td>
                            <td><?php echo isset( $r->mcq_marks ) ? floatval( $r->mcq_marks ) : '—'; ?></td>
                            <td><?php echo isset( $r->cq_marks ) ? floatval( $r->cq_marks ) : '—'; ?></td>
                            <td><?php echo isset( $r->practical_marks ) ? floatval( $r->practical_marks ) : '—'; ?></td>
                            <td><strong><?php echo floatval( $r->obtained_marks ); ?></strong></td>
                            <td style="font-weight: 800; color: <?php echo $row_failed ? '#dc2626' : '#059669'; ?>;"><?php echo esc_html( $r->grade ); ?></td>
                            <td><strong style="color: <?php echo $row_failed ? '#dc2626' : '#00523c'; ?>;"><?php echo number_format( floatval( $r->gpa ), 2 ); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="ifs-educore-gpa-box">
                    <h4 style="margin: 0; font-weight: 800; color: #00523c; text-transform: uppercase; font-size: 14px; letter-spacing: 0.5px;"><?php esc_html_e( 'Final Result Summary', 'ifsedu-school-management' ); ?></h4>
                    <p style="font-size: 14.5px; margin: 6px 0 0 0; color: #1e293b;">
                        <?php esc_html_e( 'Status:', 'ifsedu-school-management' ); ?> 
                        <strong style="color: <?php echo $has_failed ? '#dc2626' : '#059669'; ?>;">
                            <?php
                            echo $has_failed
                                ? esc_html__( 'FAILED (F)', 'ifsedu-school-management' )
                                : sprintf(
                                    /* translators: %s: Final letter grade (e.g., A+, A, B) */
                                    esc_html__( 'PASSED (%s)', 'ifsedu-school-management' ),
                                    esc_html( $final_grade )
                                );
                            ?>
                        </strong> &nbsp;|&nbsp; 
                        <?php esc_html_e( 'Total Score:', 'ifsedu-school-management' ); ?> <strong><?php echo floatval( $obtained_marks_all ); ?> / <?php echo floatval( $total_marks_all ); ?></strong> &nbsp;|&nbsp;
                        <?php esc_html_e( 'GPA:', 'ifsedu-school-management' ); ?> <strong style="font-size: 16px; color: #00523c;"><?php echo esc_html( $final_gpa ); ?></strong>
                    </p>
                </div>

                <div class="ifs-educore-sign-row">
                    <div class="ifs-educore-signature-col">
                        <div class="ifs-educore-sign-line"><?php esc_html_e( 'Class Teacher Signature', 'ifsedu-school-management' ); ?></div>
                    </div>
                    <div class="ifs-educore-signature-col">
                        <div class="ifs-educore-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                    </div>
                    <div class="ifs-educore-signature-col">
                        <?php if ( ! empty( $principal_sig ) ) : ?>
                            <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-sig-img">
                        <?php endif; ?>
                        <div class="ifs-educore-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                    </div>
                </div>
            </div>
            <?php
        }

        // ==========================================================================
        // CASE B: CLASS TABULATION SHEET REPORT
        // ==========================================================================
        elseif ( $filter_exam > 0 && 'tabulation' === $report_type && ! empty( $filter_class ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
            $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
            
            $students = array();
            if ( ! empty( $filter_section ) ) {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                        $filter_class,
                        $clean_class,
                        $filter_section
                    )
                );
            } else {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                        $filter_class,
                        $clean_class
                    )
                );
            }

            // Fetch subject columns ordered according to subject_order configured in academic settings
            $subjects = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT r.subject_name 
                     FROM `{$table_results}` r
                     LEFT JOIN `{$table_units}` u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, 'Class ', '')))
                     LEFT JOIN `{$table_subjects}` s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                     WHERE r.exam_id = %d AND (r.class_name = %s OR r.class_name = %s)
                     GROUP BY r.subject_name
                     ORDER BY MIN(COALESCE(s.subject_order, 999)) ASC, r.subject_name ASC",
                    $filter_exam,
                    $filter_class,
                    $clean_class
                )
            );
            // phpcs:enable

            if ( ! $students || ! $subjects ) {
                $sec_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                $empty_tab_notice = sprintf(
                    /* translators: 1: Class name, 2: Section label */
                    esc_html__( 'No evaluated marks or subject entries found for %1$s%2$s in this exam.', 'ifsedu-school-management' ),
                    '<strong>' . esc_html( $filter_class ) . '</strong>',
                    '<strong>' . esc_html( $sec_label ) . '</strong>'
                );
                echo '<div class="ifs-educore-status-banner no-print">' . wp_kses_post( $empty_tab_notice ) . '</div>';
                echo '</div>';
                return;
            }

            // Pre-calculate Summary Metrics across all students in Tabulation
            $total_students_count = count( $students );
            $passed_count         = 0;
            $failed_count         = 0;
            $grade_counts         = array(
                'A+' => 0,
                'A'  => 0,
                'A-' => 0,
                'B'  => 0,
                'C'  => 0,
                'D'  => 0,
                'F'  => 0,
            );

            // Pre-fetch all results for fast array lookup
            $all_student_ids = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
            $results_map = array();
            if ( ! empty( $all_student_ids ) ) {
                $in_placeholders = implode( ',', array_fill( 0, count( $all_student_ids ), '%d' ) );
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
               $in_placeholders = implode( ',', array_map( 'absint', $all_student_ids ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$raw_tab_results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT student_id, subject_name, obtained_marks, grade, gpa 
         FROM `{$table_results}` 
         WHERE exam_id = %d AND student_id IN ({$in_placeholders})",
        $filter_exam
    )
);
// phpcs:enable

                if ( ! empty( $raw_tab_results ) ) {
                    foreach ( $raw_tab_results as $r_item ) {
                        $results_map[ $r_item->student_id ][ $r_item->subject_name ] = $r_item;
                    }
                }
            }

            // Calculate aggregate statistics
            foreach ( $students as $s_calc ) {
                $student_calc_id = absint( $s_calc->id );
                $st_res     = isset( $results_map[ $student_calc_id ] ) ? $results_map[ $student_calc_id ] : array();
                $s_sum_gpa  = 0;
                $s_sub_cnt  = 0;
                $s_failed   = false;

                foreach ( $subjects as $sub_k ) {
                    if ( isset( $st_res[ $sub_k ] ) ) {
                        $s_sum_gpa += floatval( $st_res[ $sub_k ]->gpa );
                        $s_sub_cnt++;
                        if ( 'F' === strtoupper( trim( (string) $st_res[ $sub_k ]->grade ) ) || floatval( $st_res[ $sub_k ]->gpa ) <= 0 ) {
                            $s_failed = true;
                        }
                    }
                }

                if ( 0 === $s_sub_cnt || $s_failed ) {
                    $failed_count++;
                    $grade_counts['F']++;
                } else {
                    $passed_count++;
                    $s_avg = $s_sum_gpa / $s_sub_cnt;
                    if ( $s_avg >= 5.0 ) {
                        $grade_counts['A+']++;
                    } elseif ( $s_avg >= 4.0 ) {
                        $grade_counts['A']++;
                    } elseif ( $s_avg >= 3.5 ) {
                        $grade_counts['A-']++;
                    } elseif ( $s_avg >= 3.0 ) {
                        $grade_counts['B']++;
                    } elseif ( $s_avg >= 2.0 ) {
                        $grade_counts['C']++;
                    } elseif ( $s_avg >= 1.0 ) {
                        $grade_counts['D']++;
                    } else {
                        $grade_counts['F']++;
                    }
                }
            }

            $pass_percentage = ( $total_students_count > 0 ) ? number_format( ( $passed_count / $total_students_count ) * 100, 1 ) : 0;
            ?>

            <div style="text-align: center; margin-bottom: 24px;" class="no-print">
                <button type="button" onclick="window.print();" class="ifs-educore-btn-submit-trigger" style="width: auto; padding: 0 32px; font-size: 14px;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Class Tabulation Sheet', 'ifsedu-school-management' ); ?>
                </button>
            </div>

            <div class="ifs-educore-tabulation-container">
                <div class="ifs-educore-report-header">
                    <div class="ifs-educore-header-brand-row">
                        <?php if ( ! empty( $school_logo ) ) : ?>
                            <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                        <?php endif; ?>
                        <h3 class="ifs-educore-header-title"><?php echo esc_html( $school_name ); ?></h3>
                    </div>
                    <?php if ( ! empty( $school_tagline ) ) : ?>
                        <div class="ifs-educore-header-sub"><?php echo esc_html( $school_tagline ); ?></div>
                    <?php endif; ?>
                    <h5 style="margin: 6px 0 0 0; font-weight: 700; color: #1e293b; font-size: 14px;"><?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Academic Tabulation Sheet', 'ifsedu-school-management' ); ?></h5>
                    <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e1;">
                        <?php echo esc_html( preg_match( '/^class\s+/i', (string) $filter_class ) ? $filter_class : 'Class ' . $filter_class ); ?>
                        <?php if ( ! empty( $filter_section ) ) : ?>
                            (<?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_section ); ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <!-- TOP SUMMARY METRICS DASHBOARD -->
                <div class="ifs-educore-summary-dashboard">
                    <div class="ifs-educore-summary-card">
                        <div class="ifs-educore-summary-label"><?php esc_html_e( 'Total Students', 'ifsedu-school-management' ); ?></div>
                        <div class="ifs-educore-summary-val" style="color: #0f172a;"><?php echo esc_html( $total_students_count ); ?></div>
                    </div>
                    <div class="ifs-educore-summary-card" style="background:#f0fdf4; border-color:#bbf7d0;">
                        <div class="ifs-educore-summary-label" style="color:#15803d;"><?php esc_html_e( 'Passed', 'ifsedu-school-management' ); ?></div>
                        <div class="ifs-educore-summary-val" style="color:#166534;"><?php echo esc_html( $passed_count ); ?></div>
                    </div>
                    <div class="ifs-educore-summary-card" style="background:#fef2f2; border-color:#fecaca;">
                        <div class="ifs-educore-summary-label" style="color:#b91c1c;"><?php esc_html_e( 'Failed', 'ifsedu-school-management' ); ?></div>
                        <div class="ifs-educore-summary-val" style="color:#dc2626;"><?php echo esc_html( $failed_count ); ?></div>
                    </div>
                    <div class="ifs-educore-summary-card" style="background:#eff6ff; border-color:#bfdbfe;">
                        <div class="ifs-educore-summary-label" style="color:#1d4ed8;"><?php esc_html_e( 'Pass Rate', 'ifsedu-school-management' ); ?></div>
                        <div class="ifs-educore-summary-val" style="color:#1e40af;"><?php echo esc_html( $pass_percentage ); ?>%</div>
                    </div>
                </div>

                <!-- GRADE BREAKDOWN PILLS BAR -->
                <div class="ifs-educore-grade-counts-bar">
                    <div style="font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; margin-right:6px;">
                        <span class="dashicons dashicons-chart-pie" style="font-size:16px; width:16px; height:16px; vertical-align:middle;"></span>
                        <?php esc_html_e( 'Grade Breakdown:', 'ifsedu-school-management' ); ?>
                    </div>
                    <span class="ifs-educore-grade-pill grade-aplus">A+: <strong><?php echo esc_html( $grade_counts['A+'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-a">A: <strong><?php echo esc_html( $grade_counts['A'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-aminus">A-: <strong><?php echo esc_html( $grade_counts['A-'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-b">B: <strong><?php echo esc_html( $grade_counts['B'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-c">C: <strong><?php echo esc_html( $grade_counts['C'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-d">D: <strong><?php echo esc_html( $grade_counts['D'] ); ?></strong></span>
                    <span class="ifs-educore-grade-pill grade-f">F: <strong><?php echo esc_html( $grade_counts['F'] ); ?></strong></span>
                </div>

                <!-- Horizontal Scrollbar Wrapper -->
                <div class="ifs-educore-tabulation-scroll-wrapper">
                    <table class="ifs-educore-tabulation-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 90px;"><?php esc_html_e( 'ID', 'ifsedu-school-management' ); ?></th>
                                <th style="min-width: 160px; text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                <?php foreach ( $subjects as $sub ) : ?>
                                    <th style="min-width: 110px;"><?php echo esc_html( $sub ); ?></th>
                                <?php endforeach; ?>
                                <th style="min-width: 85px;"><?php esc_html_e( 'Total Score', 'ifsedu-school-management' ); ?></th>
                                <th style="min-width: 70px;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                <th style="min-width: 75px;"><?php esc_html_e( 'Result', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $students as $s ) : 
                                $student_tab_id = absint( $s->id );
                                $student_results = isset( $results_map[ $student_tab_id ] ) ? $results_map[ $student_tab_id ] : array();

                                $total_obtained = 0;
                                $sum_gpa        = 0;
                                $sub_count      = 0;
                                $has_failed     = false;
                            ?>
                            <tr>
                                <td><strong style="color: #0f172a;">#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                <td><code><?php echo esc_html( (string) $s->student_id ); ?></code></td>
                                <td style="text-align: left; font-weight: 700; color: #0f172a; white-space: nowrap;"><?php echo esc_html( $s->full_name ); ?></td>
                                
                                <?php foreach ( $subjects as $sub ) : 
                                    if ( isset( $student_results[ $sub ] ) ) {
                                        $res = $student_results[ $sub ];
                                        $total_obtained += floatval( $res->obtained_marks );
                                        $sum_gpa        += floatval( $res->gpa );
                                        $sub_count++;
                                        if ( 'F' === strtoupper( trim( (string) $res->grade ) ) || floatval( $res->gpa ) <= 0 ) {
                                            $has_failed = true;
                                        }
                                        $sub_failed = ( 'F' === strtoupper( trim( (string) $res->grade ) ) || floatval( $res->gpa ) <= 0 );
                                        ?>
                                        <td>
                                            <strong><?php echo floatval( $res->obtained_marks ); ?></strong><br>
                                            <small style="font-weight: 700; color: <?php echo $sub_failed ? '#dc2626' : '#059669'; ?>;">(<?php echo esc_html( $res->grade ); ?>)</small>
                                        </td>
                                    <?php } else { ?>
                                        <td style="color: #94a3b8;">—</td>
                                    <?php }
                                endforeach; 

                                $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                                $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                                ?>

                                <td style="font-weight: 800; color:#0f172a;"><?php echo floatval( $total_obtained ); ?></td>
                                <td style="font-weight: 800; color: <?php echo $has_failed ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( $final_gpa ); ?></td>
                                <td>
                                    <span style="padding: 3px 10px; border-radius: 20px; font-weight: 800; font-size: 11px; background: <?php echo $has_failed ? '#fef2f2' : '#ecfdf5'; ?>; color: <?php echo $has_failed ? '#dc2626' : '#059669'; ?>; border: 1px solid <?php echo $has_failed ? '#fecaca' : '#a7f3d0'; ?>;">
                                        <?php echo $has_failed ? esc_html__( 'FAIL', 'ifsedu-school-management' ) : esc_html__( 'PASS', 'ifsedu-school-management' ); ?>
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
                            <img src="<?php echo esc_url( $principal_sig ); ?>" alt="Signature" class="ifs-educore-sig-img">
                        <?php endif; ?>
                        <div class="ifs-educore-sign-line"><?php esc_html_e( 'Headmaster / Principal', 'ifsedu-school-management' ); ?></div>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

    </div>
    <?php
}