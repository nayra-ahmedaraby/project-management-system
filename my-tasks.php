<?php
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'My Tasks';
$statusFilter = $_GET['status'] ?? '';

include 'includes/header.php';

// Load HTML template
echo file_get_contents('templates/views/my-tasks.html');
?>

<script>
const currentUserId = <?php echo getCurrentUserId(); ?>;
const isManager = <?php echo isManager() ? 'true' : 'false'; ?>;
const initialStatusFilter = '<?php echo $statusFilter; ?>';
</script>
<script src="assets/js/kanban.js"></script>
<script src="assets/js/my-tasks.js"></script>

<?php include 'includes/footer.php'; ?>
