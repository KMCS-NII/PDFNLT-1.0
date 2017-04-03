<?php
$code = "";
if (isset($_GET['code'])) {
    $code = $_GET['code'];
}
$files = glob("train/${code}*.csv");
if (count($files) > 0) {
    preg_match("/train\/(.*)\.csv/", $files[0], $m);
    $code = $m[1];
} else {
    $files = glob("train/*.csv");
    if (count($files) > 0) {
        preg_match("/train\/(.*)\.csv/", $files[0], $m);
        $code = $m[1];
    } else {
        $code = "";
    }
}
$options = array();
$files = glob("train/*.csv");
foreach ($files as $csv) {
    $basename = basename($csv, ".csv");
    if ($basename == $code) {
        $options[$basename] = '<option value="' . $basename . '" selected="selected">' . $basename . '</option>';
    } else {
        $options[$basename] = '<option value="' . $basename . '">' . $basename . '</option>';
    }
}
ksort($options, SORT_NUMERIC);
$options = implode('', array_values($options));
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
  <script type="text/javascript">
    var default_paper = "<?php echo $code; ?>";
  </script>
</head>

<body>
  <div class="fullheight">
    <div id="menu">
      <select id="paper_select" size="1">
        <?php echo $options; ?>
      </select>
      <input type="text" id="paper_select_input" size="10" placeholder="Paper Code" />
      <!--input type="button" id="paper_select_input_button" value="Go" /-->
      &nbsp;
      <input type="button" id="layout_select_button" value="Layout" />
    </div>

    <div id="container">
    <div class="vtop line">
      <div id="paper_line">
	<table id="table_line">
	  <thead>
	    <tr><th>#</th><th>ラベル</th><th>テキスト</th></tr>
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
    </div>
  </div>

</body>
</html>
