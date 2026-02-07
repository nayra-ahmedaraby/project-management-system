<?php
require_once 'includes/functions.php';
requireLogin();

if (!isManager()) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Projects';

include 'includes/header.php';

// Load HTML template
echo file_get_contents('templates/views/projects.html');
?>

<script src="assets/js/projects.js"></script>

<?php include 'includes/footer.php'; ?>
