<?php

if (!isset($argv)) {
    echo "This program must be executed from command line.\n";
    die();
}

require_once(dirname(__FILE__).'/lib/PdfAnalyzer.php');
require_once(dirname(__FILE__) . '/lib/getopts.php');

ini_set('memory_limit', -1);

$opts = getopts(array(
    'c' => array('switch' => array('c', 'command'), 'type' => GETOPT_VAL),
    'h' => array('switch' => array('h', 'help'), 'type' => GETOPT_SWITCH),
    'm' => array('switch' => array('m', 'model'), 'type' => GETOPT_VAL),
    'b' => array('switch' => array('b', 'base-dir'), 'type' => GETOPT_VAL),
    'p' => array('switch' => array('p', 'pdf-dir'), 'type' => GETOPT_VAL),
    'f' => array('switch' => array('f', 'figure-dir'), 'type' => GETOPT_VAL),
    'a' => array('switch' => array('a', 'annotation-dir'), 'type' => GETOPT_VAL),
    't' => array('switch' => array('t', 'training-dir'), 'type' => GETOPT_VAL),
    'x' => array('switch' => array('x', 'xhtml-dir'), 'type' => GETOPT_VAL),
    'A' => array('switch' => array('A', 'all'), 'type' => GETOPT_SWITCH),
    'i' => array('switch' => array('i', 'with-image'), 'type' => GETOPT_SWITCH),
    'u' => array('switch' => array('u', 'use-alter-image'), 'type' => GETOPT_VAL),
    'M' => array('switch' => array('M', 'with-mecab'), 'type' => GETOPT_SWITCH),
    'w' => array('switch' => array('w', 'with-wordtag'), 'type' => GETOPT_SWITCH),
));

if ($opts['h'] || !$opts['c']) { //count($opts['cmdline']) == 0) {
    echo <<<_USAGE_
Usage: php {$argv[0]} [options] -c <command> [file 1] [...]

Options:

  --model <model file> (default: 'paper.model')

  --base-dir <BASE directory> (default: './')

  --pdf-dir <PDF directory> (default: './pdf/')

  --figure-dir <pdffigure-json directory> (default: null)

  --annotation-dir <annotation directory> (default: './anno/')

  --training-dir <training directory> (default: './train/')

  --xhtml-dir <XHTML directory> (default: './xhtml/')

  -A, --all
     Instead of specifying PDFs, use all files under the directory.

  --help  Show this help

  (Options for 'generate_xhtml' command only)
  --with-image (default:0)
     Generate image under the 'xhtml-dir'/image/ for figures and tables

  --use-alter-image (default:'')
     Generate alternate image for sections.

     ex. '--use-alter-image Equation,Theorem'
       The sections titled 'Equation' and 'Theorem' are shown as images.

  --with-mecab (default:0)
     Use MeCab to extract Japanese words instead of characters.

  --with-wordtag (default:0)
     Add '<span class="word"...>' tag for each word.

Command:

  generate_annotation
     Generate annotation file(s) from PDF(s) using the model file.
     Annotation file(s) will be generated under the 'annotation-dir'.
     When 'figure-dir' is set, use json files under it instead of
     calling 'pdffigures' program.
     
  update_training
     Update training file(s) from PDF(s) and annotation file(s).
     Training file(s) will be generated under the 'training-dir'.
     When 'figure-dir' is set, use json files under it instead of
     calling 'pdffigures' program.
     When annotation file(s) are not available, this command 
     generates annotation file(s) from the model file temporary.
     
  update_model
     Update the model file from training files.
     All files under the 'training-dir' will be used.
     
  generate_xhtml
     Generate XHTML file(s) from PDF(s) using the model file.
     XHTML file(s) will be generated under the 'xhtml-dir'.

  update_xhtml
     Generate XHTML file(s) from PDF(s) using the training file.
     XHTML file(s) will be generated under the 'xhtml-dir'.

  generate_labels
     Generate 'labels.json' file from the all training files
     under the 'training-dir'.
     If the "labels.json" file exists in the directory,
     labels not included in that file will not be used for
     'update training'.
     
_USAGE_;

    die();
}

// デフォルト値とオプションパラメータ
$base_dir       = $opts['b'] ? $opts['b'] : './';
$modelfile      = $opts['m'] ? $opts['m'] : null;
$pdf_dir        = $opts['p'] ? $opts['p'] : null;
$figure_dir     = $opts['f'] ? $opts['f'] : null;
$annotation_dir = $opts['a'] ? $opts['a'] : null;
$training_dir   = $opts['t'] ? $opts['t'] : null;
$xhtml_dir      = $opts['x'] ? $opts['x'] : null;

$p = new PdfAnalyzer($base_dir);
if ($modelfile) $p->setModelFile($modelfile);
if ($pdf_dir) $p->setPdfDir($pdf_dir);
if ($figure_dir) $p->setFigureDir($figure_dir);
if ($annotation_dir) $p->setAnnotationDir($annotation_dir);
if ($training_dir) $p->setTrainingDir($training_dir);
if ($xhtml_dir) $p->setXhtmlDir($xhtml_dir);
$p->setCutImage($opts['i']);
$p->setUseAltImage($opts['u']);
$p->setUseMecab($opts['M']);
$p->setUseWordtag($opts['w']);

// 対象ファイル
$files = $opts['cmdline'];
if (count($opts['cmdline']) == 0 && $opts['A']) {
    $files = array('*');
}

// ディスパッチ
switch ($opts['c']) {
case 'generate_annotation':
    if (count($files) == 0) {
        echo "Note: Please specify target PDF(s), or set '--all' option.\n";
    }
    $p->pdf2anno($files);
    break;
case 'update_training':
    if (count($files) == 0) {
        echo "Note: Please specify target PDF(s), or set '--all' option.\n";
    }
    $p->updateTrain($files);
    break;
case 'update_model':
    $p->updateModel();
    break;
case 'generate_xhtml':
    if (count($files) == 0) {
        echo "Note: Please specify target PDF(s), or set '--all' option.\n";
    }
    $p->pdf2xhtml($files);
    break;
case 'update_xhtml':
    if (count($files) == 0) {
        echo "Note: Please specify target CSV(s), or set '--all' option.\n";
    }
    $p->csv2xhtml($files);
    break;
case 'generate_labels':
    $p->generateLabels();
    break;
default:
    echo "Unknown command '", $opts['c'], "'\n";
}
