<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/recognition/lib.php');

$PAGE->set_url(new moodle_url('/local/recognition/form.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('createrecognition', 'local_recognition'));
$PAGE->set_heading(get_string('createrecognition', 'local_recognition'));

// Font Awesome ekle
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'));

echo $OUTPUT->header();

// Rozet kategorileri
$badges = [
    'creative' => [
        'title' => get_string('creative', 'local_recognition'),
        'icon' => 'fas fa-lightbulb'
    ],
    'team-player' => [
        'title' => get_string('teamplayer', 'local_recognition'),
        'icon' => 'fas fa-users'
    ],
    'congratulations' => [
        'title' => get_string('congratulations', 'local_recognition'),
        'icon' => 'fas fa-award'
    ],
    'employee' => [
        'title' => get_string('employeeofmonth', 'local_recognition'),
        'icon' => 'fas fa-trophy'
    ],
    'leadership' => [
        'title' => get_string('leadership', 'local_recognition'),
        'icon' => 'fas fa-crown'
    ],
    'customer-service' => [
        'title' => get_string('customerservice', 'local_recognition'),
        'icon' => 'fas fa-headset'
    ],
    'anniversary' => [
        'title' => get_string('anniversary', 'local_recognition'),
        'icon' => 'fas fa-calendar-alt'
    ],
    'milestone' => [
        'title' => get_string('milestone', 'local_recognition'),
        'icon' => 'fas fa-flag-checkered'
    ]
];

?>

<div class="recognition-form">
    <form id="recognition-form" method="post" action="<?php echo $CFG->wwwroot; ?>/local/recognition/submit.php">
        <h3 class="mb-4"><?php echo get_string('selectbadge', 'local_recognition'); ?></h3>
        
        <div class="badge-grid">
            <?php foreach ($badges as $key => $badge): ?>
            <div class="badge-item" data-badge="<?php echo $key; ?>">
                <div class="badge-icon <?php echo $key; ?>">
                    <i class="<?php echo $badge['icon']; ?> fa-2x"></i>
                </div>
                <div class="badge-title"><?php echo $badge['title']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tributejs@5.1.3/dist/tribute.css">
            <div id="mention-editor" class="mention-editor" contenteditable="true" data-placeholder="Birini @ ile etiketle..."></div>
            <input type="hidden" name="message" id="message-hidden">
            <script src="https://cdn.jsdelivr.net/npm/tributejs@5.1.3/dist/tribute.min.js"></script>
            <script src="/local/recognition/mention-tribute-init.js"></script>
        </div>

        <div class="recognition-visibility">
            <div class="visibility-title"><?php echo get_string('visibleto', 'local_recognition'); ?></div>
            <select class="form-control" name="visibility">
                <option value="all"><?php echo get_string('allcompany', 'local_recognition'); ?></option>
                <option value="department"><?php echo get_string('department', 'local_recognition'); ?></option>
                <option value="team"><?php echo get_string('team', 'local_recognition'); ?></option>
            </select>
        </div>

        <input type="hidden" name="badge_type" id="selected_badge" value="">
        <button type="submit" class="recognition-submit">
            <?php echo get_string('createrecognition', 'local_recognition'); ?>
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const badgeItems = document.querySelectorAll('.badge-item');
    const selectedBadgeInput = document.getElementById('selected_badge');

    badgeItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove selected class from all items
            badgeItems.forEach(i => i.classList.remove('selected'));
            // Add selected class to clicked item
            this.classList.add('selected');
            // Update hidden input
            selectedBadgeInput.value = this.dataset.badge;
        });
    });
});
</script>

<?php
echo $OUTPUT->footer();
