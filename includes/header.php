<?php require_once 'includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Project Dashboard'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Dashboard</h2>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <span class="icon">📋</span> Kanban Board
                        </a>
                    </li>
                    <li>
                        <a href="my-tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my-tasks.php' ? 'active' : ''; ?>">
                            <span class="icon">✓</span> My Tasks
                        </a>
                    </li>
                    <li>
                        <a href="calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                            <span class="icon">📅</span> Calendar
                        </a>
                    </li>
                    <?php if (isManager()): ?>
                    <li>
                        <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
                            <span class="icon">📁</span> Projects
                        </a>
                    </li>
                    <li>
                        <a href="members.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>">
                            <span class="icon">👥</span> Members
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="sidebar-projects">
                <h3>Projects</h3>
                <ul>
                    <?php 
                    $projects = getAllProjects();
                    foreach ($projects as $project): 
                    ?>
                    <li>
                        <a href="index.php?project=<?php echo $project['id']; ?>">
                            <span class="project-color" style="background: <?php echo $project['color']; ?>"></span>
                            <?php echo sanitize($project['name']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">☰</button>
                    <h1><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="user-menu">
                        <div class="user-info">
                            <span class="user-name"><?php echo sanitize($_SESSION['full_name']); ?></span>
                            <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="dropdown-menu">
                            <a href="logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
