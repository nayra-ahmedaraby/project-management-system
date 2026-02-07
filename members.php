<?php
require_once 'includes/functions.php';
requireLogin();

if (!isManager()) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Members';

include 'includes/header.php';

// Load HTML template
echo file_get_contents('templates/views/members.html');
?>

<script>
const currentUserId = <?php echo getCurrentUserId(); ?>;
</script>
<script src="assets/js/members.js"></script>

<?php include 'includes/footer.php'; ?>
