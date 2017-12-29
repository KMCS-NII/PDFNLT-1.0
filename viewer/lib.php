<?php

/**
 * Read configuration from file "config.txt"
 */
function read_config() {
    $config = array();
    $fp = fopen(dirname(__FILE__) . "/config.txt", "r");
    if (!$fp) {
        echo <<<_HTML_

<html>
<head>
    <title>Error</title>
</head>
<body>
    <h1>Error</h1>
    <p>cannot read \"config.txt\"</p>
</body>
</html>

_HTML_;

        exit(1);
    } else {
        while ($line = fgets($fp)) {
            if (preg_match('/^([^#][^\s]+)\s+([^\s]+)/', $line, $m)) {
                $key = $m[1];
                $value = $m[2];
                $config[$key] = $value;
            }
        }
    }
    fclose($fp);
    return $config;
}

function get_base_dir($config) {
    if (!isset($config['basedir'])) {
        return dirname(__FILE__) . '/';
    }
    $basedir = $config['basedir'];
    if (!preg_match('/\/$/', $basedir)) {
        $basedir .= '/';
    }
    return $basedir;
}

function get_training_dir($config) {
    if (!isset($config['training_dir'])) {
        return get_base_dir($config) . 'train/';
    }
    if (!preg_match('/\/$/', $config['training_dir'])) {
        return $config['training_dir'] . '/';
    }
    return $config['training_dir'];
}

function get_pdf_dir($config) {
    if (!isset($config['pdf_dir'])) {
        return get_base_dir($config) . 'pdf/';
    }
    if (!preg_match('/\/$/', $config['pdf_dir'])) {
        return $config['pdf_dir'] . '/';
    }
    return $config['pdf_dir'];
}

function get_xhtml_dir($config) {
    if (!isset($config['xhtml_dir'])) {
        return get_base_dir($config) . 'xhtml/';
    }
    if (!preg_match('/\/$/', $config['xhtml_dir'])) {
        return $config['xhtml_dir'] . '/';
    }
    return $config['xhtml_dir'];
}

function get_pdfanalyzer($config) {
    if (!isset($config['pdfanalyzer'])) {
        return get_base_dir($config) . 'pdfanalyze.php';
    }
    return $config['pdfanalyzer'];
}

function get_options($config) {
    if (!isset($config['options'])) {
        return "--with-image --with-wordtag";
    }
    return $config['options'];
}

/**
 * Update XHTML from the posted labels
 */
function update_xhtml($paper, $labels, $config) {
    $labels = json_decode($labels);
    $target = get_training_dir($config) . $paper . '.csv';
    debug_log(sprintf("target: %s\n", $target));

    if (!is_writable($target)) {
        debug_log(sprintf("target file is not writable: %s", $target));
        exit(1); // error exit
    }
  
    $text = trim(file_get_contents($target));
    $lines = array_map(
        function($row) {
            return explode("\t", $row);
        },
        explode("\n", $text)
    );
    foreach ($labels as $index => $label) {
        $lines[$index][0] = $label;
    }
  
    $text = implode("", array_map(
        function($row) {
            return implode("\t", $row) . "\n";
        }, $lines)
    );
  
    file_put_contents($target, $text);
    debug_log(sprintf("target file is updated: %s", $target));
    $pdfanalyzer = get_pdfanalyzer($config);
    $basedir = get_base_dir($config);
    $options = get_options($config);

    $cmd = "php ${pdfanalyzer} -c update_xhtml --base-dir ${basedir} ${options} ${target}";
    debug_log(sprintf("Executing command: '%s'", $cmd));
    exec($cmd, $output, $retval);
    debug_log(sprintf("Retval : '%s'", $retval));
    exit($retval);
}

/**
 * Get code
 */
function get_xhtml_code($config) {
    return get_code(get_xhtml_dir($config), ".xhtml");
}

function get_training_code($config) {
    return get_code(get_training_dir($config), ".csv");
}

function get_code($dir, $ext) {
    $code = "";
    if (isset($_GET['code'])) {
        $code = $_GET['code'];
    }
    $files = glob($dir . $code . "*" . $ext);
    if (count($files) > 0) {
        $code = basename($files[0], $ext);
    } else {
        $files = glob($dir . "*" . $ext);
        if (count($files) > 0) {
            $code = basename($files[0], $ext);
        } else {
            $code = "";
        }
    }
    return $code;
}

/**
 * Get filelist options (HTML style)
 */
function get_xhtml_options($code, $config) {
    return get_file_options($code, get_xhtml_dir($config), ".xhtml");
}

function get_training_options($code, $config) {
    return get_file_options($code, get_training_dir($config), ".csv");
}

function get_file_options($code, $dir, $ext) {
    $options = array();
    $files = glob($dir . "*" . $ext);
    foreach ($files as $file) {
        $basename = basename($file, $ext);
        if ($basename == $code) {
            $options[$basename] = '<option value="' . $basename . '" selected="selected">' . $basename . '</option>';
        } else {
            $options[$basename] = '<option value="' . $basename . '">' . $basename . '</option>';
        }
    }
    ksort($options, SORT_REGULAR);
    $options = implode('', array_values($options));
    return $options;
}

/**
 * Debug log
 */
function debug_log($msg) {
    if (false) { // set true for logging debug information
        $fp = fopen("/tmp/line_checker.log", "a");
        chmod("/tmp/line_checker.log", 0666);
        fprintf($fp, "%s\t basedir: %s\n", strftime('%y-%m-%d %H:%M:%S'), $msg);
        fclose($fp);
    }
}

function errorHandler($errorNumber, $errorString, $errorFile, $errorLine, $errorText) {
    debug_log(sprintf("[%d] %s\n", $errorNumber, $errorString));
}
set_error_handler('errorHandler');
