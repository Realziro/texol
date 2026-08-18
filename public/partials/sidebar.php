<?php
// Shared sidebar for all authenticated pages.
// Use $activeMenu (e.g. 'dashboard', 'users', 'tickets') before including this file.

$activeMenu = $activeMenu ?? 'dashboard';

function texol_is_active(string $menu, string $active): string
{
    return $menu === $active ? ' active' : '';
}
?>
<!-- Sidebar -->
<nav id="sidebar" class="sidebar d-flex flex-column flex-shrink-0">
    <div class="sidebar-header d-flex align-items-center justify-content-between px-3 px-lg-4 py-3">
        <span class="fs-6 fw-semibold text-white sidebar-brand-text">
        <img
                        src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg"
                        alt="Texol Energies"
                        class="brand-logo"
                    />        </span>
        <button
            class="btn btn-sm btn-outline-light d-lg-none"
            id="sidebarCloseBtn"
            type="button"
            aria-label="Close sidebar"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <hr class="sidebar-divider my-0" />

    <!-- Sidebar Navigation -->
    <ul class="nav nav-pills flex-column mb-auto mt-2 px-2 px-lg-3">
        <li class="nav-item">
            <a href="  index" class="nav-link<?php echo texol_is_active('dashboard', $activeMenu); ?>" data-menu="dashboard">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>
    
      
        <li>
            <a href="  job_cards" class="nav-link<?php echo texol_is_active('job_cards', $activeMenu); ?>" data-menu="job_cards">
                <i class="bi bi-clipboard-check me-2"></i>
              Tasks
            </a>
        </li>
        <?php if (check_permission('tickets', 'view')) : ?>
        <li>
            <a href="  tickets" class="nav-link<?php echo texol_is_active('tickets', $activeMenu); ?>" data-menu="tickets">
                <i class="bi bi-ticket-detailed me-2"></i>
                Tickets
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="  mytickets" class="nav-link<?php echo texol_is_active('mytickets', $activeMenu); ?>" data-menu="mytickets">
                <i class="bi bi-person-lines-fill me-2"></i>
                My Tickets
            </a>
        </li>
        <li>
            <a href="  requisition" class="nav-link<?php echo texol_is_active('requisitions', $activeMenu); ?>" data-menu="requisitions">
                <i class="bi bi-file-earmark-plus me-2"></i>
                Requisitions
            </a>
        </li>
        <?php if (check_permission('customer_feedback', 'view') || (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'call center agent')) : ?>
        <li>
            <a href="  customer_feedback" class="nav-link<?php echo texol_is_active('customer_feedback', $activeMenu); ?>" data-menu="customer_feedback">
                <i class="bi bi-chat-dots me-2"></i>
                Customer Feedback
            </a>
        </li>
        <?php endif; ?>
        <?php if (check_permission('users', 'view')) : ?>
        <li>
            <a href="  users" class="nav-link<?php echo texol_is_active('users', $activeMenu); ?>" data-menu="users">
                <i class="bi bi-people-fill me-2"></i>
                Users
            </a>
        </li>
        <?php endif; ?>

                <?php if (check_permission('mytickets', 'view') || (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'Technician')) : ?>

        <li>
            <a href="  shared" class="nav-link<?php echo texol_is_active('shared', $activeMenu); ?>" data-menu="shared">
                <i class="bi bi-people me-2"></i>
                Shared With Me
            </a>
        </li>
                <?php endif; ?>

       
        <li>
            <a href="  profile" class="nav-link<?php echo texol_is_active('profile', $activeMenu); ?>" data-menu="profile">
                <i class="bi bi-person-circle me-2"></i>
                Profile
            </a>
        </li>
        <?php if (check_permission('departments', 'view') || check_permission('categories', 'view') || check_permission('permissions', 'view') || check_permission('roles', 'view')) : ?>
        <li class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                <i class="bi bi-gear-fill me-2"></i>
                Admin Settings
            </a>
            <ul class="dropdown-menu">
                <?php if (check_permission('departments', 'view')) : ?>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('departments', $activeMenu); ?>" href="  departments">
                        <i class="bi bi-diagram-3-fill me-2"></i>Departments
                    </a>
                </li>
                <?php endif; ?>
                <?php if (check_permission('categories', 'view')) : ?>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('categories', $activeMenu); ?>" href="  categories">
                        <i class="bi bi-tags-fill me-2"></i>Categories
                    </a>
                </li>
                <?php endif; ?>
                <?php if (check_permission('roles', 'view')) : ?>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('roles', $activeMenu); ?>" href="  roles">
                        <i class="bi bi-person-vcard-fill me-2"></i>Roles
                    </a>
                </li>
                <?php endif; ?>
                <?php if (check_permission('permissions', 'view')) : ?>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('permissions', $activeMenu); ?>" href="  permissions">
                        <i class="bi bi-shield-lock-fill me-2"></i>Permissions
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('source', $activeMenu); ?>" href="  source">
                        <i class="bi bi-link-45deg me-2"></i>Source
                    </a>
                </li>
                <li>
                    <a class="dropdown-item<?php echo texol_is_active('branches', $activeMenu); ?>" href="  branches">
                        <i class="bi bi-building me-2"></i>Branches
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        <li class="mt-3">
            <a href="  logout" class="nav-link text-danger" data-menu="logout">
                <i class="bi bi-box-arrow-right me-2"></i>
                Logout
            </a>
        </li>
    </ul>

    <!-- Sidebar footer -->
    <div class="sidebar-footer mt-auto px-3 px-lg-4 py-3 small text-muted">
        <span>© <?php echo date('Y'); ?> Texol</span>
    </div>
</nav>
<!-- /Sidebar -->

