<?php
if (post_password_required()) return;
?>
<div id="comments" class="comments-area">
    <?php if (have_comments()) : ?>
        <h3 class="comments-title"><?php comments_number('بدون دیدگاه', 'یک دیدگاه', '% دیدگاه'); ?></h3>
        <ol class="comment-list">
            <?php wp_list_comments(['style' => 'ol', 'short_ping' => true, 'avatar_size' => 40]); ?>
        </ol>
    <?php endif; ?>
    <?php comment_form(['title_reply' => 'دیدگاه خود را بنویسید', 'label_submit' => 'ارسال']); ?>
</div>
