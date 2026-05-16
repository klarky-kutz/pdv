<?php 
ob_start();
session_start();
include '../_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'receipt_template')) {
	redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Helpers (keep DB schema intact, but allow custom copy/reset flows)
function postemplate_ensure_store_link($store_id, $template_id)
{
	$store_id = (int)$store_id;
	$template_id = (int)$template_id;

	$statement = db()->prepare("SELECT `pt2s` FROM `pos_template_to_store` WHERE `store_id` = ? AND `ttemplate_id` = ? LIMIT 1");
	$statement->execute(array($store_id, $template_id));
	$row = $statement->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		return;
	}

	$sort_stmt = db()->prepare("SELECT COALESCE(MAX(`sort_order`), 0) + 1 AS next_sort FROM `pos_template_to_store` WHERE `store_id` = ?");
	$sort_stmt->execute(array($store_id));
	$sort_row = $sort_stmt->fetch(PDO::FETCH_ASSOC);
	$next_sort = ($sort_row && isset($sort_row['next_sort'])) ? (int)$sort_row['next_sort'] : 1;

	$insert = db()->prepare("INSERT INTO `pos_template_to_store` (`store_id`, `ttemplate_id`, `is_active`, `status`, `sort_order`) VALUES (?, ?, 0, 1, ?)");
	$insert->execute(array($store_id, $template_id, $next_sort));
}

function postemplate_set_store_receipt_template_preference($store_id, $template_id)
{
	$store_id = (int)$store_id;
	$template_id = (int)$template_id;

	$storeModel = registry()->get('loader')->model('store');
	$store = $storeModel->getStore($store_id);
	$pref = $store ? valid_unserialize($store['preference']) : array();
	if (!is_array($pref)) {
		$pref = array();
	}
	$pref['receipt_template'] = $template_id;

	$update = db()->prepare("UPDATE `stores` SET `preference` = ? WHERE `store_id` = ?");
	$update->execute(array(serialize($pref), $store_id));
}


function postemplate_set_store_active_template($store_id, $template_id)
{
	$store_id = (int)$store_id;
	$template_id = (int)$template_id;

	postemplate_ensure_store_link($store_id, $template_id);

	$reset = db()->prepare("UPDATE `pos_template_to_store` SET `is_active` = 0 WHERE `store_id` = ?");
	$reset->execute(array($store_id));

	$activate = db()->prepare("UPDATE `pos_template_to_store` SET `is_active` = 1, `status` = 1 WHERE `store_id` = ? AND `ttemplate_id` = ?");
	$activate->execute(array($store_id, $template_id));

	// IMPORTANT: actual receipt printing uses store preference('receipt_template')
	postemplate_set_store_receipt_template_preference($store_id, $template_id);
}

function postemplate_find_custom_copy_id($store_id, $base_template_id)
{
	$store_id = (int)$store_id;
	$base_template_id = (int)$base_template_id;
	$marker = postemplate_build_custom_marker($store_id, $base_template_id);

	$statement = db()->prepare(
		"SELECT t.template_id\n"
		. "FROM `pos_templates` t\n"
		. "INNER JOIN `pos_template_to_store` pt2s ON (t.template_id = pt2s.ttemplate_id)\n"
		. "WHERE pt2s.store_id = ? AND t.template_name LIKE ?\n"
		. "LIMIT 1"
	);
	$statement->execute(array($store_id, '%' . $marker));
	$row = $statement->fetch(PDO::FETCH_ASSOC);
	return ($row && isset($row['template_id'])) ? (int)$row['template_id'] : 0;
}

$current_store_id = (int)store_id();

// Atualizar modelo selecionado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? $_POST['action'] : '';

	if ($action === 'activate_template') {
		$template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
		if ($template_id) {
			postemplate_set_store_active_template($current_store_id, $template_id);
			$_SESSION['success_message'] = 'Modelo ativado com sucesso!';
		}
		redirect('select_receipt_template.php');
	}

	if ($action === 'clone_for_store') {
		$base_template_id = isset($_POST['base_template_id']) ? (int)$_POST['base_template_id'] : 0;
		if (!$base_template_id) {
			redirect('select_receipt_template.php');
		}

		// Enforce: only 1 custom copy per (store, base_template)
		$existing_custom_id = postemplate_find_custom_copy_id($current_store_id, $base_template_id);
		if ($existing_custom_id) {
			postemplate_set_store_active_template($current_store_id, $existing_custom_id);
			$_SESSION['success_message'] = 'Você já tem uma cópia personalizada deste modelo. Abrindo para editar...';
			redirect('receipt_template.php?template_id=' . $existing_custom_id);
		}

		// Create copy (custom) and keep global template available
		$orig = get_the_postemplate($base_template_id);
		$orig_name = ($orig && isset($orig['template_name'])) ? postemplate_strip_custom_marker($orig['template_name']) : 'Template';
		$new_name = $orig_name . ' (Personalizado) ' . postemplate_build_custom_marker($current_store_id, $base_template_id);

		$content = get_the_postemplate($base_template_id, 'template_content');
		$css = get_the_postemplate($base_template_id, 'template_css');

		$insert = db()->prepare("INSERT INTO `pos_templates` (`template_name`, `template_content`, `template_css`, `created_at`, `updated_at`, `created_by`) VALUES (?, ?, ?, NOW(), NOW(), ?)");
		$insert->execute(array($new_name, $content, $css, (int)user_id()));
		$new_id = (int)db()->lastInsertId();

		// Link the custom template to this store and set it active
		postemplate_ensure_store_link($current_store_id, $new_id);
		postemplate_set_store_active_template($current_store_id, $new_id);

		$_SESSION['success_message'] = 'Cópia criada! Este modelo é seu e NÃO receberá atualizações automáticas. Você pode editar livremente.';
		redirect('receipt_template.php?template_id=' . $new_id);
	}

	if ($action === 'delete_custom') {
		$custom_template_id = isset($_POST['custom_template_id']) ? (int)$_POST['custom_template_id'] : 0;
		$base_template_id = isset($_POST['base_template_id']) ? (int)$_POST['base_template_id'] : 0;
		if (!$custom_template_id || !$base_template_id) {
			redirect('select_receipt_template.php');
		}

		// Remove store link
		$del_link = db()->prepare("DELETE FROM `pos_template_to_store` WHERE `store_id` = ? AND `ttemplate_id` = ?");
		$del_link->execute(array($current_store_id, $custom_template_id));

		// Delete template record only if no longer linked to any store
		$count_stmt = db()->prepare("SELECT COUNT(*) AS total FROM `pos_template_to_store` WHERE `ttemplate_id` = ?");
		$count_stmt->execute(array($custom_template_id));
		$count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
		$links = ($count_row && isset($count_row['total'])) ? (int)$count_row['total'] : 0;
		if ($links === 0) {
			$del_tpl = db()->prepare("DELETE FROM `pos_templates` WHERE `template_id` = ? LIMIT 1");
			$del_tpl->execute(array($custom_template_id));
		}

		// Revert to global/base template
		postemplate_set_store_active_template($current_store_id, $base_template_id);
		$_SESSION['success_message'] = 'Modelo personalizado removido. Você voltou a usar o modelo padrão (global).';
		redirect('select_receipt_template.php');
	}
}

// Template em uso no momento (a impressão usa preference('receipt_template'))
$current_template_id = (int)get_preference('receipt_template');
if (!$current_template_id) {
	$stmt_map = db()->prepare("SELECT `ttemplate_id` FROM `pos_template_to_store` WHERE `store_id` = ? AND `is_active` = 1 LIMIT 1");
	$stmt_map->execute(array($current_store_id));
	$row = $stmt_map->fetch(PDO::FETCH_ASSOC);
	$current_template_id = ($row && isset($row['ttemplate_id'])) ? (int)$row['ttemplate_id'] : 1;
}

// Prepare template lists (global templates + per-base custom copies)
$templates = get_postemplates();
$global_templates = array();
$custom_by_base = array();
$orphan_custom_templates = array();

foreach ($templates as $t) {
	$meta = postemplate_parse_custom_marker(isset($t['template_name']) ? $t['template_name'] : '');
	if ($meta['is_custom'] && (int)$meta['store_id'] === $current_store_id && (int)$meta['base_id'] > 0) {
		// For each base template, keep only one custom copy
		if (!isset($custom_by_base[$meta['base_id']])) {
			$custom_by_base[$meta['base_id']] = $t;
		}
		continue;
	}

	// Hide legacy custom templates without marker from main grid to keep screen clean
	// (Do NOT hide global templates that simply contain the word "Personalizado")
	$template_name = isset($t['template_name']) ? $t['template_name'] : '';
	if ($meta['is_custom'] === false && postemplate_is_legacy_custom_template_name($template_name)) {
		$orphan_custom_templates[] = $t;
		continue;
	}

	$global_templates[] = $t;
}

// If the store has no global templates linked, relink them automatically (common in older DBs)
// You can force rerun by opening: select_receipt_template.php?relink=1
$force_relink = isset($_GET['relink']) && $_GET['relink'];
if (empty($global_templates) && !empty($orphan_custom_templates) && (empty($_SESSION['postemplate_relinked']) || $force_relink)) {
	try {
		$all_stmt = db()->prepare("SELECT `template_id`, `template_name` FROM `pos_templates` ORDER BY `template_id` ASC");
		$all_stmt->execute();
		$all_templates = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!is_array($all_templates)) {
			$all_templates = array();
		}

		foreach ($all_templates as $tpl) {
			$tid = isset($tpl['template_id']) ? (int)$tpl['template_id'] : 0;
			$tname = isset($tpl['template_name']) ? $tpl['template_name'] : '';
			if (!$tid) {
				continue;
			}

			// Skip custom copies
			$meta = postemplate_parse_custom_marker($tname);
			if ($meta['is_custom']) {
				continue;
			}
			// Skip legacy custom templates (older naming)
			if (postemplate_is_legacy_custom_template_name($tname)) {
				continue;
			}

			postemplate_ensure_store_link($current_store_id, $tid);
		}

		$_SESSION['postemplate_relinked'] = 1;
		$_SESSION['success_message'] = 'Modelos globais restaurados para esta loja. Agora você pode selecionar/editar normalmente.';
		redirect('select_receipt_template.php');
	} catch (Exception $e) {
		// If it fails, allow page to render warning (better than blank)
		$_SESSION['postemplate_relinked'] = 1;
	}
}

// Set Document Title
$document->setTitle(trans('title_receipt_template'));

$angular_disabled = true;

// Include Header and Footer
include ("header.php");
include ("left_sidebar.php");
?>

<style>
/* Estilo seguindo padrão ModernPOS */
.template-card {
	background: #fff;
	border: 1px solid #d2d6de;
	border-radius: 3px;
	margin-bottom: 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,0.1);
	transition: all 0.3s ease;
}

.template-card:hover {
	box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

.template-card.selected {
	border: 2px solid #3c8dbc;
	box-shadow: 0 0 10px rgba(60,141,188,0.3);
}

.template-card-header {
	background: #f4f4f4;
	border-bottom: 1px solid #d2d6de;
	padding: 10px 15px;
}

.template-card-title {
	font-size: 16px;
	font-weight: 600;
	margin: 0;
	color: #333;
}

.template-badge {
	background: #00a65a;
	color: white;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	margin-left: 10px;
}

.template-card-body {
	padding: 15px;
}

.template-preview {
	height: 350px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 3px;
	overflow: hidden;
	margin-bottom: 15px;
}

.template-preview iframe {
	width: 100%;
	height: 100%;
	border: none;
	transform: scale(0.8);
	transform-origin: top center;
}

.template-description {
	color: #666;
	font-size: 13px;
	line-height: 1.5;
	margin-bottom: 12px;
}


.template-features {
	list-style: none;
	padding: 0;
	margin: 0 0 15px 0;
}

.template-features li {
	padding: 5px 0;
	color: #555;
	font-size: 12px;
}

.template-features li i {
	color: #00a65a;
	font-size: 13px;
	margin-right: 5px;
}

.template-actions {
	display: flex;
	gap: 10px;
}

.btn-select-template {
	flex: 1;
}

.btn-select-template:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.mt-10 {
	margin-top: 10px;
}

.mb-10 {
	margin-bottom: 10px;
}

@media (max-width: 768px) {
	.template-actions {
		flex-direction: column;
	}
}
</style>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

	<!-- Content Header Start-->
	<section class="content-header">
		<h1>
			<?php echo trans('text_receipt_template'); ?>
			<small><?php echo store('name'); ?></small>
		</h1>
		<ol class="breadcrumb">
			<li><a href="dashboard.php"><i class="fa fa-dashboard"></i> <?php echo trans('text_dashboard'); ?></a></li>
			<li><a href="store_single.php?tab=pos-setting"><?php echo trans('title_pos_setting'); ?></a></li>
			<li class="active"><?php echo trans('text_receipt_template'); ?></li>
		</ol>
	</section>
	<!-- Content Header End-->

	<!-- Content Start-->
	<section class="content">

		<!-- Success / Error Messages -->
		<?php if (isset($_SESSION['success_message'])): ?>
			<div class="alert alert-success alert-dismissible">
				<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
				<h4><i class="icon fa fa-check"></i> Sucesso!</h4>
				<?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
			</div>
		<?php endif; ?>

		<?php if (isset($_SESSION['error_message'])): ?>
			<div class="alert alert-danger alert-dismissible">
				<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
				<h4><i class="icon fa fa-ban"></i> Atenção!</h4>
				<?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
			</div>
		<?php endif; ?>

		<div class="box">
			<div class="box-header with-border">
				<h3 class="box-title">Selecione o Modelo de Recibo</h3>
				<p class="help-block">Escolha o modelo que será usado nas impressões de recibo do sistema.</p>
			</div>
			<div class="box-body">
				<?php if (empty($global_templates) && !empty($orphan_custom_templates)): ?>
					<div class="alert alert-danger" style="margin-bottom:15px;">
						<b>Nenhum modelo global disponível para esta loja.</b><br>
						Isso geralmente acontece quando modelos antigos foram criados e os padrões não ficaram vinculados à loja.<br>
						<a class="btn btn-sm btn-primary" href="select_receipt_template.php?relink=1" style="margin-top:10px;">
							<i class="fa fa-refresh"></i> Restaurar modelos globais
						</a>
					</div>
				<?php endif; ?>
				<div class="row">

					<?php 
					foreach ($global_templates as $template):
						$base_template_id = (int)$template['template_id'];
						$custom = isset($custom_by_base[$base_template_id]) ? $custom_by_base[$base_template_id] : null;
						$custom_template_id = $custom ? (int)$custom['template_id'] : 0;

						$is_custom_active = ($custom_template_id && $current_template_id == $custom_template_id);
						$is_base_active = ($current_template_id == $base_template_id);
						$is_selected = $is_custom_active || $is_base_active;

						$active_badge_text = $is_selected ? '✓ Ativo' : null;

						// Preview what is currently used (custom if active; otherwise base)
						$effective_template_id = $is_custom_active ? $custom_template_id : $base_template_id;

						$display_name = postemplate_strip_custom_marker($template['template_name']);
						$display_name = trim($display_name);
					?>
					
					<div class="col-md-6">
						<div class="template-card <?php echo $is_selected ? 'selected' : ''; ?>">
							
							<!-- Header -->
							<div class="template-card-header">
								<h4 class="template-card-title">
									<i class="fa fa-file-text-o"></i>
									<?php echo htmlspecialchars($display_name); ?>
									<?php if ($active_badge_text): ?>
										<span class="template-badge"><?php echo $active_badge_text; ?></span>
									<?php endif; ?>
								</h4>
							</div>
							
							<!-- Body -->
							<div class="template-card-body">
								<!-- Preview -->
								<div class="template-preview">
									<iframe src="preview_receipt.php?template_id=<?php echo $effective_template_id; ?>&mini=1"></iframe>
								</div>
								
								<!-- Description -->
								<p class="template-description">
									<?php 
									if (stripos($display_name, 'minimal') !== false || stripos($display_name, 'minimalista') !== false) {
										echo "Design limpo e minimalista, perfeito para impressoras térmicas.";
									} elseif (stripos($display_name, 'classic') !== false || stripos($display_name, 'clássico') !== false) {
										echo "Modelo clássico e profissional com todos os detalhes essenciais.";
									} elseif (stripos($display_name, 'modern') !== false || stripos($display_name, 'moderno') !== false) {
										echo "Design moderno e elegante com layout diferenciado.";
									} else {
										echo "Modelo profissional para impressão de recibos.";
									}
									?>
								</p>
								
								<!-- Features -->
								<ul class="template-features">
									<li><i class="fa fa-check"></i> Impressão térmica</li>
									<li><i class="fa fa-check"></i> QR Code e Barcode</li>
									<li><i class="fa fa-check"></i> Personalizável</li>
								</ul>

								<?php if (!$custom_template_id): ?>
								<!-- GLOBAL TEMPLATE -->
								<div class="template-actions">
									<form method="post" style="flex: 1;">
										<input type="hidden" name="action" value="activate_template">
										<input type="hidden" name="template_id" value="<?php echo $base_template_id; ?>">
										<button type="submit" class="btn <?php echo ($current_template_id == $base_template_id) ? 'btn-success' : 'btn-primary'; ?> btn-block" <?php echo ($current_template_id == $base_template_id) ? 'disabled' : ''; ?>>
											<i class="fa <?php echo ($current_template_id == $base_template_id) ? 'fa-check' : 'fa-hand-o-right'; ?>"></i>
											<?php echo ($current_template_id == $base_template_id) ? '✓ Ativo' : 'Selecionar'; ?>
										</button>
									</form>
									<a href="preview_receipt.php?template_id=<?php echo $base_template_id; ?>" target="_blank" class="btn btn-default" title="Visualizar">
										<i class="fa fa-eye"></i>
									</a>
									<button type="button" class="btn btn-default" onclick="cloneAndEdit(<?php echo $base_template_id; ?>)" title="Editar">
										<i class="fa fa-edit"></i>
									</button>
								</div>
								<?php else: ?>
								<!-- CUSTOM TEMPLATE (one per global/base) -->
								<div class="template-actions">
									<form method="post" style="flex: 1;">
										<input type="hidden" name="action" value="activate_template">
										<input type="hidden" name="template_id" value="<?php echo $custom_template_id; ?>">
										<button type="submit" class="btn <?php echo ($current_template_id == $custom_template_id) ? 'btn-success' : 'btn-primary'; ?> btn-block" <?php echo ($current_template_id == $custom_template_id) ? 'disabled' : ''; ?>>
											<i class="fa <?php echo ($current_template_id == $custom_template_id) ? 'fa-check' : 'fa-hand-o-right'; ?>"></i>
											<?php echo ($current_template_id == $custom_template_id) ? '✓ Ativo' : 'Ativar'; ?>
										</button>
									</form>
									<a href="preview_receipt.php?template_id=<?php echo $effective_template_id; ?>" target="_blank" class="btn btn-default" title="Visualizar">
										<i class="fa fa-eye"></i>
									</a>
									<a href="receipt_template.php?template_id=<?php echo $custom_template_id; ?>" class="btn btn-default" title="Editar">
										<i class="fa fa-edit"></i>
									</a>
									<button type="button" class="btn btn-danger" onclick="deleteCustomTemplate(<?php echo $custom_template_id; ?>, <?php echo $base_template_id; ?>)" title="Deletar">
										<i class="fa fa-trash"></i>
									</button>
								</div>
								<?php endif; ?>
								
							</div>
						</div>
					</div>
					
					<?php endforeach; ?>

					<?php if (!empty($orphan_custom_templates)): ?>
						<div class="col-md-12">
							<div class="alert alert-warning">
								<b>Atenção:</b> Existem modelos personalizados antigos (sem vínculo ao modelo global). Eles foram ocultados para manter a tela limpa.
							</div>
						</div>
					<?php endif; ?>

				</div>
			</div>
		</div>

	</section>
	<!-- Content End-->

</div>
<!-- Content Wrapper End -->

<!-- Hidden forms (no modal) -->
<form method="post" id="cloneTemplateForm" style="display:none;">
	<input type="hidden" name="action" value="clone_for_store">
	<input type="hidden" name="base_template_id" id="clone_base_template_id" value="">
</form>
<form method="post" id="deleteCustomTemplateForm" style="display:none;">
	<input type="hidden" name="action" value="delete_custom">
	<input type="hidden" name="custom_template_id" id="delete_custom_template_id" value="">
	<input type="hidden" name="base_template_id" id="delete_base_template_id" value="">
</form>

<script>
function cloneAndEdit(baseTemplateId) {
	document.getElementById('clone_base_template_id').value = baseTemplateId;
	document.getElementById('cloneTemplateForm').submit();
}

function deleteCustomTemplate(customTemplateId, baseTemplateId) {
	if (!confirm('Excluir sua personalização e voltar ao modelo global?')) {
		return;
	}
	document.getElementById('delete_custom_template_id').value = customTemplateId;
	document.getElementById('delete_base_template_id').value = baseTemplateId;
	document.getElementById('deleteCustomTemplateForm').submit();
}
</script>

<?php include ("footer.php"); ?>
