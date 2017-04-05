<?php
$code = "";
if (isset($_GET['code'])) {
    $code = $_GET['code'];
}
$files = glob("xhtml/${code}*.xhtml");
if (count($files) > 0) {
    preg_match("/xhtml\/(.*)\.xhtml/", $files[0], $m);
    $code = $m[1];
} else {
    $files = glob("xhtml/*.xhtml");
    if (count($files) > 0) {
        preg_match("/xhtml\/(.*)\.xhtml/", $files[0], $m);
        $code = $m[1];
    } else {
        $code = "";
    }
}
$options = array();
$files = glob("xhtml/*.xhtml");
foreach ($files as $xhtml) {
    $basename = basename($xhtml, ".xhtml");
    if ($basename == $code) {
        $options[$basename] = '<option value="' . $basename . '" selected="selected">' . $basename . '</option>';
    } else {
        $options[$basename] = '<option value="' . $basename . '">' . $basename . '</option>';
    }
}
ksort($options, SORT_REGULAR);
$options = implode('', array_values($options));
?>
 
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PDF2XHTML Viewer</title>
  <link href="css/viewer.css" rel="stylesheet">
  <script src="js/jquery-1.11.3.min.js"></script>
  <script src="js/viewer.js"></script>
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
      <input type="button" id="prev_page_button" value="&lt;" />
      <input type="button" id="next_page_button" value="&gt;" />
    </div>

    <div id="container">
    <div class="vtop xhtml">
      <iframe id="iframe_xhtml"></iframe>
    </div>
    <div class="vtop pdf">
      <div id="paper">
	<img id="paper_image"></img>
      </div>
    </div>
	<div id="page_number">p.1</div>
    </div>
  </div>

</body>
</html>
