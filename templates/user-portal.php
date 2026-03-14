<?php
/**
 * Template: User portal shortcode wrapper.
 *
 * This is a thin wrapper; actual markup lives in
 * public/partials/user-portal.php which is included by the shortcode handler.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This file is intentionally minimal.  The shortcode handler in
 * class-public.php directly includes public/partials/user-portal.php,
 * so this template only needs to exist for future override capability.
 *
 * Theme authors can copy this file to:
 *   {theme}/processflow-manager/templates/user-portal.php
 * and customise as needed.
 */
?>
<?php // Content is rendered by the shortcode handler. ?>
