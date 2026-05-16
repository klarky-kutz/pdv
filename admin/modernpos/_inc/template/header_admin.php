<?php
// Este arquivo é incluído no topo de todas as páginas do painel admin.
// O _init.php e a verificação de login já devem ter sido feitos ANTES de incluir este arquivo.

// O $document já foi criado pelo _init.php
?>
<!DOCTYPE html>
<html lang="<?php echo $document->get_lang_tag ? $document->get_lang_tag() : $document->langTag($active_lang);?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?php echo $document->getTitle(); ?><?php echo store('name') ? ' | ' . store('name') : ''; ?></title>
    
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <?php if ($store->get('favicon')): ?>
        <link rel="shortcut icon" href="<?php echo root_url(); ?>/assets/itsolution24/img/logo-favicons/<?php echo $store->get('favicon'); ?>">
    <?php else: ?>
        <link rel="shortcut icon" href="<?php echo root_url(); ?>/assets/itsolution24/img/logo-favicons/nofavicon.png">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <link type="text/css" href="<?php echo root_url(); ?>/assets/toastr/toastr.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo root_url(); ?>/assets/itsolution24/css/dashboard.css"> 
    
    <script type="text/javascript">
        var baseUrl = "<?php echo root_url(); ?>";
        var adminDir = "<?php echo ADMINDIRNAME; ?>";
        var refUrl = "<?php echo isset($session->data['ref_url']) ? $session->data['ref_url'] : ''?>";
    </script>
</head>
<body class="dashboard-body">

<div class="dashboard-wrapper">
    
    <div class="main-content">