<?php
/**
 * Template Name: Full Screen
 * Description: صفحه تمام صفحه - بدون هدر و فوتر
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('template-full-screen'); ?>>
<?php wp_body_open(); ?>
<?php while (have_posts()) : the_post(); ?>
    <div class="full-screen-content">
        <?php the_content(); ?>
    </div>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
