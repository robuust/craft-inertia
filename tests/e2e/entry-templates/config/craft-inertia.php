<?php

return [
    'rootView' => 'frontend::app',
    'pages' => [
        'Home' => ['fields' => ['introduction']],
        'Articles/Show' => ['fields' => [
            'summary', 'body', 'richText', 'publishedAt', 'featured', 'rating', 'tone', 'tags',
            'heroImage' => ['transform' => ['width' => 320, 'height' => 180, 'mode' => 'crop'], 'srcset' => ['320w', '640w']],
            'relatedArticles' => ['fields' => ['summary']],
            'contentBlocks' => ['types' => [
                'textBlock' => ['fields' => ['heading', 'blockCopy']],
                'groupBlock' => ['fields' => ['heading', 'nestedContent' => ['fields' => ['heading', 'blockCopy']]]],
            ]],
        ]],
        'Journal' => ['fields' => ['teaser', 'copy']],
        'Structure' => ['fields' => ['location']],
    ],
];
