<?php
/**
 * Add / Edit Examination Scheme View & Controller
 * File: inc/exams/exam-add-edit.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_exam_add_edit_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to configure examinations.', 'ifsedu-school-management' ) );
    }

    $table_exams = $wpdb->prefix . 'sms_exams';
    $table_units = $wpdb->prefix . 'sms_academic_units';

    $list_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $is_edit   = ( 'edit' === $get_action && $get_id > 0 );
    $edit_exam = null;

    $edit_exam_title  = '';
    $edit_exam_year   = current_time( 'Y' );
    $selected_classes = array();

    if ( $is_edit ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $edit_exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $get_id ) );
        // phpcs:enable
        if ( $edit_exam ) {
            $parts = explode( ' - ', (string) $edit_exam->exam_name );
            if ( count( $parts ) > 1 && is_numeric( end( $parts ) ) ) {
                $edit_exam_year  = array_pop( $parts );
                $edit_exam_title = implode( ' - ', $parts );
            } else {
                $edit_exam_title = $edit_exam->exam_name;
            }

            if ( ! empty( $edit_exam->class_name ) ) {
                $selected_classes = array_map( 'trim', explode( ',', (string) $edit_exam->class_name ) );
            }
        } else {
            $is_edit = false;
        }
    }

    // --------------------------------------------------------------------------
    // Handle Save / Update Form Submission
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_exam_action'] ) && 'save_exam' === $_POST['educore_exam_action'] ) {
        check_admin_referer( 'save_exam_action', 'ifs_educore_exam_nonce' );

        $exam_id_input    = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
        $exam_title_input = isset( $_POST['exam_title'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_title'] ) ) : '';
        $exam_year_input  = isset( $_POST['exam_year'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_year'] ) ) : current_time( 'Y' );
        $full_exam_name   = ! empty( $exam_year_input ) ? $exam_title_input . ' - ' . $exam_year_input : $exam_title_input;

        $class_names_input = ( isset( $_POST['class_name'] ) && is_array( $_POST['class_name'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['class_name'] ) ) : array();
        $class_name        = ! empty( $class_names_input ) ? implode( ', ', $class_names_input ) : '';

        $start_date = ! empty( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : current_time( 'Y-m-d' );
        $end_date   = ! empty( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : current_time( 'Y-m-d' );
        $status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Upcoming';

        $data = array(
            'exam_name'  => $full_exam_name,
            'class_name' => $class_name,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'status'     => $status,
        );
        $format = array( '%s', '%s', '%s', '%s', '%s' );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $exam_id_input > 0 ) {
            $wpdb->update( $table_exams, $data, array( 'id' => $exam_id_input ), $format, array( '%d' ) );
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %s: Exam Name */
                educore_log_activity( sprintf( __( 'Updated Examination Scheme: %s', 'ifsedu-school-management' ), $full_exam_name ) );
            }
            $redirect_target = add_query_arg( array( 'status' => 'updated' ), $list_url );
        } else {
            $wpdb->insert( $table_exams, $data, $format );
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %s: Exam Name */
                educore_log_activity( sprintf( __( 'Created Examination Scheme: %s', 'ifsedu-school-management' ), $full_exam_name ) );
            }
            $redirect_target = add_query_arg( array( 'status' => 'success' ), $list_url );
        }
        // phpcs:enable

        if ( ! headers_sent() ) {
            wp_safe_redirect( $redirect_target );
            exit;
        }

        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
        exit;
    }

    // =========================================================================
    // Query Distinct Class Names Ordered by sort_order
    // =========================================================================
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
    );

    if ( empty( $raw_classes_data ) ) {
        $alt_table = $wpdb->get_var( "SHOW TABLES LIKE '%sms_academic_units'" );
        if ( $alt_table ) {
            $raw_classes_data = $wpdb->get_results( 
                "SELECT class_name, MIN(sort_order) as min_sort 
                 FROM `{$alt_table}` 
                 WHERE class_name IS NOT NULL AND class_name != '' 
                 GROUP BY class_name 
                 ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
            );
        }
    }
    // phpcs:enable

    $class_list = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $c_name = trim( (string) $c_row->class_name );
            if ( ! empty( $c_name ) && ! in_array( $c_name, $class_list, true ) ) {
                $class_list[] = $c_name;
            }
        }
    }
    ?>

    <style>
        .ifs-educore-exam-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 30px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03) !important;
            max-width: 960px !important;
            margin: 20px 0 !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-form-group {
            margin-bottom: 22px !important;
        }
        .ifs-educore-form-label {
            display: block !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #334155 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            margin-bottom: 8px !important;
        }
        .ifs-educore-input-field,
        .ifs-educore-select-field {
            width: 100% !important;
            height: 44px !important;
            padding: 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            color: #0f172a !important;
            background: #ffffff !important;
            box-sizing: border-box !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-input-field:focus,
        .ifs-educore-select-field:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }

        /* Class Selector Panel */
        .ifs-class-selector-panel {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 16px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-panel-toolbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-bottom: 14px !important;
            padding-bottom: 12px !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .ifs-class-search-input {
            height: 34px !important;
            padding: 0 12px !important;
            font-size: 13px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            max-width: 220px !important;
            outline: none !important;
            box-sizing: border-box !important;
        }
        .ifs-class-search-input:focus {
            border-color: #00523c !important;
        }
        .ifs-class-toolbar-actions {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        .ifs-class-count-badge {
            font-size: 12px !important;
            font-weight: 700 !important;
            background: #e2e8f0 !important;
            color: #475569 !important;
            padding: 4px 10px !important;
            border-radius: 999px !important;
            transition: all 0.2s ease !important;
        }
        .ifs-class-count-badge.has-selected {
            background: #dcfce7 !important;
            color: #15803d !important;
        }
        .ifs-class-btn-toggle {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            padding: 5px 12px !important;
            border-radius: 6px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        .ifs-class-btn-toggle:hover {
            background: #00523c !important;
            color: #ffffff !important;
            border-color: #00523c !important;
        }

        /* Class Chip Grid */
        .ifs-class-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
            gap: 10px !important;
            max-height: 250px !important;
            overflow-y: auto !important;
            padding: 2px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: #ffffff !important;
            border: 1.5px solid #e2e8f0 !important;
            padding: 9px 12px !important;
            border-radius: 8px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card:hover {
            border-color: #94a3b8 !important;
            background: #f8fafc !important;
        }
        .ifs-class-card.is-active {
            border-color: #00523c !important;
            background: #f0fdf4 !important;
            color: #00523c !important;
        }
        .ifs-class-card input[type="checkbox"] {
            margin: 0 !important;
            width: 16px !important;
            height: 16px !important;
            cursor: pointer !important;
            accent-color: #00523c !important;
            flex-shrink: 0 !important;
        }
        .ifs-class-name {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .ifs-educore-btn-save {
            background: #00523c !important;
            color: #ffffff !important;
            border: none !important;
            padding: 12px 28px !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 14.5px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2) !important;
            transition: background 0.2s !important;
        }
        .ifs-educore-btn-save:hover {
            background: #047857 !important;
        }
    </style>

    <div class="ifs-educore-exam-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:24px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-edit" style="color:#00523c;"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Examination Scheme', 'ifsedu-school-management' ) : esc_html__( 'Create New Examination Scheme', 'ifsedu-school-management' ); ?>
            </h3>
            <a href="<?php echo esc_url( $list_url ); ?>" style="text-decoration:none; color:#475569; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:4px;">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to List', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <form method="POST" action="" id="educoreExamForm">
            <?php wp_nonce_field( 'save_exam_action', 'ifs_educore_exam_nonce' ); ?>
            <input type="hidden" name="educore_exam_action" value="save_exam">
            <input type="hidden" name="exam_id" value="<?php echo $is_edit ? absint( $edit_exam->id ) : 0; ?>">

            <!-- Exam Title & Academic Year -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'Exam Title', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="exam_title" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. First Term Examination / Annual Exam', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $edit_exam_title ); ?>" required>
                </div>
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'Academic Year', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="exam_year" class="ifs-educore-input-field" min="2020" max="2099" value="<?php echo esc_attr( $edit_exam_year ); ?>" required>
                </div>
            </div>

            <!-- Applicable Classes Matrix -->
            <div class="ifs-educore-form-group">
                <label class="ifs-educore-form-label"><?php esc_html_e( 'Applicable Classes', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                
                <div class="ifs-class-selector-panel">
                    <div class="ifs-class-panel-toolbar">
                        <input type="text" id="classSearchInput" class="ifs-class-search-input" placeholder="<?php esc_attr_e( 'Search class...', 'ifsedu-school-management' ); ?>" autocomplete="off">
                        
                        <div class="ifs-class-toolbar-actions">
                            <span class="ifs-class-count-badge" id="selectedClassCountBadge">0 Selected</span>
                            <button type="button" class="ifs-class-btn-toggle" id="btnToggleAllClasses"><?php esc_html_e( 'Select All', 'ifsedu-school-management' ); ?></button>
                        </div>
                    </div>

                    <div class="ifs-class-grid" id="classGridContainer">
                        <?php if ( ! empty( $class_list ) ) : foreach ( $class_list as $cls_name ) : 
                            $is_checked = in_array( $cls_name, $selected_classes, true );
                        ?>
                            <label class="ifs-class-card <?php echo $is_checked ? 'is-active' : ''; ?>" data-class-text="<?php echo esc_attr( strtolower( $cls_name ) ); ?>">
                                <input type="checkbox" name="class_name[]" value="<?php echo esc_attr( $cls_name ); ?>" class="cb-class" <?php checked( $is_checked ); ?>>
                                <span class="ifs-class-name"><?php echo esc_html( $cls_name ); ?></span>
                            </label>
                        <?php endforeach; else : ?>
                            <div style="font-size:13px; color:#ef4444; padding: 12px; font-weight:700; grid-column: 1 / -1; text-align: center;">
                                <?php esc_html_e( 'No classes configured yet.', 'ifsedu-school-management' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Start Date, End Date, Status -->
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'Start Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="start_date" class="ifs-educore-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->start_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'End Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="end_date" class="ifs-educore-input-field" value="<?php echo $is_edit ? esc_attr( $edit_exam->end_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                    <select name="status" class="ifs-educore-select-field">
                        <option value="Upcoming" <?php selected( $is_edit ? $edit_exam->status : '', 'Upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'ifsedu-school-management' ); ?></option>
                        <option value="Ongoing" <?php selected( $is_edit ? $edit_exam->status : '', 'Ongoing' ); ?>><?php esc_html_e( 'Ongoing', 'ifsedu-school-management' ); ?></option>
                        <option value="Completed" <?php selected( $is_edit ? $edit_exam->status : '', 'Completed' ); ?>><?php esc_html_e( 'Completed', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-top:24px; text-align:right;">
                <button type="submit" class="ifs-educore-btn-save">
                    <span class="dashicons dashicons-saved"></span>
                    <?php echo $is_edit ? esc_html__( 'Update Exam Scheme', 'ifsedu-school-management' ) : esc_html__( 'Save Exam Scheme', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var grid = document.getElementById('classGridContainer');
        var toggleBtn = document.getElementById('btnToggleAllClasses');
        var searchInput = document.getElementById('classSearchInput');
        var countBadge = document.getElementById('selectedClassCountBadge');
        var form = document.getElementById('educoreExamForm');

        function updateSelectionState() {
            var allCheckboxes = grid.querySelectorAll('.cb-class');
            var checkedCheckboxes = grid.querySelectorAll('.cb-class:checked');
            var count = checkedCheckboxes.length;

            countBadge.textContent = count + ' Selected';
            if (count > 0) {
                countBadge.classList.add('has-selected');
            } else {
                countBadge.classList.remove('has-selected');
            }

            allCheckboxes.forEach(function(cb) {
                var card = cb.closest('.ifs-class-card');
                if (card) {
                    if (cb.checked) {
                        card.classList.add('is-active');
                    } else {
                        card.classList.remove('is-active');
                    }
                }
            });

            if (toggleBtn) {
                var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
                var visibleChecked = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
                var allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked.length;
                toggleBtn.textContent = allVisibleChecked ? '<?php echo esc_js( __( 'Deselect All', 'ifsedu-school-management' ) ); ?>' : '<?php echo esc_js( __( 'Select All', 'ifsedu-school-management' ) ); ?>';
            }
        }

        // Live Search Filter
        if (searchInput && grid) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                var cards = grid.querySelectorAll('.ifs-class-card');
                cards.forEach(function(card) {
                    var text = card.getAttribute('data-class-text') || '';
                    if (!q || text.indexOf(q) !== -1) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
                updateSelectionState();
            });
        }

        // Checkbox change listener
        if (grid) {
            grid.addEventListener('change', function(e) {
                if (e.target.classList.contains('cb-class')) {
                    updateSelectionState();
                }
            });
        }

        // Toggle All visible classes
        if (toggleBtn && grid) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
                var visibleChecked = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
                var shouldCheckAll = visibleCheckboxes.length !== visibleChecked.length;

                visibleCheckboxes.forEach(function(cb) {
                    cb.checked = shouldCheckAll;
                });
                updateSelectionState();
            });
        }

        // Form Validation
        if (form) {
            form.addEventListener('submit', function(e) {
                var checkedClasses = form.querySelectorAll('input[name="class_name[]"]:checked');
                if (checkedClasses.length === 0) {
                    e.preventDefault();
                    alert('<?php echo esc_js( __( 'Please select at least one class.', 'ifsedu-school-management' ) ); ?>');
                }
            });
        }

        updateSelectionState();
    });
    </script>
    <?php
}