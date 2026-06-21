<?php
if (!defined('ABSPATH')) exit;

define('AI_THEME_VERSION', '1.0.0');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 60, 'width' => 200]);
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    add_theme_support('editor-styles');
    add_editor_style('assets/editor-style.css');
    register_nav_menus(['primary' => 'منوی اصلی', 'footer' => 'منوی فوتر']);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ai-theme', get_stylesheet_uri(), [], AI_THEME_VERSION);
    wp_enqueue_style('ai-theme-fonts', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap', [], null);
    wp_add_inline_style('ai-theme', 'body{font-family:"Vazirmatn",Tahoma,Arial,sans-serif}');
});

add_action('widgets_init', function () {
    register_sidebar(['name' => 'سایدبار', 'id' => 'sidebar-1', 'before_widget' => '<div class="widget">', 'after_widget' => '</div>', 'before_title' => '<h3 class="widget-title">', 'after_title' => '</h3>']);
});

add_filter('body_class', function ($classes) {
    if (is_page_template('templates/full-screen.php')) $classes[] = 'template-full-screen';
    if (is_page_template('templates/blank.php')) $classes[] = 'template-blank';
    if (is_page_template('templates/landing.php')) $classes[] = 'template-landing';
    return $classes;
});
