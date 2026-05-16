<?php 
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo trans('text_receipt_preview'); ?></title>
    <link type="text/css" href="../assets/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link type="text/css" href="../assets/itsolution24/cssmin/main.css" rel="stylesheet">
	<style>
		<?php 
		$template_id = $request->get['template_id'];
		$css = get_the_postemplate($template_id,'template_css');
		echo html_entity_decode($css ? $css : '');
		?>
	</style>
</head>
<body>
<?php
if (!1) {
	$statement = $db->prepare("SELECT `invoice_id` FROM `selling_info` WHERE `store_id` = ? AND `status` = ? ORDER BY `info_id` DESC");
	$statement->execute(array(store_id(), 1));
	$row = $statement->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$data = get_postemplate_empty_data();
	} else {
		$data = get_postemplate_data($row['invoice_id']);
	}
} else {
	$data = get_postemplate_empty_data();
}
include DIR_SRC.'parser/lex/lib/Lex/ArrayableInterface.php';
include DIR_SRC.'parser/lex/lib/Lex/ArrayableObjectExample.php';
include DIR_SRC.'parser/lex/lib/Lex/Parser.php';
include DIR_SRC.'parser/lex/lib/Lex/ParsingException.php';
$parser = new Lex\Parser();
$template_content = get_the_postemplate($template_id,'template_content');
$template = html_entity_decode($template_content ? $template_content : '');
echo $parser->parse($template, $data);
?>
<br>
<div class="text-center mb-20">
	<a href="receipt_template.php?template_id=<?php echo $template_id;?>">&larr; <?php echo trans('button_back');?></a>
</div>
</body>
</html>