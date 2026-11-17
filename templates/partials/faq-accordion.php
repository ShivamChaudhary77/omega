<?php
/**
 * Bootstrap accordion of FAQ items (questions and answers are plain text).
 *
 * @var array  $faqs       [['question' => ..., 'answer' => plain text | 'answerHtml' => HTML], ...]
 * @var string $id         accordion element id
 * @var string $itemPrefix id prefix for each item (defaults to $id)
 */
$itemPrefix = $itemPrefix ?? $id;
?>
                <div class="accordion" id="<?= e($id) ?>">
<?php foreach (array_values($faqs) as $i => $faq): ?>
<?php $itemId = $itemPrefix . '-' . $i; ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse"
                                data-bs-target="#<?= e($itemId) ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="<?= e($itemId) ?>">
                                <?= e($faq['question'] ?? '') ?>

                            </button>
                        </h3>
                        <div id="<?= e($itemId) ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" data-bs-parent="#<?= e($id) ?>">
                            <div class="accordion-body"><?= isset($faq['answerHtml']) ? $faq['answerHtml'] : e($faq['answer'] ?? '') ?></div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
