<?php

return [
    // false on staging: robots.txt disallows everything and every page is noindex
    'indexable' => (bool) env('SEO_INDEXABLE', true),
    'ai_crawlers' => ['GPTBot', 'ClaudeBot', 'Google-Extended', 'PerplexityBot'],
];
