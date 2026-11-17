<?php
/**
 * Block "hero": page title band with breadcrumb.
 *
 * Fields: h1 (optional, defaults to the page h1), variant ("wide" optional)
 *
 * @var array $block
 * @var array $page
 */
partial('hero', ['page' => $page, 'h1' => $block['h1'] ?? null, 'variant' => $block['variant'] ?? null]);
