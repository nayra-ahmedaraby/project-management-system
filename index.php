<?php
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Kanban Board';

include 'includes/header.php';

// Load HTML template
echo file_get_contents('templates/views/kanban.html');
?>

<script>
const currentUserId = <?php echo getCurrentUserId(); ?>;
const isManager = <?php echo isManager() ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/kanban.js"></script>

<?php include 'includes/footer.php'; ?>

