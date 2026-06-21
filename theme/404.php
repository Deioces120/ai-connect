<?php get_header(); ?>
<div class="container">
    <div class="error-404">
        <h1>404</h1>
        <p>صفحه مورد نظر یافت نشد.</p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="btn">بازگشت به صفحه اصلی</a>
    </div>
</div>
<?php get_footer(); ?>
