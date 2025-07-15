<?php
// Menu logic based on $role_id from dashboard.php
$menu_items = [
    ['label' => 'Settings', 'url' => 'settings.php', 'roles' => [1]],
    ['label' => 'Rosters', 'url' => 'rosters.php', 'roles' => [1, 2]],
    ['label' => 'Tickets', 'url' => 'tickets.php', 'roles' => [1, 2, 3]],
    ['label' => 'My Roster', 'url' => 'my_roster.php', 'roles' => [1, 2, 4]],
    ['label' => 'Analytics', 'url' => 'analytics.php', 'roles' => [1]],
    ['label' => 'Report a problem', 'url' => 'problem.php', 'roles' => [1,2,4]],
    ['label' => 'Admin problem overview', 'url' => 'admin_problem.php', 'roles' => [1]],
    ['label' => 'Logout', 'url' => 'logout.php', 'roles' => [1,2,3,4]],

];

foreach ($menu_items as $item) {
    if (in_array($role_id, $item['roles'])) {
        echo "<a href=\"{$item['url']}\" target=\"mainframe\">{$item['label']}</a>";
    }
}
?>
