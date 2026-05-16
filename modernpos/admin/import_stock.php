<?php 
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'import_stock')) {
	redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

if (isset($request->get['action_type']) && $request->get['action_type'] == 'export') 
{
	try {

		if (user_group_id() != 1 && !has_permission('access', 'import_stock') || DEMO) {
	      throw new Exception(trans('error_permission'));
	    }

	    if (!isset($request->get['sup_id'])) {
	    	throw new Exception(trans('error_invalid_request_parameter'));
	    }
	    $sup_id = (int)$request->get['sup_id'];

		$title = 'stock';
		$products = get_products(array('filter_sup_id' => $sup_id));
		if (!$products) {
	    	throw new Exception(trans('error_product_not_found'));
	    }
		$output = fopen("php://output",'w') or die("Can't open php://output");
		header("Content-Type:application/csv"); 
		header("Content-Disposition:attachment;filename=".$title."-".store_id().".csv"); 
		fputcsv($output, array('Name', 'Code', 'QTY.'));
		foreach($products as $line) {
		    fputcsv($output, array($line['p_name'], $line['p_code'], $line['quantity_in_stock']));
		}
		fclose($output) or die("Can't close php://output");
		exit;

	} catch (Exception $e) {

		dd($e->getMessage());
	}
}

if (isset($request->post['submit'])) 
{
	try {

		if (user_group_id() != 1 && !has_permission('access', 'import_stock') || DEMO) {
	      throw new Exception(trans('error_permission'));
	    }
		if (!$_FILES['filename']['name']) {
			throw new Exception(trans('error_invalid_file'));
		}

		$csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');

		if (!in_array($_FILES["filename"]["type"], $csvMimes)) {
			throw new Exception(trans('error_invalid_file'));
		}

		if(isset($_FILES["filename"]["type"])) {
			$validextensions = array("csv");
			$temporary = explode(".", $_FILES["filename"]["name"]);
			$file_extension = end($temporary);
			if (in_array($file_extension, $validextensions)) {
				if ($_FILES["filename"]["error"] > 0) {
					throw new Exception("Return Code: " . $_FILES['filename']['error']);
				} else {
					$temp = explode(".", $_FILES["filename"]["name"]);
					$newfilename = 'stock-'.store_id().'.'.end($temp);
					$sourcePath = $_FILES["filename"]["tmp_name"];
					$path = DIR_STORAGE."products/".$newfilename;
					if(!move_uploaded_file($sourcePath, $path)) {
						throw new Exception(trans('error_upload'));
					}
				}

				$sup_id = 0;
				$path = DIR_STORAGE.'products/stock-'.store_id().'.csv';
				if (file_exists($path)) {
					$sup_id = (int) $request->post['sup_id'];
					$file = fopen($path,"r");
					while (($line = fgetcsv($file, 1000, ",")) !== FALSE) {
						if (!$line) {
							continue;
						}
						$p_name = $line[0];
						if ($p_name == 'Name') {
							continue;
						}
						$p_code = $line[1];
						$qty = $line[2];
						if ($qty <= 0) {
							continue;
						}
				    	$products = get_products(array('filter_p_code' => $p_code));
				    	if (!$products) {
				    		continue;
				    	}
				    	$product = $products[0];
				    	$sup_id = $product['sup_id'];
				    	break;
				    }
				}

				if (!$sup_id) {
					throw new Exception(trans('error_invalid_file'));
				}

				redirect(root_url().ADMINDIRNAME.'/purchase.php?box_state=open&sup_id='.$sup_id);

			} else {
				throw new Exception(trans('error_invalid_file'));
			}
		}

		$Hooks->do_action('After_Import_Product', $request);
	}
	catch(Exception $e) { 
	    $error_message = $e->getMessage();
	}
}

$message = '';
$document->setTitle(trans('title_import_stock'));

include("header.php");
include ("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

	<!-- Content Header Start -->
	<section class="content-header">
		<h1>
		  <?php echo sprintf(trans('text_stock_import')); ?>
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
		        <a href="product.php"><?php echo trans('text_purchase'); ?></a>  
		    </li>
			<li class="active">
			  	<?php echo sprintf(trans('text_stock_import_title')); ?>
			</li>
		</ol>
	</section>
	<!-- Content Header End -->

	<!-- Content Start -->
	<section class="content">

		<?php if(DEMO) : ?>
	    <div class="box">
	      <div class="box-body">
	        <div class="alert alert-info mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
	        </div>
	        <div class="alert alert-danger mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo trans('text_disabled_in_demo'); ?></p>
	        </div>
	      </div>
	    </div>
	    <?php endif; ?>
    
		<div class="row">
			<div class="col-sm-12">
				<div class="box box-no-border">

					<?php if ($message):?>
					<div class="alert alert-info mb-0 r-0">
						<?php echo $message ; ?>
					</div>
					<?php endif;?>

					<?php if (isset($error_message)): ?>
					<div class="alert alert-danger mb-0 r-0">
					    <p>
					    	<span class="fa fa-warning"></span> 
					    	<?php echo $error_message ; ?>
					    </p>
					</div>
					<?php elseif (isset($success_message)): ?>
					<div class="alert alert-success mb-0 r-0">
					    <p>
					    	<span class="fa fa-check"></span> 
					    	<?php echo $success_message ; ?>
					    </p>
					</div>
					<?php endif; ?>

					<form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
						<div class="box-body">

							<div class="well well-small">
								<div class="text-warning">
									<div> <?php echo trans('text_product_stock_instruction'); ?>
									</div>
								</div>
							</div>

							<div class="form-group">
								<label for="filename" class="col-sm-3 control-label">
						    		<?php echo trans('text_export_product_of'); ?>
						    	</label>
								<div class="col-sm-5">
								    <select onchange="this.options[this.selectedIndex].value && (window.location = 'import_stock.php?action_type=export&sup_id='+this.options[this.selectedIndex].value);" class="form-control noselect2" name="sup_id">
								    	<option value=""><?php echo trans('text_select');?></option>
								    	<?php foreach (get_suppliers() as $supplier):?>
								    		<option value="<?php echo $supplier['sup_id'];?>"><?php echo $supplier['sup_name'];?></option>
								    	<?php endforeach;?>
								    </select>
							 	</div>
							</div>
<hr>
<br>
						  	<div class="form-group">
						    	<label for="filename" class="col-sm-3 control-label">
						    		<?php echo trans('text_select_csv_file'); ?>
						    	</label>
						        <div class="col-sm-5">	            
									<input type="file" class="form-control" name="filename" id="filename" accept=".csv" required>
						        </div>
						 	</div>
						 	<br>
						    <div class="form-group">
						        <div class="col-sm-5 col-sm-offset-3">
							        <button type="submit" class="btn btn-block btn-success" name="submit">
							        	<span class="fa fa-fw fa-upload"></span> 
							          	<?php echo trans('button_import'); ?>
							        </button>
						        </div>
						    </div>
						</div>
				  	</form>
				</div>
			</div>
		</div>
	</section>
	<!-- Content End -->

</div>
<!-- Content Wrapper End -->

<?php include ("footer.php"); ?>