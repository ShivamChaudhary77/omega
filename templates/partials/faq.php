<?php
/**
 * FAQ accordion (same markup as the static pages). Answers may contain
 * simple HTML that was sanitised on save.
 *
 * @var array  $faqs  [['question' => ..., 'answer' => ...], ...]
 * @var string $id    unique accordion id, e.g. "faq-about"
 * @var string $title optional section heading
 */
$title = $title ?? 'Frequently Asked Questions';
if (empty($faqs)) {
    return;
}
?>
    <!-- FAQ Start -->
    <div class="container-fluid py-5">
        <div class="container py-5" style="max-width: 860px;">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h2 class="h1 mb-5"><?= e($title) ?></h2>
            </div>
            <div class="post-faq mt-0">
                <div class="accordion" id="<?= e($id) ?>">
<?php foreach (array_values($faqs) as $i => $faq): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse"
                                data-bs-target="#<?= e($id) ?>-<?= $i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="<?= e($id) ?>-<?= $i ?>">
                                <?= e($faq['question'] ?? '') ?>

                            </button>
                        </h3>
                        <div id="<?= e($id) ?>-<?= $i ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" data-bs-parent="#<?= e($id) ?>">
                            <div class="accordion-body"><?= $faq['answer'] ?? '' ?></div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- FAQ End -->
