<?php
// Menu logic based on rights from dashboard.php
$menu_items = [
    ['label' => 'Settings', 'url' => 'settings.php', 'rights' => ['settings']],
    ['label' => 'Rosters', 'url' => 'rosters.php', 'rights' => ['rosters']],
    ['label' => 'Tickets', 'url' => 'tickets.php', 'rights' => ['tickets']],
    ['label' => 'Special Discounts', 'url' => 'special_discounts.php', 'rights' => ['tickets']],
    ['label' => 'My Roster', 'url' => 'my_roster.php', 'rights' => ['my_roster']],
    ['label' => 'Analytics', 'url' => 'analytics.php', 'rights' => ['analytics']],
    ['label' => 'Maintenance', 'url' => 'maintenance.php', 'rights' => ['maintenance']],
    ['label' => 'Report a problem', 'url' => 'problem.php', 'rights' => ['report_problem']],
    ['label' => 'Admin problem overview', 'url' => 'admin_problem.php', 'rights' => ['admin_problem']],
    ['label' => 'Role Management', 'url' => 'role_management.php', 'rights' => ['roles_management']],
    ['label' => 'Daily Operations', 'url' => 'daily_operations.php', 'rights' => ['daily_operations']],
    ['label' => 'Logout', 'url' => 'logout.php', 'rights' => ['logout']],
];

foreach ($menu_items as $item) {
    if (!empty(array_intersect($rights, $item['rights']))) {
        echo "<a href=\"{$item['url']}\" target=\"mainframe\">{$item['label']}</a>";
    }
}
?>
