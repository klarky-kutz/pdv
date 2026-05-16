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

$template_id = isset($request->get['template_id']) ? (int)$request->get['template_id'] : 1;

// Load template and validate access
$template_info = get_the_postemplate($template_id);
if (!$template_info || empty($template_info['template_id'])) {
	$_SESSION['error_message'] = 'Modelo de recibo inválido.';
	redirect('select_receipt_template.php');
}

$template_name = isset($template_info['template_name']) ? $template_info['template_name'] : '';
$template_meta = postemplate_parse_custom_marker($template_name);
$is_custom_marker_for_store = (
	isset($template_meta['is_custom']) && $template_meta['is_custom']
	&& (int)$template_meta['store_id'] === (int)store_id()
);
$is_legacy_custom_for_store = postemplate_is_legacy_custom_template_name($template_name, store_id());
$is_custom_for_store = $is_custom_marker_for_store || $is_legacy_custom_for_store;

// Security: this screen is ONLY for store-cloned templates
// (Globals must never be editable/visible here)
if (!$is_custom_for_store) {
	$_SESSION['error_message'] = 'Este modelo é global. Para editar, use a tela de seleção (o sistema cria a sua cópia automaticamente).';
	redirect('select_receipt_template.php');
}

// Determine if this is a custom copy with base link (so we can show the Reset button)
$can_reset_from_global = (
	$is_custom_marker_for_store
	&& (int)$template_meta['base_id'] > 0
);
$base_template_id = $can_reset_from_global ? (int)$template_meta['base_id'] : 0;

// Sidebar templates: show ONLY cloned templates for current store
$sidebar_templates = array();
foreach (get_postemplates() as $t) {
	$tname = isset($t['template_name']) ? $t['template_name'] : '';
	$tmeta = postemplate_parse_custom_marker($tname);
	$custom_marker = ($tmeta['is_custom'] && (int)$tmeta['store_id'] === (int)store_id());
	$legacy_custom = postemplate_is_legacy_custom_template_name($tname, store_id());
	if ($custom_marker || $legacy_custom) {
		$sidebar_templates[] = $t;
	}
}

// If somehow empty, show only the current template
if (empty($sidebar_templates)) {
	$sidebar_templates[] = array(
		'template_id' => $template_id,
		'template_name' => $template_name,
	);
}

// Set Document Title
$document->setTitle(trans('title_receipt_template'));

$angular_disabled = true;

// Add Script
$document->addScript('../assets/edit-area/edit_area_full.js');

// Include Header and Footer
include ("header.php");
include ("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

	<!-- Content Header Start-->
	<section class="content-header">
		<h1>
			<?php echo trans('text_receipt_tempalte_title'); ?>
			<small>
				<?php echo store('name'); ?>
			</small>
		</h1>
		<ol class="breadcrumb">
			<li>
				<a href="dashboard.php">
					<i class="fa fa-dashboard"></i> 
					<?php echo trans('text_dashboard'); ?>
				</a>
			</li>
			<li>
				<a href="store_single.php?tab=pos-setting">
					<?php echo trans('title_pos_setting'); ?>
				</a>
			</li>
			<li class="active">
				<?php echo trans('text_receipt_template'); ?>
			</li>
		</ol>
	</section>
	<!-- Content Header End-->

	<!-- Content Start-->
	<section class="content">

		<?php if(DEMO) : ?>
	    <div class="box">
	      <div class="box-body">
	        <div class="alert alert-info mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
	        </div>
	        <div class="alert alert-warning mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo trans('text_disabled_in_demo'); ?></p>
	        </div>
	      </div>
	    </div>
	    <?php endif; ?>
	    
		<article class="app-layout">
			<div class="app-container">
				<div class="app-row">
					<header class="app-header bg-gray">
						<h2 class="app-title"><i class="fa fa-fw fa-adjust"></i><?php echo trans('text_receipt_tempalte_sub_title');?></h2>
						<div class="box-tools pull-right">
							<div class="btn-group">
				                <a type="button" class="btn btn-sm btn-info" href="preview_receipt.php?template_id=<?php echo $template_id;?>">
				                  	<span class="fa fa-fw fa-eye"></span>&nbsp;<?php echo trans('button_preview');?> &rarr;
				                </a>
								<?php if ($can_reset_from_global): ?>
									<button type="button" class="btn btn-sm btn-warning" id="reset-template-btn" data-template-id="<?php echo (int)$template_id; ?>" data-base-template-id="<?php echo (int)$base_template_id; ?>">
										<span class="fa fa-fw fa-refresh"></span>&nbsp;Reset
									</button>
								<?php endif; ?>
			                </div>
						</div>
					</header>
					<aside class="app-col app-sidebar">
						<?php foreach ($sidebar_templates as $template): ?>
							<a class="sidebar-item <?php echo $template_id == $template['template_id'] ? 'active' : null;?>" href="receipt_template.php?template_id=<?php echo (int)$template['template_id'];?>">
								<i class="icon fa fa-fw fa-angle-right"></i>
								<span class="text"><?php echo postemplate_strip_custom_marker($template['template_name']);?></span>
							</a>
						<?php endforeach ?>
					</aside>
					<main class="app-col app-content">
						
						<h2 class="title"><b><?php echo trans('text_tempalte_content_title');?></b></h2>
						<textarea class="template-content-editor w-100" id="template-content-editor" name="postemplatecontent" data-id="<?php echo $template_id;?>"><?php echo get_the_postemplate($template_id,'template_content');?></textarea>

						<h2 class="title"><b><?php echo trans('text_tempalte_css_title');?></b></h2>
						<textarea class="template-css-editor w-100" id="template-css-editor" name="postemplatecss" data-id="<?php echo $template_id;?>"><?php echo get_the_postemplate($template_id,'template_css');?></textarea>
			

						<div class="tags">
							<h4><b><?php echo trans('text_template_tags');?></b>. Usage:<code>{{ logo }}</code></h4>
							<?php 
							$template_tags = get_postemplate_empty_data();
							foreach ($template_tags as $key => $val):?> 
								<?php if (is_array($val)):?>
									<h4><b class="text-red"><?php echo ucfirst(my_str_replace('_', ' ', $key));?></b> Loop Tags. Usage:<code>{{ <?php echo $key;?> }} {{ sl }} {{ /<?php echo $key;?> }}</code></h4>
									<?php foreach ($val as $k => $v): ?>
										<kbd>{{ <?php echo implode(', ', array_keys($v));?> }}</kbd>
									<?php endforeach;?>
								<?php else:?>
									<kbd>{{ <?php echo $key;?> }}</kbd>
								<?php endif; ?>	
							<?php endforeach;?>
						</div>

						
					</main>	
				</div>
				<div class="clearfix"></div>
			</div>
		</article>

	</section>
	<!-- Content End-->

</div>
<!-- Content Wrapper End -->

<?php if ($can_reset_from_global): ?>
<!-- Reset Template Modal -->
<style>
/* Center the reset modal better (Bootstrap 3 / AdminLTE) */
#resetTemplateModal .modal-dialog { margin-top: 18vh; }
@media (max-width: 767px) {
	#resetTemplateModal .modal-dialog { margin-top: 10vh; }
}
</style>
<div class="modal fade" id="resetTemplateModal" tabindex="-1" role="dialog" aria-labelledby="resetTemplateModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-yellow" style="border-bottom: 1px solid #f39c12;">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="resetTemplateModalLabel"><i class="fa fa-refresh"></i> Resetar modelo</h4>
			</div>
			<div class="modal-body">
				<div class="callout callout-warning" style="margin-bottom: 0;">
					<p>
						Este botão vai <b>substituir</b> seu HTML e CSS atual pelo <b>código padrão (global)</b> mais recente.
					</p>
					<ul style="margin-bottom:0; padding-left:18px;">
						<li>Você continuará com um modelo <b>personalizado</b> (não recebe updates automáticos).</li>
						<li>Use a lixeira na tela anterior para <b>voltar ao padrão</b> permanentemente.</li>
					</ul>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-warning" id="confirm-reset-template-btn">
					<i class="fa fa-refresh"></i> Resetar agora
				</button>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	var btn = document.getElementById('reset-template-btn');
	if (!btn) return;

	btn.addEventListener('click', function() {
		$('#resetTemplateModal').modal('show');
	});

	document.getElementById('confirm-reset-template-btn').addEventListener('click', function() {
		var templateId = btn.getAttribute('data-template-id');
		var baseTemplateId = btn.getAttribute('data-base-template-id');

		$.ajax({
			url: window.baseUrl + "_inc/ajax.php?type=RESETPOSRECEIPTTEMPLATE",
			dataType: "JSON",
			type: "POST",
			data: {
				template_id: templateId,
				base_template_id: baseTemplateId
			},
			success: function(res) {
				$('#resetTemplateModal').modal('hide');
				window.toastr.success(res.msg, "Success!");
				window.location.reload();
			},
			error: function(xhr, ajaxOptions, thrownError) {
				window.toastr.error(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText, "Error!");
			}
		});
	});
})();
</script>
<?php endif; ?>

<?php include ("footer.php"); ?>
