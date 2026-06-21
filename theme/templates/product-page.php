<?php
/**
 * Template Name: Product Page
 * Description: صفحه محصول حرفه‌ای مشابه هرمس پارت
 */
get_header('shop');
?>

<style>
/* Product Page Styles */
.aic-product-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.aic-product-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
@media (max-width: 768px) { .aic-product-grid { grid-template-columns: 1fr; } }

/* Gallery */
.aic-gallery { position: relative; }
.aic-main-image { width: 100%; border-radius: 12px; overflow: hidden; background: #f8f8f8; margin-bottom: 10px; }
.aic-main-image img { width: 100%; height: auto; display: block; transition: transform 0.3s; }
.aic-main-image:hover img { transform: scale(1.05); }
.aic-thumbnails { display: flex; gap: 8px; }
.aic-thumb { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; cursor: pointer; border: 2px solid transparent; transition: border-color 0.3s; }
.aic-thumb.active, .aic-thumb:hover { border-color: #667eea; }
.aic-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Product Info */
.aic-product-info { display: flex; flex-direction: column; gap: 20px; }
.aic-breadcrumb { font-size: 14px; color: #888; }
.aic-breadcrumb a { color: #667eea; text-decoration: none; }
.aic-breadcrumb a:hover { text-decoration: underline; }
.aic-product-title { font-size: 24px; font-weight: 700; color: #333; line-height: 1.5; }
.aic-product-rating { display: flex; align-items: center; gap: 8px; color: #f5a623; font-size: 14px; }
.aic-product-price { display: flex; align-items: center; gap: 15px; }
.aic-price-current { font-size: 28px; font-weight: 700; color: #e74c3c; }
.aic-price-currency { font-size: 14px; color: #888; }
.aic-price-old { font-size: 18px; color: #aaa; text-decoration: line-through; }
.aic-badge { display: inline-block; background: #27ae60; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.aic-badge.out-of-stock { background: #e74c3c; }

/* SKU & Meta */
.aic-product-meta { display: flex; flex-direction: column; gap: 8px; font-size: 14px; color: #666; padding: 15px; background: #f9f9f9; border-radius: 8px; }
.aic-meta-row { display: flex; gap: 10px; }
.aic-meta-label { font-weight: 600; min-width: 80px; }

/* Add to Cart */
.aic-cart-section { display: flex; align-items: center; gap: 15px; padding: 20px; background: #f9f9f9; border-radius: 12px; }
.aic-quantity { display: flex; align-items: center; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
.aic-qty-btn { width: 40px; height: 40px; border: none; background: #f0f0f0; cursor: pointer; font-size: 18px; transition: background 0.2s; }
.aic-qty-btn:hover { background: #e0e0e0; }
.aic-qty-input { width: 50px; height: 40px; text-align: center; border: none; border-left: 1px solid #ddd; border-right: 1px solid #ddd; font-size: 16px; }
.aic-add-to-cart { flex: 1; padding: 14px 30px; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.aic-add-to-cart:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.3); }
.aic-buy-now { padding: 14px 30px; background: #27ae60; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.aic-buy-now:hover { background: #219a52; }

/* Features */
.aic-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; }
.aic-feature { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f9f9f9; border-radius: 8px; font-size: 13px; }
.aic-feature-icon { font-size: 24px; }

/* Tabs */
.aic-tabs { margin-top: 40px; }
.aic-tab-nav { display: flex; border-bottom: 2px solid #eee; }
.aic-tab-btn { padding: 12px 24px; border: none; background: none; font-size: 15px; font-weight: 600; color: #888; cursor: pointer; position: relative; transition: color 0.3s; }
.aic-tab-btn.active { color: #667eea; }
.aic-tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: #667eea; }
.aic-tab-content { padding: 25px 0; }
.aic-tab-pane { display: none; line-height: 1.8; color: #555; }
.aic-tab-pane.active { display: block; }

/* Related Products */
.aic-related { margin-top: 50px; }
.aic-related-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
.aic-related-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
@media (max-width: 768px) { .aic-related-grid { grid-template-columns: repeat(2, 1fr); } }
.aic-product-card { background: #fff; border: 1px solid #eee; border-radius: 12px; overflow: hidden; transition: all 0.3s; }
.aic-product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.aic-product-card img { width: 100%; height: 200px; object-fit: cover; }
.aic-product-card-info { padding: 15px; }
.aic-product-card-title { font-size: 14px; font-weight: 600; margin-bottom: 8px; line-height: 1.5; }
.aic-product-card-price { font-size: 16px; font-weight: 700; color: #e74c3c; }
</style>

<div class="aic-product-page">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        global $product;
        $gallery = $product->get_gallery_image_ids();
        $main_image = wp_get_attachment_url($product->get_image_id());
        ?>
        
        <div class="aic-product-grid">
            <!-- Gallery -->
            <div class="aic-gallery">
                <div class="aic-main-image">
                    <img id="aic-main-img" src="<?php echo esc_url($main_image); ?>" alt="<?php the_title_attribute(); ?>">
                </div>
                <?php if (!empty($gallery)) : ?>
                    <div class="aic-thumbnails">
                        <div class="aic-thumb active" onclick="changeImage('<?php echo esc_url($main_image); ?>', this)">
                            <img src="<?php echo esc_url($main_image); ?>" alt="">
                        </div>
                        <?php foreach ($gallery as $img_id) : ?>
                            <div class="aic-thumb" onclick="changeImage('<?php echo esc_url(wp_get_attachment_url($img_id)); ?>', this)">
                                <img src="<?php echo esc_url(wp_get_attachment_url($img_id)); ?>" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="aic-product-info">
                <div class="aic-breadcrumb">
                    <a href="<?php echo esc_url(home_url('/')); ?>">خانه</a> / 
                    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">فروشگاه</a> / 
                    <?php echo get_the_title(); ?>
                </div>

                <h1 class="aic-product-title"><?php the_title(); ?></h1>

                <div class="aic-product-rating">
                    <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                    <span>(<?php echo $product->get_review_count(); ?> نظر)</span>
                </div>

                <div class="aic-product-price">
                    <span class="aic-price-current"><?php echo wc_price($product->get_price()); ?></span>
                    <?php if ($product->get_regular_price() && $product->get_regular_price() !== $product->get_price()) : ?>
                        <span class="aic-price-old"><?php echo wc_price($product->get_regular_price()); ?></span>
                    <?php endif; ?>
                </div>

                <span class="aic-badge <?php echo $product->is_in_stock() ? '' : 'out-of-stock'; ?>">
                    <?php echo $product->is_in_stock() ? 'موجود در انبار' : 'ناموجود'; ?>
                </span>

                <div class="aic-product-meta">
                    <?php if ($product->get_sku()) : ?>
                        <div class="aic-meta-row">
                            <span class="aic-meta-label">کد محصول:</span>
                            <span><?php echo $product->get_sku(); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php
                    $cats = wc_get_product_category_list($product->get_id(), ', ');
                    if ($cats) : ?>
                        <div class="aic-meta-row">
                            <span class="aic-meta-label">دسته‌بندی:</span>
                            <span><?php echo $cats; ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($product->is_in_stock()) : ?>
                    <form class="aic-cart-section" action="<?php echo esc_url($product->add_to_cart_url()); ?>" method="post">
                        <div class="aic-quantity">
                            <button type="button" class="aic-qty-btn" onclick="changeQty(-1)">-</button>
                            <input type="number" name="quantity" value="1" min="1" class="aic-qty-input" id="aic-qty">
                            <button type="button" class="aic-qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                        <button type="submit" class="aic-add-to-cart">افزودن به سبد خرید</button>
                        <button type="button" class="aic-buy-now" onclick="this.form.submit()">خرید سریع</button>
                    </form>
                <?php endif; ?>

                <div class="aic-features">
                    <div class="aic-feature"><span class="aic-feature-icon">&#x1F6E5;</span> ارسال به سراسر کشور</div>
                    <div class="aic-feature"><span class="aic-feature-icon">&#x1F512;</span> ضمانت اصالت کالا</div>
                    <div class="aic-feature"><span class="aic-feature-icon">&#x1F4E6;</span> ارسال سریع ۱-۳ روزه</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="aic-tabs">
            <div class="aic-tab-nav">
                <button class="aic-tab-btn active" onclick="showTab('desc')">توضیحات</button>
                <button class="aic-tab-btn" onclick="showTab('info')">اطلاعات تکمیلی</button>
                <button class="aic-tab-btn" onclick="showTab('reviews')">نظرات (<?php echo $product->get_review_count(); ?>)</button>
            </div>
            <div class="aic-tab-content">
                <div class="aic-tab-pane active" id="tab-desc">
                    <?php the_content(); ?>
                </div>
                <div class="aic-tab-pane" id="tab-info">
                    <?php
                    $attributes = $product->get_attributes();
                    if (!empty($attributes)) :
                        foreach ($attributes as $attribute) :
                            echo '<p><strong>' . wc_attribute_label($attribute->get_name()) . ':</strong> ' . $attribute->get_options()[0] . '</p>';
                        endforeach;
                    else :
                        echo '<p>اطلاعات تکمیلی موجود نیست.</p>';
                    endif;
                    ?>
                </div>
                <div class="aic-tab-pane" id="tab-reviews">
                    <?php comments_template(); ?>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php
        $related_ids = wc_get_related_products($product->get_id(), 4);
        if (!empty($related_ids)) :
            $related = wc_get_products(['include' => $related_ids, 'limit' => 4]);
        ?>
            <div class="aic-related">
                <h2 class="aic-related-title">محصولات مرتبط</h2>
                <div class="aic-related-grid">
                    <?php foreach ($related as $rel) : ?>
                        <a href="<?php echo esc_url($rel->get_permalink()); ?>" class="aic-product-card">
                            <img src="<?php echo esc_url($rel->get_image_id() ? wp_get_attachment_url($rel->get_image_id()) : wc_placeholder_img_src()); ?>" alt="<?php echo esc_attr($rel->get_name()); ?>">
                            <div class="aic-product-card-info">
                                <div class="aic-product-card-title"><?php echo $rel->get_name(); ?></div>
                                <div class="aic-product-card-price"><?php echo wc_price($rel->get_price()); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endwhile; ?>
</div>

<script>
function changeImage(src, el) {
    document.getElementById('aic-main-img').src = src;
    document.querySelectorAll('.aic-thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}
function changeQty(delta) {
    var input = document.getElementById('aic-qty');
    var val = parseInt(input.value) + delta;
    if (val >= 1) input.value = val;
}
function showTab(id) {
    document.querySelectorAll('.aic-tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.aic-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php get_footer('shop'); ?>
