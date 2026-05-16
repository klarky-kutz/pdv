<?php
// Este arquivo é o menu lateral (sidebar)
// Ele é incluído pelo header_admin.php ou pelo arquivo principal da página.

// =======================================================
// CORREÇÃO: Pega o nome do arquivo da página atual
// =======================================================
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>

<div class="sidebar">

    <div class="sidebar-logo">
        <img src="<?php echo root_url(); ?>/assets/itsolution24/img/logo/logo.avif" alt="Logo PDV Loja">
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                
                <a class="nav-link <?php if ($currentPage == 'store_select.php' || $currentPage == 'dashboard.php') echo 'active'; ?>" 
                   href="<?php echo root_url(); ?>/store_select.php">
                    <i class="bi bi-house-door-fill"></i>
                    Início
                </a>
            </li>
            
            <li class="nav-item">
                
                <a class="nav-link <?php if ($currentPage == 'novo_cadastro.php' || $currentPage == 'lista.php') echo 'active'; ?>" 
                   data-bs-toggle="collapse" 
                   href="#submenu-lojas" 
                   role="button" 
                   aria-expanded="<?php echo ($currentPage == 'novo_cadastro.php' || $currentPage == 'lista.php') ? 'true' : 'false'; ?>" 
                   aria-controls="submenu-lojas">
                    
                    <i class="bi bi-shop-window"></i> <span>Lojas</span>
                    <i class="bi bi-chevron-down ms-auto"></i> </a>
                
                <div class="collapse <?php if ($currentPage == 'novo_cadastro.php' || $currentPage == 'lista.php') echo 'show'; ?>" 
                     id="submenu-lojas">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link sub-link <?php if ($currentPage == 'novo_cadastro.php') echo 'active-child'; ?>" 
                               href="<?php echo root_url();?><?php echo ADMINDIRNAME;?>/novo_cadastro.php">
                                <i class="bi bi-plus-lg"></i>
                                Adicionar Loja
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sub-link <?php if ($currentPage == 'lista.loja.php') echo 'active-child'; ?>" 
                               href="<?php echo root_url();?><?php echo ADMINDIRNAME;?>/lista.php">
                                <i class="bi bi-pencil-fill"></i>
                                Gerenciar Lojas
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-question-circle-fill"></i>
                    Suporte
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        
        <div class="user-profile">
            <?php 
            $user_image = user('user_image');
            $image_url = '';
            if ($user_image && defined('FILEMANAGERPATH') && is_file(FILEMANAGERPATH . $user_image) && file_exists(FILEMANAGERPATH . $user_image)) {
                $image_url = FILEMANAGERURL . $user_image; 
            } else if ($user_image && is_file(DIR_STORAGE . 'users/' . $user_image) && file_exists(DIR_STORAGE . 'users/' . $user_image)) {
                $image_url = root_url().'storage/users/' . $user_image;
            }

            if ($image_url) : 
            ?>
                <img src="<?php echo $image_url; ?>" alt="Avatar">
            <?php else : ?>
                <img src="<?php echo root_url(); ?>/assets/itsolution24/img/nopeople.png" alt="Avatar">
            <?php endif; ?>
            
            <span><?php echo user('username'); ?></span>
        </div>
        
        <a href="<?php echo root_url(); ?>/logout.php" class="btn btn-secondary logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>

</div>