<?php
/**
 * Block "faq": FAQ section. Its questions are added to the page's FAQPage
 * schema automatically.
 *
 * Fields: heading (plain) or headingHtml, items[{question, answer}], id (optional)
 *
 * @var array $block
 * @var array $page
 * @var int   $index
 */
partial('faq', [
    'faqs' => (array) ($block['items'] ?? []),
    'id' => (string) ($block['id'] ?? ('faq-' . $page['id'] . ($index ? '-' . $index : ''))),
    'title' => $block['heading'] ?? null,
    'headingHtml' => $block['headingHtml'] ?? null,
]);
