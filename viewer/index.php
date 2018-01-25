<?php
require_once(dirname(__FILE__) . "/lib.php");

// Read "config.txt"
$config = read_config();
$basedir = get_base_dir($config);

debug_log("index.php: ${basedir}");

$code = get_xhtml_code($config);
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
      <input type="text" id="paper_select" list="paper_list" placeholder="Paper Code" />
      <datalist id="paper_list">
      </datalist>
      <input type="button" id="layout_select_button" value="Layout" />
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
      <div id="page_number">
        <input type="button" id="prev_page_button" value="&lt;" />
        <span id="page">p.1</span>
        <input type="button" id="next_page_button" value="&gt;" />
      </div>
    </div>
  </div>

</body>
</html>
