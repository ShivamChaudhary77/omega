<?php
/**
 * Block "rich-text": a heading plus formatted text, in a centred column.
 *
 * Fields: heading (optional), html
 *
 * @var array $block
 */
?>
    <div class="container-fluid py-5">
        <div class="container py-5" style="max-width: 960px;">
<?php if (!empty($block['heading'])): ?>
            <h2 class="mb-4"><?= e($block['heading']) ?></h2>
<?php endif; ?>
            <div class="post-body">
<?= $block['html'] ?? '' ?>

            </div>
        </div>
    </div>
