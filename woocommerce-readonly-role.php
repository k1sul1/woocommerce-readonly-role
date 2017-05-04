<?php
/**
 * Plugin Name:     WooCommerce Readonly Role
 * Description:     Adds a new user role that allows to view orders, but not edit them.
 * Author:          Christian Nikkanen
 * Author URI:      https://github.com/k1sul1
 * Text Domain:     woocommerce-readonly-role
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         WooCommerce_Readonly_Role
 */

class WooCommerce_Readonly_Role {

  public $capabilities;
  public $role;

  function __construct() {
    register_activation_hook(__FILE__, array(get_called_class(), 'pluginActivate'));

    $this->role = apply_filters('wcror_role_details', array(
      'slug' => 'woocommerce_readonly',
      'name' => 'WooCommerce readonly'
    ));

    $this->capabilities = apply_filters('wcror_role_capabilities', array(
      'read' => true, // must have to even access /wp-admin
      'edit_posts' => true, // without this the user is redirected to WooCommerce account page
      'publish_posts' => false,
      'read_shop_order' => true,
      'edit_shop_orders' => true, // show orders in menu
      'edit_others_shop_orders' => true, // must have
    ));

    // Capabilities are saved to the database.
    // That means that once they are there, these filters wouldn't take effect anymore without removing
    // the role from the database first, which is exactly what we're doing below.
    // Quote from codex: "Once you have nailed down your list of capabilities, there's no need to keep the
    // remove_role() code, though there is, in fact, no harm in doing so.".
    // https://codex.wordpress.org/Function_Reference/add_role

    add_action('init', array($this, 'removeRole'));
    add_action('init', array($this, 'addRole'));
    add_action('init', array($this, 'maybeGeneralCleanup'));

    add_action('current_screen', array($this, 'maybeCleanScreen'));
    add_action('pre_post_update', array($this, 'maybePreventTampering'));
  }

  public static function pluginActivate() {
    register_uninstall_hook(__FILE__, array(get_called_class(), 'pluginUninstall'));
  }

  public static function pluginUninstall() {
    $this->removeRole();
  }

  public function addRole() {
    add_role($this->role['slug'], $this->role['name'], $this->capabilities);
  }

  public function removeRole() {
    remove_role($this->role['slug']);
  }

  public function isReadOnlyUser() {
    if (!is_user_logged_in()) {
      return false;
    }

    return in_array($this->role['slug'], wp_get_current_user()->roles);
  }

  public function maybeCleanScreen() {
    if (!$this->isReadOnlyUser()) {
      return false;
    }

    $screen = get_current_screen();

    if (is_object($screen) && $screen->base === 'post' && $screen->id === 'shop_order') {
      $this->orderScreenCleanup();
    }
  }

  public function orderScreenCleanup() {
    add_action('admin_head', function() {
    ?>
      <script>
        jQuery(document).ready(function($) {
          var $edit_address_links = $('a.edit_address');
          var $poststuff_inputs = $('#poststuff input, #poststuff select, #poststuff textarea');
          var $ordernotes_add = $('#woocommerce-order-notes .add_note');
          var $postcustom = $('#postcustom');
          var $downloads = $('#woocommerce-order-downloads');
          var $orderactions = $('#woocommerce-order-actions');

          $edit_address_links.remove();
          $ordernotes_add.remove();
          $postcustom.remove();
          $downloads.remove();
          $orderactions.remove();

          $poststuff_inputs.attr('disabled', 'disabled');
      });
    </script>

    <style>
      a.edit_address,
      #woocommerce-order-notes .add_note,
      #postcustom,
      #woocommerce-order-downloads,
      #woocommerce-order-actions,
      #woocommerce-order-items .refund-items,
      .order_notes .delete_note{
        display: none;
      }


      #poststuff input,
      #poststuff select,
      #poststuff textarea {
        pointer-events: none;
        cursor: not-allowed;
        background: rgba(255,255,255,.5);
        border-color: rgba(222,222,222,.75);
        -webkit-box-shadow: inset 0 1px 2px rgba(0,0,0,.04);
        box-shadow: inset 0 1px 2px rgba(0,0,0,.04);
        color: rgba(51,51,51,.5);
      }
    </style>
    <?php
    });

    add_action('admin_print_scripts', function() {
      // Non-native select fields won't care about our "disabled" attribute.
      // Note: this is only applied on a single order view.
      wp_deregister_script('wc-enhanced-select');
    });

  }

  public function maybeGeneralCleanup() {
    if ($this->isReadOnlyUser()) {
      $this->generalCleanup();
    }
  }

  public function generalCleanup() {
    // Do some general clean up, hide extra menus and "add new" buttons.
    add_action('admin_head', function() {
    ?>
    <style>
      h1 .page-title-action,
      a[href*="post-new.php"],
      #menu-posts,
      #menu-comments,
      #menu-posts-shop_order ul,
      .column-order_actions .complete{
      display: none;
    }
    </style>
    <?php
    });
  }

  public function maybePreventTampering () {
    if ($this->isReadOnlyUser()) {
      $this->preventTampering();
    }
  }

  public function preventTampering() {
    wp_die(
      '<h1>' . __('Cheatin&#8217; uh?') . '</h1>' .
      '<p>' . __('You are not allowed to edit posts in this post type.') . '</p>',
      40
    );
  }
}

global $woocommerce_readonly_role;
$woocommerce_readonly_role = new WooCommerce_Readonly_Role();
