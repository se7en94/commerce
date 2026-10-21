<?php
/**
 * header.php shim for the block theme.
 * Only used by custom render paths (e.g. language front-page renderer in
 * mu-plugins/velore-i18n.php); the normal WP block-template loader renders
 * parts/header.html directly. This shim keeps get_header() functional and
 * quiet for block themes without a header.php.
 *
 * @package twentytwentyfive-luxury
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php block_template_part( 'header' ); ?>
