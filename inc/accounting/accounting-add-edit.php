<?php
/**
 * Enterprise Accounting & General Ledger Transaction Module (Institutional Grade)
 * File: inc/accounting/accounting-add.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_accounting_add_edit_view() {
    global $wpdb;
    $current_user     = wp_get_current_user();
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // --------------------------------------------------------------------------
    // 0. CAPABILITY & ROLE PERMISSION VALIDATION
    // --------------------------------------------------------------------------
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    $is_accountant = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_accountant = educore_has_access( array( 'accountant', 'accounts_officer', 'finance', 'staff' ) );
    }

    if ( ! $is_admin && ! $is_accountant ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $staff_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT designation, staff_type FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        // phpcs:enable

        if ( $staff_row ) {
            $desig = strtolower( (string) ( $staff_row->designation . ' ' . $staff_row->staff_type ) );
            if ( strpos( $desig, 'account' ) !== false || strpos( $desig, 'finance' ) !== false || strpos( $desig, 'cash' ) !== false ) {
                $is_accountant = true;
            }
        }
    }

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage accounting records.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = ( isset( $_GET['sub_mode'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub_mode'] ) ) ) || ( isset( $_GET['sub'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub'] ) ) );
    $entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $db_error = '';
    $entry    = null;

    if ( $is_edit && $entry_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_accounting}` WHERE id = %d LIMIT 1", $entry_id ) );
        // phpcs:enable
        if ( ! $entry ) {
            $is_edit = false;
        }
    }

    $back_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'accounting', 'sub' => 'list' ), admin_url( 'admin.php' ) );
    $current_yr = (int) current_time( 'Y' );

    // Retrieve configured Voucher Prefix from Settings (fallback to VCH-)
    $prefix_acc = get_option( 'educore_prefix_acc', 'VCH-' );
    if ( empty( $prefix_acc ) ) {
        $prefix_acc = 'VCH-';
    }
    $generated_voucher_no = $prefix_acc . gmdate( 'ym' ) . '-' . wp_rand( 10000, 99999 );

    // --------------------------------------------------------------------------
    // 2. FORM SUBMISSION ENGINE WITH STRICT SANITIZATION
    // --------------------------------------------------------------------------
    if ( isset( $_POST['educore_save_accounting_entry'] ) && isset( $_POST['ifs_educore_acct_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_acct_nonce'] ) ), 'save_acct_action' ) ) {
        
        $entry_type       = isset( $_POST['entry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_type'] ) ) : 'Income';
        $category_name    = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $department       = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : 'General Administration';
        $cost_center_code = isset( $_POST['cost_center_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cost_center_code'] ) ) : 'CC-ADMIN-01';
        $project_tag      = isset( $_POST['project_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['project_tag'] ) ) : 'General Operations';
        $fiscal_year      = isset( $_POST['fiscal_year'] ) ? sanitize_text_field( wp_unslash( $_POST['fiscal_year'] ) ) : $current_yr . '-' . ( $current_yr + 1 );
        $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $party_name       = isset( $_POST['party_name'] ) ? sanitize_text_field( wp_unslash( $_POST['party_name'] ) ) : '';
        $amount           = isset( $_POST['amount'] ) ? max( 0, floatval( wp_unslash( $_POST['amount'] ) ) ) : 0;
        $tax_vat_deducted = isset( $_POST['tax_vat_deducted'] ) ? max( 0, floatval( wp_unslash( $_POST['tax_vat_deducted'] ) ) ) : 0;
        $entry_date       = ! empty( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : current_time( 'Y-m-d' );
        $payment_method   = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash';
        $bank_account     = isset( $_POST['bank_account'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account'] ) ) : 'Cash in Hand';
        $approval_status  = isset( $_POST['approval_status'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_status'] ) ) : 'Approved';
        $voucher_no       = ! empty( $_POST['voucher_no'] ) ? sanitize_text_field( wp_unslash( $_POST['voucher_no'] ) ) : $generated_voucher_no;
        $note             = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        
        $attachment_url = $entry && ! empty( $entry->attachment_url ) ? $entry->attachment_url : '';

        // Secure file upload handler
        if ( ! empty( $_FILES['voucher_attachment']['name'] ) ) {
            $allowed_mimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
                'pdf'          => 'application/pdf',
            );

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $file_info = wp_check_filetype( sanitize_file_name( $_FILES['voucher_attachment']['name'] ), $allowed_mimes );

            if ( ! in_array( $file_info['type'], $allowed_mimes, true ) ) {
                wp_die( esc_html__( 'Security Error: Only PDF, JPG, PNG, and WEBP attachments are accepted.', 'ifsedu-school-management' ) );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $uploaded_file = wp_handle_upload( $_FILES['voucher_attachment'], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
            if ( ! isset( $uploaded_file['error'] ) && isset( $uploaded_file['url'] ) ) {
                $attachment_url = esc_url_raw( $uploaded_file['url'] );
            }
        }

        if ( ! empty( $title ) && $amount > 0 ) {
            $data = array(
                'voucher_no'       => $voucher_no,
                'entry_type'       => $entry_type,
                'category_name'    => $category_name,
                'department'       => $department,
                'cost_center_code' => $cost_center_code,
                'project_tag'      => $project_tag,
                'title'            => $title,
                'party_name'       => $party_name,
                'amount'           => $amount,
                'tax_vat_deducted' => $tax_vat_deducted,
                'payment_method'   => $payment_method,
                'bank_account'     => $bank_account,
                'approval_status'  => $approval_status,
                'entry_date'       => $entry_date,
                'fiscal_year'      => $fiscal_year,
                'note'             => $note,
                'attachment_url'   => $attachment_url,
                'created_by'       => get_current_user_id(),
            );

            $formats = array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
            );

            if ( $is_edit && $entry_id > 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $result      = $wpdb->update( $table_accounting, $data, array( 'id' => $entry_id ), $formats, array( '%d' ) );
                $status_flag = 'updated';
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $result      = $wpdb->insert( $table_accounting, $data, $formats );
                $status_flag = 'success';
            }

            if ( false !== $result ) {
                if ( function_exists( 'educore_log_activity' ) ) {
                    /* translators: 1: Voucher number, 2: Purpose/Title, 3: Amount */
                    educore_log_activity( sprintf( __( 'Processed Ledger Entry: (%1$s) %2$s - Amount: %3$.2f', 'ifsedu-school-management' ), $voucher_no, $title, $amount ) );
                }

                $redirect_url = add_query_arg(
                    array(
                        'page' => 'school_management_system',
                        'tab'  => 'accounting',
                        'sub'  => 'list',
                        'msg'  => $status_flag,
                    ),
                    admin_url( 'admin.php' )
                );

                if ( ! headers_sent() ) {
                    wp_safe_redirect( $redirect_url );
                    exit;
                } else {
                    echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                    exit;
                }
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : esc_html__( 'Database query execution failed.', 'ifsedu-school-management' );
            }
        } else {
            $db_error = esc_html__( 'Please enter a valid title and an amount greater than 0.00', 'ifsedu-school-management' );
        }
    }
    ?>

    <div class="ifs-educore-add-acct-container">

        <!-- Top Navigation Bar -->
        <div class="ifs-educore-header-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-back-btn">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Accounts List', 'ifsedu-school-management' ); ?>
            </a>
            <?php if ( $is_edit && $entry ) : ?>
                <span style="font-weight:800; font-size:13px; color:#00523c; background:#ecfdf5; padding:6px 12px; border-radius:20px; border:1px solid #a7f3d0;">
                    <?php
                        /* translators: %s: Ledger Record ID */
                        echo esc_html( sprintf( __( 'Editing Ledger Record #%d', 'ifsedu-school-management' ), absint( $entry->id ) ) );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $db_error ) ) : ?>
            <div class="ifs-educore-alert-error">
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                <span><?php echo esc_html( $db_error ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-bento-card">
            
            <div class="ifs-educore-card-title-group">
                <h4 class="ifs-educore-card-title">
                    <span class="dashicons dashicons-book-alt" style="color: #00523c;"></span>
                    <?php echo $is_edit ? esc_html__( 'Edit Professional Financial Ledger Entry', 'ifsedu-school-management' ) : esc_html__( 'New Institutional Voucher Entry', 'ifsedu-school-management' ); ?>
                </h4>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'Double-Entry Accounting Standard', 'ifsedu-school-management' ); ?></span>
            </div>

            <!-- Live Summary Preview Strip -->
            <div class="ifs-educore-live-summary-box">
                <div>
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block;"><?php esc_html_e( 'Voucher / Purpose Preview', 'ifsedu-school-management' ); ?></span>
                    <strong id="ifs_educore_preview_title" style="color:#0f172a; font-size:14px;"><?php esc_html_e( 'New General Ledger Voucher', 'ifsedu-school-management' ); ?></strong>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block;"><?php esc_html_e( 'Net Amount Preview', 'ifsedu-school-management' ); ?></span>
                    <strong id="ifs_educore_preview_amount" style="color:#059669; font-size:16px;">৳0.00</strong>
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_acct_action', 'ifs_educore_acct_nonce' ); ?>
                
                <div class="ifs-educore-form-grid">
                    
                    <!-- Flow Type -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Flow Type', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="entry_type" id="ifs_educore_entry_type" class="ifs-educore-select-field type-income-active" style="font-weight:700;" required>
                            <option value="Income" <?php selected( $entry ? $entry->entry_type : '', 'Income' ); ?>><?php esc_html_e( 'Income (+ Credit)', 'ifsedu-school-management' ); ?></option>
                            <option value="Expense" <?php selected( $entry ? $entry->entry_type : '', 'Expense' ); ?>><?php esc_html_e( 'Expense (- Debit)', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Gross Amount with Quick Chips -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Gross Amount (৳)', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="amount" id="ifs_educore_amount_input" class="ifs-educore-input-field" style="font-weight:800; font-size:15px;" placeholder="0.00" min="0.01" value="<?php echo $entry ? esc_attr( $entry->amount ) : ''; ?>" required>
                        <div class="ifs-educore-quick-chips">
                            <button type="button" class="ifs-educore-chip-btn" data-add="500">+500</button>
                            <button type="button" class="ifs-educore-chip-btn" data-add="1000">+1,000</button>
                            <button type="button" class="ifs-educore-chip-btn" data-add="5000">+5,000</button>
                            <button type="button" class="ifs-educore-chip-btn" data-add="10000">+10,000</button>
                            <button type="button" class="ifs-educore-chip-btn" id="ifs_educore_clear_amt" style="color:#dc2626;"><?php esc_html_e( 'Clear', 'ifsedu-school-management' ); ?></button>
                        </div>
                        <div id="ifs_educore_words_preview" class="ifs-educore-amount-words"></div>
                    </div>

                    <!-- Tax / VAT Deducted -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Tax / VAT Deducted (৳)', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" name="tax_vat_deducted" class="ifs-educore-input-field" placeholder="0.00" min="0" value="<?php echo ( $entry && isset( $entry->tax_vat_deducted ) ) ? esc_attr( $entry->tax_vat_deducted ) : '0.00'; ?>">
                    </div>

                    <!-- Title -->
                    <div class="ifs-educore-form-group span-2">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Transaction Purpose / Ledger Title', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" id="ifs_educore_title_input" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. Monthly Electricity Bill / Annual Sports Sponsorship', 'ifsedu-school-management' ); ?>" value="<?php echo $entry ? esc_attr( $entry->title ) : ''; ?>" required>
                    </div>

                    <!-- Category -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Ledger Category', 'ifsedu-school-management' ); ?></label>
                        <select name="category_name" id="ifs_educore_category_select" class="ifs-educore-select-field">
                            <!-- Populated dynamically via JS -->
                        </select>
                    </div>

                    <!-- Department / Cost Center -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Department / Cost Center', 'ifsedu-school-management' ); ?></label>
                        <select name="department" class="ifs-educore-select-field">
                            <?php 
                            $departments = array(
                                'General Administration',
                                'Science Faculty',
                                'Arts Faculty',
                                'Commerce Faculty',
                                'Library & Laboratory',
                                'Transport & Hostel',
                                'Sports & Cultural',
                            );
                            $saved_dept = ( $entry && isset( $entry->department ) ) ? $entry->department : 'General Administration';
                            foreach ( $departments as $dept ) {
                                echo '<option value="' . esc_attr( $dept ) . '" ' . selected( $saved_dept, $dept, false ) . '>' . esc_html( $dept ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Cost Center Code -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Cost Center Code', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="cost_center_code" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. CC-ADMIN-01', 'ifsedu-school-management' ); ?>" value="<?php echo ( $entry && isset( $entry->cost_center_code ) ) ? esc_attr( $entry->cost_center_code ) : 'CC-ADMIN-01'; ?>">
                    </div>

                    <!-- Project Tag / Fund Allocation -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Project / Fund Allocation', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="project_tag" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. Annual Sports 2026 / Development Fund', 'ifsedu-school-management' ); ?>" value="<?php echo ( $entry && isset( $entry->project_tag ) ) ? esc_attr( $entry->project_tag ) : 'General Operations'; ?>">
                    </div>

                    <!-- Fiscal Year -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Fiscal Year', 'ifsedu-school-management' ); ?></label>
                        <select name="fiscal_year" class="ifs-educore-select-field">
                            <?php 
                            $saved_fiscal = ( $entry && isset( $entry->fiscal_year ) ) ? $entry->fiscal_year : $current_yr . '-' . ( $current_yr + 1 );
                            for ( $y = $current_yr - 2; $y <= $current_yr + 2; $y++ ) {
                                $f_val = $y . '-' . ( $y + 1 );
                                echo '<option value="' . esc_attr( $f_val ) . '" ' . selected( $saved_fiscal, $f_val, false ) . '>' . esc_html( 'FY ' . $f_val ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Payer / Payee Identity -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" id="ifs_educore_party_label"><?php esc_html_e( 'Received From (Payer)', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="party_name" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. Education Board / Vendor Name', 'ifsedu-school-management' ); ?>" value="<?php echo ( $entry && isset( $entry->party_name ) ) ? esc_attr( $entry->party_name ) : ''; ?>">
                    </div>

                    <!-- Payment Method -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Payment Method', 'ifsedu-school-management' ); ?></label>
                        <select name="payment_method" class="ifs-educore-select-field">
                            <option value="Cash" <?php selected( $entry ? $entry->payment_method : '', 'Cash' ); ?>><?php esc_html_e( 'Cash In Hand', 'ifsedu-school-management' ); ?></option>
                            <option value="Bank Transfer" <?php selected( $entry ? $entry->payment_method : '', 'Bank Transfer' ); ?>><?php esc_html_e( 'Bank Wire / Cheque Deposit', 'ifsedu-school-management' ); ?></option>
                            <option value="bKash" <?php selected( $entry ? $entry->payment_method : '', 'bKash' ); ?>><?php esc_html_e( 'bKash Mobile Banking', 'ifsedu-school-management' ); ?></option>
                            <option value="Nagad" <?php selected( $entry ? $entry->payment_method : '', 'Nagad' ); ?>><?php esc_html_e( 'Nagad Mobile Banking', 'ifsedu-school-management' ); ?></option>
                            <option value="Cheque" <?php selected( $entry ? $entry->payment_method : '', 'Cheque' ); ?>><?php esc_html_e( 'Cheque Payment', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Bank Account / Fund Source -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Account / Fund Source', 'ifsedu-school-management' ); ?></label>
                        <select name="bank_account" class="ifs-educore-select-field">
                            <option value="Cash in Hand" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Cash in Hand' ); ?>><?php esc_html_e( 'Cash in Hand (Petty Cash)', 'ifsedu-school-management' ); ?></option>
                            <option value="Sonali Bank PLC" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Sonali Bank PLC' ); ?>><?php esc_html_e( 'Sonali Bank PLC (Main A/C)', 'ifsedu-school-management' ); ?></option>
                            <option value="Dutch-Bangla Bank PLC" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'Dutch-Bangla Bank PLC' ); ?>><?php esc_html_e( 'Dutch-Bangla Bank PLC', 'ifsedu-school-management' ); ?></option>
                            <option value="bKash Merchant Wallet" <?php selected( ( $entry && isset( $entry->bank_account ) ) ? $entry->bank_account : '', 'bKash Merchant Wallet' ); ?>><?php esc_html_e( 'bKash Merchant Wallet', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Voucher No -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Voucher / Ref No.', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="voucher_no" class="ifs-educore-input-field" value="<?php echo $entry ? esc_attr( $entry->voucher_no ) : esc_attr( $generated_voucher_no ); ?>">
                    </div>

                    <!-- Transaction Date -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Transaction Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="entry_date" class="ifs-educore-input-field" value="<?php echo $entry ? esc_attr( $entry->entry_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>

                    <!-- Approval Status -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Audit / Approval Status', 'ifsedu-school-management' ); ?></label>
                        <select name="approval_status" class="ifs-educore-select-field" style="font-weight:700;">
                            <option value="Approved" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Approved' ); ?>><?php esc_html_e( 'Approved & Verified', 'ifsedu-school-management' ); ?></option>
                            <option value="Pending Audit" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Pending Audit' ); ?>><?php esc_html_e( 'Pending Audit Review', 'ifsedu-school-management' ); ?></option>
                            <option value="Flagged" <?php selected( ( $entry && isset( $entry->approval_status ) ) ? $entry->approval_status : '', 'Flagged' ); ?>><?php esc_html_e( 'Flagged / Disputed', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Attachment Upload -->
                    <div class="ifs-educore-form-group full-width">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Attach Receipt / Physical Voucher Slip (PDF, PNG, JPG)', 'ifsedu-school-management' ); ?></label>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <input type="file" name="voucher_attachment" accept="image/jpeg,image/png,image/webp,application/pdf" class="ifs-educore-input-field" style="padding-top:8px;">
                            <?php if ( $entry && ! empty( $entry->attachment_url ) ) : ?>
                                <a href="<?php echo esc_url( $entry->attachment_url ); ?>" target="_blank" class="ifs-educore-back-btn" style="height:42px; white-space:nowrap;">
                                    <span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'View Current Slip', 'ifsedu-school-management' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="ifs-educore-form-group full-width">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Auditor Notes / Internal Memo', 'ifsedu-school-management' ); ?></label>
                        <textarea name="note" class="ifs-educore-textarea-field" placeholder="<?php esc_attr_e( 'Enter detailed transaction summary or internal memo...', 'ifsedu-school-management' ); ?>"><?php echo $entry ? esc_textarea( $entry->note ) : ''; ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="ifs-educore-form-group full-width">
                        <button type="submit" name="educore_save_accounting_entry" class="ifs-educore-btn-submit">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo $is_edit ? esc_html__( 'Update Institutional Ledger Record', 'ifsedu-school-management' ) : esc_html__( 'Record & Post to General Ledger', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>

    <!-- Smart Dynamic Categories & In-Words Engine Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var typeSelect     = document.getElementById('ifs_educore_entry_type');
        var categorySelect = document.getElementById('ifs_educore_category_select');
        var partyLabel     = document.getElementById('ifs_educore_party_label');
        var amountInput    = document.getElementById('ifs_educore_amount_input');
        var titleInput     = document.getElementById('ifs_educore_title_input');
        var wordsPreview   = document.getElementById('ifs_educore_words_preview');
        var previewTitle   = document.getElementById('ifs_educore_preview_title');
        var previewAmount  = document.getElementById('ifs_educore_preview_amount');
        var savedCategory  = "<?php echo $entry ? esc_js( $entry->category_name ) : ''; ?>";

        var incomeCategories = [
            'Tuition & Academic Fees',
            'Admission & Registration Fees',
            'Government Grants & Subsidies',
            'Donations & Corporate Sponsorships',
            'Facility & Auditorium Rental',
            'Exam Sheet & Form Sales',
            'Bank Interest & Investments',
            'Miscellaneous Income'
        ];

        var expenseCategories = [
            'Staff Salaries & Remunerations',
            'Utility Bills (Electricity, Gas, Water)',
            'Campus Maintenance & Repairs',
            'Office & Laboratory Stationery',
            'Property Lease & Campus Rent',
            'Student Welfare & Sports Events',
            'Software Licenses & IT Hosting',
            'Depreciation & Bank Charges',
            'Miscellaneous Expenses'
        ];

        function updateCategories() {
            if (!typeSelect || !categorySelect) return;
            var selectedType = typeSelect.value;
            categorySelect.innerHTML = '';

            var activeList = (selectedType === 'Income') ? incomeCategories : expenseCategories;

            activeList.forEach(function(cat) {
                var opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                if (savedCategory && savedCategory === cat) {
                    opt.selected = true;
                }
                categorySelect.appendChild(opt);
            });

            if (selectedType === 'Income') {
                typeSelect.classList.add('type-income-active');
                typeSelect.classList.remove('type-expense-active');
                if (partyLabel) partyLabel.textContent = "<?php echo esc_js( __( 'Received From (Payer)', 'ifsedu-school-management' ) ); ?>";
            } else {
                typeSelect.classList.add('type-expense-active');
                typeSelect.classList.remove('type-income-active');
                if (partyLabel) partyLabel.textContent = "<?php echo esc_js( __( 'Paid To / Vendor Name', 'ifsedu-school-management' ) ); ?>";
            }
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', updateCategories);
            updateCategories();
        }

        function updatePreview() {
            var tVal = titleInput ? titleInput.value.trim() : '';
            var aVal = amountInput ? parseFloat(amountInput.value) || 0 : 0;

            if (previewTitle) previewTitle.textContent = tVal !== '' ? tVal : '<?php echo esc_js( __( 'New General Ledger Voucher', 'ifsedu-school-management' ) ); ?>';
            if (previewAmount) previewAmount.textContent = '৳' + aVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        if (titleInput) titleInput.addEventListener('input', updatePreview);
        if (amountInput) amountInput.addEventListener('input', updatePreview);
        updatePreview();

        document.querySelectorAll('.ifs-educore-chip-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!amountInput) return;
                if (this.id === 'ifs_educore_clear_amt') {
                    amountInput.value = '';
                } else {
                    var addVal = parseFloat(this.getAttribute('data-add')) || 0;
                    var curVal = parseFloat(amountInput.value) || 0;
                    amountInput.value = (curVal + addVal).toFixed(2);
                }
                amountInput.dispatchEvent(new Event('input'));
                updatePreview();
            });
        });

        function inWords(num) {
            var a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
            var b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            if ((num = num.toString()).length > 9) return 'overflow';
            var n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
            if (!n) return ''; 
            var str = '';
            str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
            str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
            str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
            str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
            str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) : '';
            return str ? str.trim() + ' Taka Only' : '';
        }

        if (amountInput && wordsPreview) {
            amountInput.addEventListener('input', function() {
                var val = Math.floor(parseFloat(this.value) || 0);
                if (val > 0) {
                    wordsPreview.textContent = inWords(val);
                } else {
                    wordsPreview.textContent = '';
                }
            });
            if (amountInput.value) {
                var initialVal = Math.floor(parseFloat(amountInput.value) || 0);
                if (initialVal > 0) wordsPreview.textContent = inWords(initialVal);
            }
        }
    });
    </script>
    <?php
}