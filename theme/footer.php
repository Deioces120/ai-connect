</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-copyright">
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container' => false,
                    'menu_class' => 'footer-nav',
                    'fallback_cb' => false,
                ]);
                ?>
                <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></p>
            </div>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
