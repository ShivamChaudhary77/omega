<?php
/**
 * Block "project-cards": every published project as cards, in display
 * order. New projects appear automatically.
 *
 * Fields:
 *   style        "carousel" (homepage) or "grid" (projects page)
 *   headingHtml  section heading
 *   intro        paragraph under the heading
 *   buttonLabel, buttonHref   carousel: button under the cards; grid: button above them
 *   exclude      optional list of project ids to leave out
 *
 * @var array $block
 */
$exclude = (array) ($block['exclude'] ?? []);
$projects = array_values(array_filter(projects_published(), fn ($p) => !in_array($p['id'], $exclude, true)));
$style = ($block['style'] ?? 'grid') === 'carousel' ? 'carousel' : 'grid';
$heading = $block['headingHtml'] ?? 'Our <span class="text-uppercase text-primary bg-light px-2">Projects</span>';
?>
<?php if ($style === 'carousel'): ?>
    <!-- Featured Projects Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h2 class="h1 mb-3"><?= $heading ?></h2>
<?php if (!empty($block['intro'])): ?>
                <p class="mx-auto mb-5" style="max-width: 600px;"><?= e($block['intro']) ?></p>
<?php endif; ?>
            </div>
            <div class="project-carousel-wrap wow fadeIn" data-wow-delay="0.2s">
                <div class="owl-carousel project-carousel">
<?php foreach ($projects as $project) { partial('project-card', ['project' => $project]); } ?>
                </div>
            </div>
<?php if (!empty($block['buttonLabel'])): ?>
            <div class="text-center mt-5">
                <a href="<?= e($block['buttonHref'] ?? '/project.html') ?>" class="btn btn-outline-primary px-4 py-2"><?= e($block['buttonLabel']) ?></a>
            </div>
<?php endif; ?>
        </div>
    </div>
    <!-- Featured Projects End -->
<?php else: ?>
    <!-- Project Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h2 class="h1 mb-3"><?= $heading ?></h2>
<?php if (!empty($block['intro'])): ?>
                <p class="mx-auto mb-4" style="max-width: 700px;"><?= e($block['intro']) ?></p>
<?php endif; ?>
<?php if (!empty($block['buttonLabel'])): ?>
                <a href="<?= e($block['buttonHref'] ?? '/contact.html') ?>" class="btn btn-primary px-4 py-2 mb-5"><?= e($block['buttonLabel']) ?></a>
<?php endif; ?>
            </div>
            <div class="row g-4">
<?php foreach ($projects as $i => $project): ?>
                <div class="col-md-6 col-lg-4 wow fadeIn" data-wow-delay="0.<?= $i % 3 + 1 ?>s">
<?php partial('project-card', ['project' => $project, 'grid' => true]); ?>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Project End -->
<?php endif; ?>
