<?php
/**
 * Institutional Settings & Role-Based Permissions Management Module
 * File: inc/settings/settings-tab.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_settings_tab() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access institutional settings.', 'ifsedu-school-management' ) );
    }

    // Enqueue Media Assets for Native WP Uploader
    wp_enqueue_media();

    // Available System Modules & Configurable Roles
    $system_modules = array(
        'dashboard'  => __( 'Dashboard & Overview', 'ifsedu-school-management' ),
        'students'   => __( 'Student Admission & Profiles', 'ifsedu-school-management' ),
        'academics'  => __( 'Academic Setup (Classes, Subjects, Routine)', 'ifsedu-school-management' ),
        'attendance' => __( 'Attendance Engine (Student & Staff)', 'ifsedu-school-management' ),
        'exams'      => __( 'Exams, Grading & Marksheet', 'ifsedu-school-management' ),
        'fees'       => __( 'Fee Collection & Invoicing Ledger', 'ifsedu-school-management' ),
        'accounts'   => __( 'Accounts & Financial Ledger', 'ifsedu-school-management' ),
        'staff'      => __( 'Staff / Teacher Directory', 'ifsedu-school-management' ),
        'notices'    => __( 'Notices & Academic Events', 'ifsedu-school-management' ),
        'reports'    => __( 'Analytics & System Reports', 'ifsedu-school-management' ),
    );

    $configurable_roles = array(
        'teacher'    => __( 'Teacher / Instructor', 'ifsedu-school-management' ),
        'accountant' => __( 'Accountant / Cashier', 'ifsedu-school-management' ),
        'staff'      => __( 'General Staff', 'ifsedu-school-management' ),
    );

    $settings_updated = false;

    $allowed_sub_tabs = array( 'general', 'prefixes', 'permissions' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'general';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $active_subtab = in_array( $raw_subtab, $allowed_sub_tabs, true ) ? $raw_subtab : 'general';

    // --------------------------------------------------------------------------
    // Form Submission Handlers
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method ) {
        // 1. General Settings Save
        if ( isset( $_POST['educore_save_general_settings'] ) ) {
            if ( ! isset( $_POST['educore_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_settings_nonce'] ) ), 'save_settings_action' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
            }

            $school_name    = isset( $_POST['school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['school_name'] ) ) : '';
            $school_tagline = isset( $_POST['school_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['school_tagline'] ) ) : '';
            $school_logo    = isset( $_POST['school_logo'] ) ? esc_url_raw( wp_unslash( $_POST['school_logo'] ) ) : '';
            $principal_sig  = isset( $_POST['principal_sig'] ) ? esc_url_raw( wp_unslash( $_POST['principal_sig'] ) ) : '';

            update_option( 'educore_school_name', $school_name );
            update_option( 'educore_school_tagline', $school_tagline );
            update_option( 'educore_school_logo', $school_logo );
            update_option( 'educore_principal_sig', $principal_sig );

            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity( __( 'Updated general institutional settings profile.', 'ifsedu-school-management' ) );
            }

            $settings_updated = true;
        }

        // 2. ID Prefix & Voucher Prefix Settings Save
        if ( isset( $_POST['educore_save_prefixes'] ) ) {
            if ( ! isset( $_POST['educore_prefixes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_prefixes_nonce'] ) ), 'save_prefixes_action' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
            }

            $prefix_student = isset( $_POST['prefix_student'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_student'] ) ) : 'STU-';
            $prefix_teacher = isset( $_POST['prefix_teacher'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_teacher'] ) ) : 'TCH-';
            $prefix_staff   = isset( $_POST['prefix_staff'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_staff'] ) ) : 'STF-';
            $prefix_officer = isset( $_POST['prefix_officer'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_officer'] ) ) : 'OFC-';
            $prefix_fee     = isset( $_POST['prefix_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_fee'] ) ) : 'INV-';
            $prefix_acc     = isset( $_POST['prefix_acc'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix_acc'] ) ) : 'VCH-';

            update_option( 'educore_prefix_student', $prefix_student );
            update_option( 'educore_prefix_teacher', $prefix_teacher );
            update_option( 'educore_prefix_staff', $prefix_staff );
            update_option( 'educore_prefix_officer', $prefix_officer );
            update_option( 'educore_prefix_fee', $prefix_fee );
            update_option( 'educore_prefix_acc', $prefix_acc );

            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity( __( 'Updated system ID and voucher prefix codes.', 'ifsedu-school-management' ) );
            }

            $settings_updated = true;
        }

        // 3. Role Permissions Save
        if ( isset( $_POST['educore_save_permissions'] ) ) {
            if ( ! isset( $_POST['educore_permissions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_permissions_nonce'] ) ), 'save_permissions_action' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_permissions = ( isset( $_POST['educore_role_permissions'] ) && is_array( $_POST['educore_role_permissions'] ) ) ? wp_unslash( $_POST['educore_role_permissions'] ) : array();
            
            $sanitized_permissions = array();

            foreach ( $configurable_roles as $role_key => $role_name ) {
                $sanitized_permissions[ $role_key ] = array();
                if ( isset( $raw_permissions[ $role_key ] ) && is_array( $raw_permissions[ $role_key ] ) ) {
                    $clean_role_modules = array_map( 'sanitize_key', $raw_permissions[ $role_key ] );
                    foreach ( array_keys( $system_modules ) as $mod_key ) {
                        if ( in_array( $mod_key, $clean_role_modules, true ) ) {
                            $sanitized_permissions[ $role_key ][] = $mod_key;
                        }
                    }
                }
            }

            update_option( 'educore_role_permissions', $sanitized_permissions );
            
            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity( __( 'Updated role-based module access control matrix.', 'ifsedu-school-management' ) );
            }

            $settings_updated = true;
        }
    }

    // Retrieve settings values
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    $prefix_student = get_option( 'educore_prefix_student', 'STU-' );
    $prefix_teacher = get_option( 'educore_prefix_teacher', 'TCH-' );
    $prefix_staff   = get_option( 'educore_prefix_staff', 'STF-' );
    $prefix_officer = get_option( 'educore_prefix_officer', 'OFC-' );
    $prefix_fee     = get_option( 'educore_prefix_fee', 'INV-' );
    $prefix_acc     = get_option( 'educore_prefix_acc', 'VCH-' );

    $saved_permissions = get_option( 'educore_role_permissions', array() );
    $saved_permissions = is_array( $saved_permissions ) ? $saved_permissions : array();

    $base_admin_url = admin_url( 'admin.php' );
    $base_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'settings' ), $base_admin_url );
    ?>

    <div class="ifs-educore-settings-root">

        <!-- Navigation Tabs -->
        <div class="ifs-educore-subnav">
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'general', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'general' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'General Settings', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'prefixes', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'prefixes' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-id-alt"></span>
                <?php esc_html_e( 'ID Prefix Settings', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'permissions', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'permissions' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-lock"></span>
                <?php esc_html_e( 'User Roles & Permissions', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( $settings_updated ) : ?>
            <div class="ifs-educore-alert">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Settings updated successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <?php if ( 'prefixes' === $active_subtab ) : ?>
            <!-- TAB 2: ID PREFIX SETTINGS -->
            <div class="ifs-educore-settings-card">

                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_prefixes_action', 'educore_prefixes_nonce' ); ?>

                    <!-- Row 1: Student & Teacher -->
                    <div class="ifs-educore-grid-row">
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Student ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_student" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_student ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STU-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STU-2026-0001', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Teacher / Instructor ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_teacher" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_teacher ); ?>" required placeholder="<?php esc_attr_e( 'e.g. TCH-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: TCH-0101', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <!-- Row 2: General Staff & Officer -->
                    <div class="ifs-educore-grid-row">
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'General Staff ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_staff" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_staff ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STF-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STF-0012', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Officer / Admin Staff Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_officer" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_officer ); ?>" required placeholder="<?php esc_attr_e( 'e.g. OFC-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: OFC-0005', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <!-- Row 3: Fee Invoices & Accounting Vouchers -->
                    <div class="ifs-educore-grid-row">
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Fee Invoice Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_fee" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_fee ); ?>" required placeholder="<?php esc_attr_e( 'e.g. INV-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: INV-2026-0045', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Accounting Voucher Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="prefix_acc" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_acc ); ?>" required placeholder="<?php esc_attr_e( 'e.g. VCH-', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: VCH-2026-0102', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                        <button type="submit" name="educore_save_prefixes" class="ifs-educore-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save ID Prefixes', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ( 'permissions' === $active_subtab ) : ?>
            <!-- TAB 3: ROLE-BASED MODULE PERMISSIONS -->
            <div class="ifs-educore-settings-card">

                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_permissions_action', 'educore_permissions_nonce' ); ?>

                    <div style="overflow-x:auto;">
                        <table class="ifs-educore-perm-table">
                            <thead>
                                <tr>
                                    <th class="module-col"><?php esc_html_e( 'System Module / Feature', 'ifsedu-school-management' ); ?></th>
                                    <?php foreach ( $configurable_roles as $role_key => $role_name ) : ?>
                                        <th><?php echo esc_html( $role_name ); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $system_modules as $mod_key => $mod_name ) : ?>
                                    <tr>
                                        <td class="module-col">
                                            <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px; color:#00523c; vertical-align:middle;"></span>
                                            <?php echo esc_html( $mod_name ); ?>
                                        </td>
                                        <?php foreach ( $configurable_roles as $role_key => $role_name ) : 
                                            $is_allowed = isset( $saved_permissions[ $role_key ] ) && is_array( $saved_permissions[ $role_key ] ) && in_array( $mod_key, $saved_permissions[ $role_key ], true );
                                        ?>
                                            <td>
                                                <label class="ifs-educore-switch">
                                                    <input type="checkbox" name="educore_role_permissions[<?php echo esc_attr( $role_key ); ?>][]" value="<?php echo esc_attr( $mod_key ); ?>" <?php checked( $is_allowed, true ); ?>>
                                                    <span class="ifs-educore-switch-slider"></span>
                                                </label>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                        <button type="submit" name="educore_save_permissions" class="ifs-educore-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Access Matrix', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>

        <?php else : ?>
            <!-- TAB 1: GENERAL INSTITUTIONAL IDENTITY -->
            <div class="ifs-educore-settings-card">

                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>

                    <!-- Row 1: Name & Motto -->
                    <div class="ifs-educore-grid-row">
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Official Institution Name', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="school_name" class="ifs-educore-input" value="<?php echo esc_attr( $school_name ); ?>" required>
                        </div>

                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label">
                                <?php esc_html_e( 'Motto / Tagline', 'ifsedu-school-management' ); ?>
                            </label>
                            <input type="text" name="school_tagline" class="ifs-educore-input" value="<?php echo esc_attr( $school_tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Education for Enlightenment', 'ifsedu-school-management' ); ?>">
                        </div>
                    </div>

                    <!-- Row 2: Institutional Logo & Principal Signature -->
                    <div class="ifs-educore-grid-row">
                        <!-- Logo Upload -->
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label"><?php esc_html_e( 'Institutional Logo', 'ifsedu-school-management' ); ?></label>
                            <div class="ifs-educore-uploader-card">
                                <div class="ifs-educore-preview-box" id="ifs_logo_preview">
                                    <?php if ( ! empty( $school_logo ) ) : ?>
                                        <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'School Logo', 'ifsedu-school-management' ); ?>">
                                    <?php else : ?>
                                        <span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="school_logo" id="ifs_school_logo_input" value="<?php echo esc_url( $school_logo ); ?>">
                                <div class="ifs-educore-uploader-actions">
                                    <button type="button" class="ifs-educore-btn-upload" id="ifs_upload_logo_btn">
                                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select Logo', 'ifsedu-school-management' ); ?>
                                    </button>
                                    <?php 
                                    $logo_remove_style = empty( $school_logo ) ? 'display:none;' : '';
                                    ?>
                                    <button type="button" class="ifs-educore-btn-remove" id="ifs_remove_logo_btn" style="<?php echo esc_attr( $logo_remove_style ); ?>">
                                        <?php esc_html_e( 'Remove', 'ifsedu-school-management' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Principal Signature Upload -->
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-label"><?php esc_html_e( 'Principal / Authority Signature', 'ifsedu-school-management' ); ?></label>
                            <div class="ifs-educore-uploader-card">
                                <div class="ifs-educore-preview-box" id="ifs_sig_preview">
                                    <?php if ( ! empty( $principal_sig ) ) : ?>
                                        <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Principal Signature', 'ifsedu-school-management' ); ?>">
                                    <?php else : ?>
                                        <span class="dashicons dashicons-edit" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="principal_sig" id="ifs_principal_sig_input" value="<?php echo esc_url( $principal_sig ); ?>">
                                <div class="ifs-educore-uploader-actions">
                                    <button type="button" class="ifs-educore-btn-upload" id="ifs_upload_sig_btn">
                                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select Signature', 'ifsedu-school-management' ); ?>
                                    </button>
                                    <?php 
                                    $sig_remove_style = empty( $principal_sig ) ? 'display:none;' : '';
                                    ?>
                                    <button type="button" class="ifs-educore-btn-remove" id="ifs_remove_sig_btn" style="<?php echo esc_attr( $sig_remove_style ); ?>">
                                        <?php esc_html_e( 'Remove', 'ifsedu-school-management' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                        <button type="submit" name="educore_save_general_settings" class="ifs-educore-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save General Settings', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <!-- Media Uploader Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function setupMediaUploader(btnSelector, inputSelector, previewSelector, removeBtnSelector, modalTitle) {
            var mediaFrame;
            $(btnSelector).on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                mediaFrame = wp.media({
                    title: modalTitle,
                    button: { text: '<?php echo esc_js( __( 'Use Selected Image', 'ifsedu-school-management' ) ); ?>' },
                    multiple: false
                });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $(inputSelector).val(attachment.url);
                    $(previewSelector).html('<img src="' + attachment.url + '" alt="Preview">');
                    $(removeBtnSelector).show();
                });
                mediaFrame.open();
            });

            $(removeBtnSelector).on('click', function(e) {
                e.preventDefault();
                $(inputSelector).val('');
                $(previewSelector).html('<span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>');
                $(this).hide();
            });
        }

        setupMediaUploader('#ifs_upload_logo_btn', '#ifs_school_logo_input', '#ifs_logo_preview', '#ifs_remove_logo_btn', '<?php echo esc_js( __( 'Select Institutional Logo', 'ifsedu-school-management' ) ); ?>');
        setupMediaUploader('#ifs_upload_sig_btn', '#ifs_principal_sig_input', '#ifs_sig_preview', '#ifs_remove_sig_btn', '<?php echo esc_js( __( 'Select Principal Signature Image', 'ifsedu-school-management' ) ); ?>');
    });
    </script>
    <?php
}