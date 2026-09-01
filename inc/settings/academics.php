<?php
/**
 * Institutional Academic Defaults, Session & Interactive 12-Month Holiday Calendar with Reason Management
 * File: inc/settings/academics.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_academics_view( $base_url ) {
    $settings_updated = false;
    $req_method       = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $calendar_year = isset( $_GET['cal_year'] ) ? absint( wp_unslash( $_GET['cal_year'] ) ) : absint( get_option( 'educore_academic_year', gmdate( 'Y' ) ) );
    if ( $calendar_year < 2000 || $calendar_year > 2099 ) {
        $calendar_year = absint( gmdate( 'Y' ) );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( 'POST' === $req_method && isset( $_POST['educore_save_academic_settings'] ) ) {
        if ( ! isset( $_POST['educore_academic_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_academic_settings_nonce'] ) ), 'save_academic_settings_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
        }

        $academic_year        = isset( $_POST['academic_year'] ) ? sanitize_text_field( wp_unslash( $_POST['academic_year'] ) ) : gmdate( 'Y' );
        $default_shift        = isset( $_POST['default_shift'] ) ? sanitize_text_field( wp_unslash( $_POST['default_shift'] ) ) : 'Morning';
        $passing_grade_scale  = isset( $_POST['passing_grade_scale'] ) ? sanitize_text_field( wp_unslash( $_POST['passing_grade_scale'] ) ) : 'National Standard (GPA 5.0)';
        $attendance_threshold = isset( $_POST['attendance_threshold'] ) ? absint( wp_unslash( $_POST['attendance_threshold'] ) ) : 75;

        // Custom Holiday Dates with Reasons JSON
        $raw_off_dates_json = isset( $_POST['academic_off_dates_json'] ) ? wp_unslash( $_POST['academic_off_dates_json'] ) : '';
        $decoded_map        = json_decode( $raw_off_dates_json, true );
        $sanitized_map      = array();

        if ( is_array( $decoded_map ) ) {
            foreach ( $decoded_map as $date_key => $reason_val ) {
                $clean_date   = sanitize_text_field( $date_key );
                $clean_reason = sanitize_text_field( $reason_val );
                if ( ! empty( $clean_date ) ) {
                    $sanitized_map[ $clean_date ] = ! empty( $clean_reason ) ? $clean_reason : __( 'Holiday', 'ifsedu-school-management' );
                }
            }
        }

        update_option( 'educore_academic_year', $academic_year );
        update_option( 'educore_default_shift', $default_shift );
        update_option( 'educore_passing_grade_scale', $passing_grade_scale );
        update_option( 'educore_attendance_threshold', $attendance_threshold );
        update_option( 'educore_academic_off_dates_' . $calendar_year, $sanitized_map );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated academic setup criteria and 12-month holiday calendar with holiday reasons.', 'ifsedu-school-management' ) );
        }

        $settings_updated = true;
    }

    $academic_year        = get_option( 'educore_academic_year', gmdate( 'Y' ) );
    $default_shift        = get_option( 'educore_default_shift', 'Morning' );
    $passing_grade_scale  = get_option( 'educore_passing_grade_scale', 'National Standard (GPA 5.0)' );
    $attendance_threshold = get_option( 'educore_attendance_threshold', 75 );

    // Retrieve Saved Custom Off Days for this selected year
    $saved_off_days_map = get_option( 'educore_academic_off_dates_' . $calendar_year, null );

    // First time initialization: populate Friday & Saturday as default "Weekly Holiday"
    if ( is_null( $saved_off_days_map ) || ! is_array( $saved_off_days_map ) ) {
        $saved_off_days_map = array();
        for ( $m = 1; $m <= 12; $m++ ) {
            $days_in_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
            for ( $d = 1; $d <= $days_in_month; $d++ ) {
                $date_str    = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                $day_of_week = date( 'w', strtotime( $date_str ) ); // 5 = Friday, 6 = Saturday
                if ( 5 == $day_of_week || 6 == $day_of_week ) {
                    $saved_off_days_map[ $date_str ] = __( 'Weekly Holiday', 'ifsedu-school-management' );
                }
            }
        }
    }
    $saved_off_days_json = wp_json_encode( $saved_off_days_map );
    ?>

    <style>
        .ifs-cal-grid-12 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .ifs-cal-month-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }
        .ifs-cal-month-header {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 1.5px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ifs-cal-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-size: 10.5px;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 6px;
        }
        .ifs-cal-weekdays span.weekend-hdr {
            color: #dc2626;
        }
        .ifs-cal-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        .ifs-cal-day-cell {
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 5px;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .ifs-cal-day-cell:hover {
            border-color: #00523c;
            background: #f0fdf4;
            transform: scale(1.04);
            z-index: 2;
        }
        .ifs-cal-day-cell.is-off-day {
            background: #fee2e2 !important;
            color: #dc2626 !important;
            border-color: #fca5a5 !important;
        }
        .ifs-cal-day-cell.is-off-day::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 4px;
            height: 4px;
            background: #dc2626;
            border-radius: 50%;
        }
        .ifs-cal-day-cell.is-empty {
            visibility: hidden;
            cursor: default;
        }

        /* Holiday Reason Modal */
        .ifs-cal-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 999999;
            align-items: center;
            justify-content: center;
        }
        .ifs-cal-modal-backdrop.is-visible {
            display: flex;
        }
        .ifs-cal-modal-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        }
        .ifs-reason-preset-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            margin: 2px;
            display: inline-block;
        }
        .ifs-reason-preset-btn:hover {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
        }
    </style>

    <?php if ( $settings_updated ) : ?>
        <div class="ifs-educore-alert">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Academic setup and holiday calendar updated successfully.', 'ifsedu-school-management' ); ?>
        </div>
    <?php endif; ?>

    <div class="ifs-educore-settings-card">
        <form method="POST" action="">
            <?php wp_nonce_field( 'save_academic_settings_action', 'educore_academic_settings_nonce' ); ?>
            <input type="hidden" name="academic_off_dates_json" id="ifs_academic_off_dates_json" value="<?php echo esc_attr( $saved_off_days_json ); ?>">

            <!-- Global Academic Parameters -->
            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Current Academic Session / Year', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="academic_year" class="ifs-educore-input" value="<?php echo esc_attr( $academic_year ); ?>" required placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'e.g. 2026 or 2026-2027', 'ifsedu-school-management' ); ?></span>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Default Academic Shift', 'ifsedu-school-management' ); ?>
                    </label>
                    <select name="default_shift" class="ifs-educore-input" style="height:42px;">
                        <option value="Morning" <?php selected( $default_shift, 'Morning' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                        <option value="Day" <?php selected( $default_shift, 'Day' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                        <option value="Combined" <?php selected( $default_shift, 'Combined' ); ?>><?php esc_html_e( 'Combined / Single Shift', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Grading System Benchmark', 'ifsedu-school-management' ); ?>
                    </label>
                    <input type="text" name="passing_grade_scale" class="ifs-educore-input" value="<?php echo esc_attr( $passing_grade_scale ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Default grading scale applied across report cards.', 'ifsedu-school-management' ); ?></span>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Minimum Exam Eligibility Attendance (%)', 'ifsedu-school-management' ); ?>
                    </label>
                    <input type="number" min="1" max="100" name="attendance_threshold" class="ifs-educore-input" value="<?php echo esc_attr( $attendance_threshold ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Minimum attendance required for exam admit card issuance.', 'ifsedu-school-management' ); ?></span>
                </div>
            </div>

            <!-- 12-Month Academic Year Holiday & Off-Days Calendar -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                    <div>
                        <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">
                            <span class="dashicons dashicons-calendar-alt" style="color:#00523c; vertical-align:middle;"></span>
                            <?php printf( esc_html__( 'Academic Calendar & Off-Days: %s', 'ifsedu-school-management' ), esc_html( $calendar_year ) ); ?>
                        </h4>
                        <small style="color:#64748b; font-weight:600;">
                            <?php esc_html_e( 'Friday & Saturday default to Weekly Holiday. Click any date to edit holiday reason or toggle status.', 'ifsedu-school-management' ); ?>
                        </small>
                    </div>

                    <!-- Change Year Selector -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-size:12.5px; font-weight:700; color:#334155;"><?php esc_html_e( 'View Year:', 'ifsedu-school-management' ); ?></label>
                        <select onchange="window.location.href='<?php echo esc_url( add_query_arg( array( 'subtab' => 'academics' ), $base_url ) ); ?>&cal_year=' + this.value;" class="ifs-educore-input" style="height:36px; width:100px; padding:0 8px; font-weight:700;">
                            <?php for ( $y = (int) gmdate( 'Y' ) - 2; $y <= (int) gmdate( 'Y' ) + 4; $y++ ) : ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $calendar_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="background:#ecfdf5; color:#047857; font-weight:800; font-size:12px; padding:6px 12px; border-radius:6px; border:1px solid #a7f3d0;" id="ifs_total_off_days_pill">
                            <?php echo count( (array) $saved_off_days_map ); ?> <?php esc_html_e( 'Total Off Days', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                </div>

                <!-- 12 Months Grid Display -->
                <div class="ifs-cal-grid-12">
                    <?php
                    $month_names = array(
                        1  => __( 'January', 'ifsedu-school-management' ),
                        2  => __( 'February', 'ifsedu-school-management' ),
                        3  => __( 'March', 'ifsedu-school-management' ),
                        4  => __( 'April', 'ifsedu-school-management' ),
                        5  => __( 'May', 'ifsedu-school-management' ),
                        6  => __( 'June', 'ifsedu-school-management' ),
                        7  => __( 'July', 'ifsedu-school-management' ),
                        8  => __( 'August', 'ifsedu-school-management' ),
                        9  => __( 'September', 'ifsedu-school-management' ),
                        10 => __( 'October', 'ifsedu-school-management' ),
                        11 => __( 'November', 'ifsedu-school-management' ),
                        12 => __( 'December', 'ifsedu-school-management' ),
                    );

                    for ( $m = 1; $m <= 12; $m++ ) :
                        $first_day_of_month = mktime( 0, 0, 0, $m, 1, $calendar_year );
                        $days_in_this_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
                        $start_weekday      = date( 'w', $first_day_of_month ); // 0 (Sun) to 6 (Sat)
                    ?>
                        <div class="ifs-cal-month-card">
                            <div class="ifs-cal-month-header">
                                <span><?php echo esc_html( $month_names[ $m ] ); ?></span>
                                <small style="color:#64748b; font-size:11px;"><?php echo esc_html( $calendar_year ); ?></small>
                            </div>

                            <div class="ifs-cal-weekdays">
                                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span>
                                <span class="weekend-hdr">Fr</span>
                                <span class="weekend-hdr">Sa</span>
                            </div>

                            <div class="ifs-cal-days-grid">
                                <?php
                                for ( $blank = 0; $blank < $start_weekday; $blank++ ) {
                                    echo '<div class="ifs-cal-day-cell is-empty"></div>';
                                }

                                for ( $d = 1; $d <= $days_in_this_month; $d++ ) {
                                    $current_date_str = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                                    $is_off           = isset( $saved_off_days_map[ $current_date_str ] );
                                    $reason           = $is_off ? $saved_off_days_map[ $current_date_str ] : '';
                                    $tooltip          = $current_date_str . ( $is_off ? ' (' . $reason . ')' : '' );
                                ?>
                                    <div class="ifs-cal-day-cell <?php echo $is_off ? 'is-off-day' : ''; ?>" 
                                         data-date="<?php echo esc_attr( $current_date_str ); ?>" 
                                         data-reason="<?php echo esc_attr( $reason ); ?>"
                                         title="<?php echo esc_attr( $tooltip ); ?>">
                                        <?php echo esc_html( $d ); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <button type="submit" name="educore_save_academic_settings" class="ifs-educore-btn-submit">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Academic Settings & Calendar', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal for Adding/Editing Holiday Reason -->
    <div class="ifs-cal-modal-backdrop" id="ifs_holiday_modal">
        <div class="ifs-cal-modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:10px; margin-bottom:14px;">
                <h4 style="margin:0; font-size:15px; font-weight:800; color:#0f172a;">
                    <span class="dashicons dashicons-edit" style="color:#00523c; vertical-align:middle;"></span>
                    <?php esc_html_e( 'Set Academic Day Status', 'ifsedu-school-management' ); ?>
                </h4>
                <button type="button" id="ifs_close_holiday_modal" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
            </div>

            <div style="margin-bottom:12px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="font-size:13.5px; font-weight:800; color:#0f172a;" id="modal_display_date"></div>
                <div style="font-size:12px; color:#64748b; font-weight:600;" id="modal_display_weekday"></div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="ifs-educore-label"><?php esc_html_e( 'Day Type', 'ifsedu-school-management' ); ?></label>
                <select id="modal_day_status" class="ifs-educore-input" style="height:38px;">
                    <option value="open"><?php esc_html_e( 'Open Academic Day', 'ifsedu-school-management' ); ?></option>
                    <option value="off"><?php esc_html_e( 'Off / Holiday (Blocked Day)', 'ifsedu-school-management' ); ?></option>
                </select>
            </div>

            <div id="modal_reason_wrap" style="margin-bottom:14px;">
                <label class="ifs-educore-label"><?php esc_html_e( 'Holiday / Block Reason', 'ifsedu-school-management' ); ?></label>
                <input type="text" id="modal_holiday_reason" class="ifs-educore-input" placeholder="e.g. Weekly Holiday / Eid Vacation" style="margin-bottom:6px;">
                
                <!-- Quick Preset Reason Chips -->
                <div>
                    <span style="font-size:11px; color:#64748b; font-weight:700; display:block; margin-bottom:4px;"><?php esc_html_e( 'Quick Presets:', 'ifsedu-school-management' ); ?></span>
                    <button type="button" class="ifs-reason-preset-btn">Weekly Holiday</button>
                    <button type="button" class="ifs-reason-preset-btn">National Holiday</button>
                    <button type="button" class="ifs-reason-preset-btn">Eid Vacation</button>
                    <button type="button" class="ifs-reason-preset-btn">Puja Vacation</button>
                    <button type="button" class="ifs-reason-preset-btn">Summer Vacation</button>
                    <button type="button" class="ifs-reason-preset-btn">Winter Vacation</button>
                    <button type="button" class="ifs-reason-preset-btn">Exam Preparation</button>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" id="ifs_cancel_holiday_btn" class="ifs-educore-square-btn" style="background:#f1f5f9; color:#475569; padding:8px 14px; font-weight:700; border-radius:6px; border:none; cursor:pointer;">
                    <?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?>
                </button>
                <button type="button" id="ifs_save_holiday_btn" class="ifs-educore-btn-submit" style="padding:8px 16px; font-size:13px;">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Apply Status', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Click, Modal & JSON Map Engine Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var hiddenInput = document.getElementById('ifs_academic_off_dates_json');
        var counterPill = document.getElementById('ifs_total_off_days_pill');
        var dayCells    = document.querySelectorAll('.ifs-cal-day-cell:not(.is-empty)');

        var modal           = document.getElementById('ifs_holiday_modal');
        var closeModalBtn   = document.getElementById('ifs_close_holiday_modal');
        var cancelModalBtn  = document.getElementById('ifs_cancel_holiday_btn');
        var saveModalBtn    = document.getElementById('ifs_save_holiday_btn');

        var modalDateLabel    = document.getElementById('modal_display_date');
        var modalWeekdayLabel = document.getElementById('modal_display_weekday');
        var modalStatusSelect = document.getElementById('modal_day_status');
        var modalReasonWrap   = document.getElementById('modal_reason_wrap');
        var modalReasonInput  = document.getElementById('modal_holiday_reason');

        var activeTargetCell = null;
        var offDaysDataMap   = {};

        try {
            offDaysDataMap = JSON.parse(hiddenInput.value || '{}');
        } catch (e) {
            offDaysDataMap = {};
        }

        function updateStorageState() {
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(offDaysDataMap);
            }
            if (counterPill) {
                counterPill.textContent = Object.keys(offDaysDataMap).length + ' <?php echo esc_js( __( 'Total Off Days', 'ifsedu-school-management' ) ); ?>';
            }
        }

        function hideModal() {
            if (modal) modal.classList.remove('is-visible');
            activeTargetCell = null;
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

        modalStatusSelect.addEventListener('change', function() {
            if (this.value === 'off') {
                modalReasonWrap.style.display = 'block';
                if (!modalReasonInput.value.trim()) {
                    modalReasonInput.value = '<?php echo esc_js( __( 'Holiday', 'ifsedu-school-management' ) ); ?>';
                }
            } else {
                modalReasonWrap.style.display = 'none';
            }
        });

        document.querySelectorAll('.ifs-reason-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                modalReasonInput.value = this.textContent.trim();
            });
        });

        dayCells.forEach(function(cell) {
            cell.addEventListener('click', function() {
                activeTargetCell = this;
                var dateStr = this.getAttribute('data-date');
                var isOff   = this.classList.contains('is-off-day');
                var reason  = offDaysDataMap[dateStr] || this.getAttribute('data-reason') || '';

                var dObj = new Date(dateStr);
                var weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var dayName  = weekdays[dObj.getDay()] || '';

                modalDateLabel.textContent    = dateStr;
                modalWeekdayLabel.textContent = dayName;

                if (isOff) {
                    modalStatusSelect.value = 'off';
                    modalReasonWrap.style.display = 'block';
                    modalReasonInput.value = reason ? reason : (dayName === 'Friday' || dayName === 'Saturday' ? 'Weekly Holiday' : 'Holiday');
                } else {
                    modalStatusSelect.value = 'open';
                    modalReasonWrap.style.display = 'none';
                    modalReasonInput.value = (dayName === 'Friday' || dayName === 'Saturday' ? 'Weekly Holiday' : 'Holiday');
                }

                modal.classList.add('is-visible');
            });
        });

        saveModalBtn.addEventListener('click', function() {
            if (!activeTargetCell) return;
            var dateStr = activeTargetCell.getAttribute('data-date');
            var status  = modalStatusSelect.value;
            var reason  = modalReasonInput.value.trim();

            if (status === 'off') {
                activeTargetCell.classList.add('is-off-day');
                offDaysDataMap[dateStr] = reason ? reason : 'Holiday';
                activeTargetCell.setAttribute('data-reason', offDaysDataMap[dateStr]);
                activeTargetCell.setAttribute('title', dateStr + ' (' + offDaysDataMap[dateStr] + ')');
            } else {
                activeTargetCell.classList.remove('is-off-day');
                delete offDaysDataMap[dateStr];
                activeTargetCell.removeAttribute('data-reason');
                activeTargetCell.setAttribute('title', dateStr);
            }

            updateStorageState();
            hideModal();
        });
    });
    </script>
    <?php
}