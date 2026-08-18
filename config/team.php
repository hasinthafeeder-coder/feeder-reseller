<?php

return [
    'tree' => [
        'max_depth' => (int) env('RESELLER_TEAM_TREE_MAX_DEPTH', 3),
        'children_limit' => (int) env('RESELLER_TEAM_TREE_CHILDREN_LIMIT', 60),
        'search_limit' => (int) env('RESELLER_TEAM_TREE_SEARCH_LIMIT', 10),
    ],
];
