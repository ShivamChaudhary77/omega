<?php
/**
 * Project card (same markup as the homepage carousel and project.html grid).
 *
 * @var array $project  a project summary (see page_summary())
 * @var bool  $grid     true = project.html grid item (adds data-project-slug);
 *                      false = homepage carousel (uses "carouselCard" overrides)
 */
$card = (array) ($project['card'] ?? []);
if (empty($grid)) {
    $card = array_merge($card, (array) ($project['carouselCard'] ?? []));
}
$title = (string) ($card['title'] ?? $project['name'] ?? '');
$alt = (string) ($card['alt'] ?? $title);
$image = (string) ($card['image'] ?? $project['image'] ?? '');
?>
<a href="<?= e($project['urlPath']) ?>" class="project-card text-decoration-none"<?= !empty($grid) ? ' data-project-slug="' . e($project['id']) . '"' : '' ?>>
    <div class="project-card-img overflow-hidden">
        <img class="img-fluid w-100" src="<?= e($image) ?>" alt="<?= e($alt) ?>" loading="lazy">
    </div>
    <div class="project-card-body">
        <h4 class="mb-1"><?= e($title) ?></h4>
    </div>
</a>
