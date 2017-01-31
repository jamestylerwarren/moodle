<?php

require('config.php');

$PAGE->set_url('/test.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$iconsystem = \core\output\icon_system::instance();

$map = $iconsystem->get_icon_name_map();

$count = 0;
foreach ($map as $from => $to) {
    list($component, $key) = explode(':', $from);
    echo '<span style="margin: 1rem;">';
    echo $OUTPUT->pix_icon($key, '', $component);
    echo $OUTPUT->activity_icon($key, '', $component);
    echo '</span>';

    $count++;
    if ($count % 3 == 0) {
        echo '<br>';
    }
}

echo $OUTPUT->footer();
