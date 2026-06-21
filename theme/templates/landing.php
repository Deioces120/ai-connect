<?php
/**
 * Template Name: Landing Page
 * Description: صفحه لندینگ - بدون هدر و فوتر، تمام عرض
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('template-landing'); ?>>
<?php wp_body_open(); ?>
<?php while (have_posts()) : the_post(); ?>
    <div class="landing-content">
        <?php the_content(); ?>
    </div>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
