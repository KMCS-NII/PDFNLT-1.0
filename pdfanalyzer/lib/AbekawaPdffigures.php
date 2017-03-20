<?php

/**
 * @file AbekawaPdffigures.php
 * 画像認識手法で抽出した図表領域の情報を取得する
 */

class AbekawaPdffigures
{

  /**
   * 処理結果の JSON ファイルを読み込み
   * - 図表番号の付与
   * - bbox の DPI 変換
   * を行った結果を返す
   **/
  static function get($pdfpath, $json_dir, $dpi = 100) {

    // PDF に対応する JSON ファイルを読み込む
    $basename = basename($pdfpath, ".pdf");
    $jsonfile = $json_dir . $basename . '.json';
    if (!is_readable($jsonfile)) {
      throw new RuntimeException("File '{$jsonfile}' cannot be found.");
    }
    $content = file_get_contents($jsonfile);
    $pdffigures = json_decode($content, true);
    
    // 図と表に番号を振る
    $ifigure = 1;
    $itable  = 1;
    for ($i = 0; $i < count($pdffigures); $i++) {
      $fig = $pdffigures[$i];
      switch ($fig['Type']) {
      case 'Figure':
        if (isset($pdffigures[$i]['Number'])) {
          $ifigure = $pdffigures[$i]['Number'];
        } else {
          $pdffigures[$i]['Number'] = $ifigure;
        }
        $ifigure++;
        break;
      case 'Table':
        if (isset($pdffigures[$i]['Number'])) {
          $itable = $pdffigures[$i]['Number'];
        } else {
          $pdffigures[$i]['Number'] = $itable;
        }
        $itable++;
        break;
      }
      // DPI を変換
      if (isset($pdffigure[$i]['DPI'])) {
        $data_dpi = $pdffigures[$i]['DPI'];
      } else {
        $data_dpi = 100;  // default
      }
      if ($data_dpi != $dpi) {
        $rate = $dpi / $data_dpi;
        for ($j = 0; $j < 4; $j++) {
          $pdffigures[$i]['ImageBB'][$j] *= $rate;
        }
        $pdffigures[$i]['DPI'] = $dpi;
      }
    }
    return $pdffigures;
  }

}

// main
if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {

  if (count($argv) < 2) {
    echo "Usage: php {$argv[0]} <pdf-filepath>\n";
    echo "Note: The output will be output to the stdout.\n";
    die();
  }

  $pdffigures = AbekawaPdffigures::get($argv[1], "abekawa_json/");
  echo json_encode(
    $pdffigures,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK
  );

}
