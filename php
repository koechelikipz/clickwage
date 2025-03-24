<?php
/**
 * Plugin Name: Referral Earnings System
 * Description: A simple referral system with activation fees and withdrawals.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Function: referral_system_install
 * Purpose: Creates necessary database tables for users and withdrawals.
 * This function is triggered on plugin activation.
 */
function referral_system_install() {
    global $wpdb;
    $table_users = $wpdb->prefix . 'referral_users';
    $table_withdrawals = $wpdb->prefix . 'referral_withdrawals';

    $charset_collate = $wpdb->get_charset_collate();

    // Create users table to store user details, referral data, and activation status
    $sql = "CREATE TABLE IF NOT EXISTS $table_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        referred_by INT DEFAULT NULL,
        balance INT DEFAULT 0,
        activated BOOLEAN DEFAULT 0
    ) $charset_collate;";

    // Create withdrawals table to manage withdrawal requests
    $sql .= "CREATE TABLE IF NOT EXISTS $table_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount INT NOT NULL,
        status VARCHAR(20) DEFAULT 'Pending'
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'referral_system_install');

/**
 * Function: referral_dashboard
 * Purpose: Displays user dashboard with balance, referrals, and withdrawal option.
 * This function is used as a shortcode: [referral_dashboard].
 */
function referral_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url() . '">login</a> to access your dashboard.</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_users = $wpdb->prefix . 'referral_users';
    $table_withdrawals = $wpdb->prefix . 'referral_withdrawals';
    
    // Fetch user data
    $user = $wpdb->get_row("SELECT * FROM $table_users WHERE id = $user_id");
    if (!$user) {
        return '<p>You need to activate your account with KES 100 to start earning.</p>';
    }
    
    // Count referred users
    $referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE referred_by = $user_id");
    
    // Calculate balance
    $balance = $user->balance + ($referrals * 200) + ($user->activated ? 500 : 0);
    
    // Display user details
    $output = "<h3>Welcome, {$user->username}</h3>";
    $output .= "<p>Referral Code: {$user->id}</p>";
    $output .= "<p>Referred Users: $referrals</p>";
    $output .= "<p>Balance: KES $balance</p>";
    
    // Withdrawal request button (minimum balance required: 1500 KES)
    if ($balance >= 1500) {
        $output .= '<form method="POST"><button type="submit" name="withdraw">Withdraw KES 1500</button></form>';
    }
    
    // Handle withdrawal request
    if (isset($_POST['withdraw']) && $balance >= 1500) {
        $wpdb->insert($table_withdrawals, [
            'user_id' => $user_id,
            'amount' => 1500,
            'status' => 'Pending'
        ]);
        $output .= '<p>Withdrawal request submitted!</p>';
    }
    
    return $output;
}
add_shortcode('referral_dashboard', 'referral_dashboard');

/**
 * Function: referral_admin_menu
 * Purpose: Adds an admin panel in WordPress to manage referrals and withdrawals.
 */
function referral_admin_menu() {
    add_menu_page('Referral System', 'Referral System', 'manage_options', 'referral-system', 'referral_admin_page');
}
add_action('admin_menu', 'referral_admin_menu');

/**
 * Function: referral_admin_page
 * Purpose: Displays pending withdrawal requests in the WordPress admin panel.
 */
function referral_admin_page() {
    global $wpdb;
    $table_withdrawals = $wpdb->prefix . 'referral_withdrawals';
    $withdrawals = $wpdb->get_results("SELECT * FROM $table_withdrawals");
    
    echo '<h2>Pending Withdrawals</h2><ul>';
    foreach ($withdrawals as $withdraw) {
        echo "<li>User ID: {$withdraw->user_id} - Amount: KES {$withdraw->amount} - Status: {$withdraw->status}</li>";
    }
    echo '</ul>';
}
