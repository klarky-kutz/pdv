<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Minhas Lojas - ModernPOS</title>

  <link rel="stylesheet" href="/modernpos/assets/css/stores.css">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Inputmask -->
  <script src="https://cdn.jsdelivr.net/npm/inputmask@5/dist/inputmask.min.js"></script>
</head>
<body>
  <main class="main-content">
    <header class="page-header">
      <div>
        <h1>Minhas Lojas</h1>
        <p>Gerencie suas lojas e configurações (modo demo sem banco de dados)</p>
      </div>

      <button class="btn btn-primary" type="button" onclick="openCreateModal()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nova Loja
      </button>
    </header>

    <!-- Grid (renderizado via JS) -->
    <div class="stores-grid" id="stores-container"></div>
  </main>

  <!-- Toast Container -->
  <div class="toast-container" id="toast-container"></div>

  <!-- Modals -->
  <?php include __DIR__ . '/_modals/store_create.php'; ?>
  <?php include __DIR__ . '/_modals/store_edit.php'; ?>
  <?php include __DIR__ . '/_modals/store_config.php'; ?>

  <script>
    // Força modo demo (sem backend) para stores.js
    window.MODERNPOS_STORES_MODE = 'demo';
  </script>
  <script src="/modernpos/assets/js/stores.js"></script>
</body>
</html>
