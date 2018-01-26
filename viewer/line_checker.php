<?php
require_once(dirname(__FILE__) . "/lib.php");

// Read "config.txt"
$config = read_config();
$basedir = get_base_dir($config);

debug_log("line_checker.php: ${basedir}");

if (isset($_POST['labels'])) {
    debug_log(sprintf("calling update_xhtml(), paper: %s", $_POST['paper']));
    update_xhtml($_POST['paper'], $_POST['labels'], $config);
    exit(0);
}
$code = get_training_code($config);
$loc = $_GET['loc'];
$loc = $loc ? array_map('floatval', explode(',', $loc)) : null;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PDF2XHTML Training-data Viewer</title>
  <link href="css/viewer.css" rel="stylesheet">
  <script src="js/jquery-1.11.3.min.js"></script>
  <script src="js/line_checker.js"></script>
  <script src="js/encoding.js"></script>
  <script type="text/javascript">
    var basedir = "<?php echo $basedir; ?>";
    var default_paper = "<?php echo $code; ?>";
    var loc = <?php echo json_encode($loc); ?>;
  </script>
</head>

<body>
  <div class="fullheight">
    <div id="menu">
      <input type="text" id="paper_select" list="paper_list" autocomplete="off" placeholder="Paper Code" />
      <datalist id="paper_list">
      </datalist>
      <input type="button" id="layout_select_button" value="Layout" />
      |<span>
	  <input type="button" id="line_download_button" value="Download" />
	  <input type="button" id="line_save_button" value="Save" style="display:none;" />
	  <input type="button" id="line_load_button" value="Load" style="display:none;" />
	  <input type="button" id="line_update_button" value="Update" />
      </span>
      |<span id="disp_error">Error:0</span>
    </div>

    <div id="container">
    <div class="vtop line">
      <div id="paper_line">
	<table id="table_line">
	  <thead>
	    <tr><th>#</th><th>Label</th><th>Text</th></tr>
	  </thead>
	  <tbody>
	    <tr><td>1</td><td></td><td></td></tr>
	  </tbody>
	</table>
      </div>
    </div>
    <div class="vtop pdf">
      <div id="paper">
        <img id="paper_image">
        </img>
      </div>
    </div>
      <div id="page_number">
	<input type="button" id="prev_page_button" value="&lt;" />
        <span id="page">p.1</span>
	<input type="button" id="next_page_button" value="&gt;" />
      </div>
    </div>
  </div>

</body>
</html>
