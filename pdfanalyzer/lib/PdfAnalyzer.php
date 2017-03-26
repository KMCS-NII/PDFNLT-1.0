<?php
require_once(dirname(__FILE__).'/LayoutAnalyzer.php');
require_once(dirname(__FILE__).'/CRFSuiteLib.php');
require_once(dirname(__FILE__).'/AbekawaPdffigures.php');

/*
 * PDF の解析を行う
 */

class PdfAnalyzer
{
    const UNLABELLED_LINE = '#';
  
    function __construct($modelfile = 'paper.model') {
        $this->modelfile = $modelfile;
        $this->pspell_link = pspell_new("en");
        $this->keyreps = array(
            "/@/"=>'symbol-atmark',
            "/†/u"=>'symbol-dagger',
            "/‡/u"=>'symbol-dagger',
            "/^Abstract/"=>'string-abstract',
            "/^概要/u"=>'string-abstract',
            "/References?/"=>'string-reference',
            "/^参考文献/u"=>'string-reference',
            "/Acknowle?dg/"=>'string-acknowledgement',
            "/^謝辞/u"=>'string-acknowledgement',
            "/^Keywords?/"=>'string-keyword',
            "/^キーワード/u"=>'string-keyword',
            "/Appendix/"=>'string-appendix',
            "/^付録/u"=>'string-appendix',
            "/^\d{1,2}+ /"=>'numbered-heading1',
            "/^• /"=>'itemization',
            "/^\d{1,2}\. /"=>'itemization',
            "/^[a-z] /"=>'itemization',
            "/^\([a-z]\) /"=>'itemization',
            "/^\([0-9]{1,2}\) /"=>'itemization',
            "/^\d+\.\d+ /"=>'numbered-heading2',
            "/^\d+\.\d+\.\d+ /"=>'numbered-heading3',
            "/^__Table \d+__/"=>'table-area',
            "/^Table/"=>'string-table',
            "/^表/u"=>'string-table',
            "/^__Figure \d+__/"=>'figure-area',
            "/^Figure/"=>'string-figure',
            "/^図/u"=>'string-figure',
            "/(19|20)\d{2}/"=>'year',
            "/^\d+$/"=>'numeric-only',
            "/^[A-Z]/"=>'headchar-capital',
            "/^[a-z]/"=>'headchar-lower',
            "/^<sup>/"=>'headchar-super',
            "/[\.．。]$/u"=>'tailchar-period',
            "/[\-－]$/u"=>'tailchar-hiphen',
            "/:$/u"=>'tailchar-colon',
            "/;$/u"=>'tailchar-semicolon',
            "/,$/u"=>'tailchar-comma',
            "/^\[\d+\]/"=>'reference-number',
        );
        $this->la = new LayoutAnalyzer();
        $this->crf = new CRFSuiteLib($modelfile);
        $this->cutimage = false;
        $this->pdf_dir = 'pdf/';
        $this->annotation_dir = 'anno/';
        $this->training_dir = 'train/';
        $this->xhtml_dir = 'xhtml/';
        // 日本語の場合、分かち書きに MeCab を利用する
        $this->have_mecab = class_exists("MeCab_Tagger");
        $this->__reset();
    }

    private function __reset() {
        $this->textboxes = array();
		$this->sections = array();
        $this->line_space = 0;
        $this->font_classes = array();
    }

    function __destruct() {
    }

    /**
     * XHTML 作成の際に画像も生成するかどうかのフラグをセット
     * @param $f  true の場合、画像を生成する
     */
    public function setCutImage($f = true) {
        $this->cutimage = $f;
    }

    /**
     * 各種ディレクトリの setter, getter
     */
    public function setPdfDir($d) {
        $this->pdf_dir = $d;
    }
    public function getPdfDir() {
        return $this->pdf_dir;
    }

    public function setFigureDir($d) {
        $this->figure_dir = $d;
    }
    public function getFigureDir() {
        return $this->figure_dir;
    }

    public function setAnnotationDir($d) {
        $this->annotation_dir = $d;
    }
    public function getAnnotationDir() {
        return $this->annotation_dir;
    }

    public function setTrainingDir($d) {
        $this->training_dir = $d;
    }
    public function getTrainingDir() {
        return $this->training_dir;
    }

    public function setXhtmlDir($d) {
        $this->xhtml_dir = $d;
    }
    public function getXhtmlDir() {
        return $this->xhtml_dir;
    }

    /**
     * 単語配列を結合して文字列を作る
     * 日本語の場合は空白で区切らない
     * 英語の場合は半角空白文字で分割する
     * @param $words   結合したい語の配列
     * @return 結合した文字列
     */
    static public function mergeWords($words, $lang = 'auto') {
        $sentence = '';
        if (count($words) == 0) return $sentence;
    
        for ($i = 0; $i < count($words) - 1; $i++) {
            $sentence .= $words[$i];
            if (preg_match('/[a-zA-Z0-9]$/', $words[$i]) 
            && preg_match('/^[a-zA-Z0-9]/', $words[$i + 1])) {
                $sentence .= ' ';
            } else if (preg_match('/\.$/', $words[$i])
            && preg_match('/^[A-Z]/', $words[$i + 1])) {
                // ピリオドで終わって次の語の先頭が大文字
                $sentence .= ' ';
            }              
        }
        $sentence .= $words[count($words) - 1];
        return $sentence;
    }

    // コードを抽出
    public static function getCode($pdfpath) {
        $basename = basename($pdfpath, ".pdf");
        /*
          if (preg_match('/[A-Z](\d{2})\-(\d{4})/', $str, $m)) {
          return $m[0];
          }
        */
        return $basename;
        // return false;
    }

    /**
     * 画像へのパスを返す
     * @param $doc_id   文書ID, パスに含まれる
     * @param $is_url   XHTML 中に URL として埋め込む場合には true
     */
    public function getImageDir($doc_id, $is_url = false) {
        if ($is_url) {
            // URL に埋め込む場合は xhtml directory からの相対パス
            $imgdir = sprintf("images/%s/", $doc_id);
        } else {
            // 画像ファイルパスを生成する場合は xhtml directory を含むパス
            $imgdir = sprintf("%simages/%s/", $this->xhtml_dir, $doc_id);
            @mkdir($imgdir, 0755, true);
        }
        return $imgdir;
    }

    /**
     * CRFsuite 用の素性を取得する
     * @param 無し （$this->la (Analyzer) の結果を利用する）
     * @return crfsuite 用の素性ファイル
     *         各行３列のタブ区切り文字列の１次元配列
     *         行番号\t表記文字列\t素性文字列（空白区切り）
     */
    public function getCRFfeatures() {
        $prev = null;
        $pages = $this->la->getPages();
        $line_height = $this->la->getLineHeight();
        $line_space  = $this->la->getLineSpace();
        $dpi = $this->la->getDpi();
        $lines = $this->la->getLines();
    
        $r1 = array();
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $page = $line[6]; // ページ番号、最初は0
            $indent = $pages[$page]['indent'];
            $xpos = floor(($line[1] - $pages[$page]['text_area'][0]) * 10 / ($pages[$page]['text_area'][2] - $pages[$page]['text_area'][0]));
            $ypos = floor(($line[2] - $pages[$page]['text_area'][1]) * 10 / ($pages[$page]['text_area'][3] - $pages[$page]['text_area'][1]));
            $features = array("page"=>$page, "xpos"=>$xpos, "ypos"=>$ypos);

            // 段組み
            $column = $line[7]; // single, left, right
            $features["column"]= $column;

            // 許容誤差
            $margin = 0.02 * $dpi; // 単語の位置 = 0.02 inch
            $line_margin = 0.05 * $dpi; // 行の位置 = 0.05 inch

            // 行内の位置を判定
            $c_line = ($line[1] + $line[3]) / 2.0;
            switch($column) {
            case 'left':
                $l = $indent[0];
                $r = $indent[1];
                break;
            case 'right':
                $l = $indent[2];
                $r = $indent[3];
                break;
            default:
                $l = $indent[0];
                $r = $indent[3];
                break;
            }
            if (abs($l - $line[1]) < $margin && abs($r - $line[3]) < $margin) {
                // 両端がカラム幅にそろっている
                $features['align'] = 'justified';
            } else if (abs(($l + $r) / 2.0 - $c_line) < $margin) { // 中央でそろっている
                $features['align'] = 'centered';
            }
            if ($l - $margin > $line[1]) { // 左端にはみ出している
                $features['leftpos'] = 'over';
            } else if ($l + $margin < $line[1]) { // 左側に余白がある
                $features['leftpos'] = 'indent';
            }
            if ($r + $margin < $line[3]) { // 右側にはみ出している
                $features['rightpos'] = 'over';
            } else if ($r - $margin > $line[3]) { // 右側に余白がある
                $features['rightpos'] = 'indent';
            }

            // 行高さ
            $h = $line[4] - $line[2];
            $v = 'normal';
            if ($h > $line_height * 3.0) {
                $v = 'box';
            } else if ($h > $line_height * 1.1) {
                $v = 'highest';
            } else if ($h > $line_height * 1.03) {
                $v = 'higher';
            } else if ($h < $line_height * 0.92) {
                $v = 'lowest';
            } else if ($h < $line_height * 0.98) {
                $v = 'lower';
            }
            $features['lineheight'] = $v;

            // 先頭行をチェック
            $v = null;
            if (is_null($prev)) {
                $v = 'top';
            } else {
                $prev_features = $r1[$i - 1]['features'];
                // ページや段組みの変更
                if ($prev[6] != $page) {
                    $v = 'page-top';
                } else if ($prev[7] != $line[7]) {
                    $v = 'column-top';
                }
            }
            if ($v) {
                $features['linepos'] = $v;
            } else {
                // 前の行との左端相対位置
                $v = null;
                if ($line[1] > $prev[1] + $line_margin) {
                    $v = 'indented';
                } else if ($line[1] < $prev[1] - $line_margin) {
                    $v = 'hanged';
                } else if ($prev[1] - $line_margin < $line[1] && $prev[1] + $line_margin > $line[1]) {
                    $v = 'aligned';
                }
                if ($v) {
                    $features['relindent'] = $v;
                }

                // 前の行との右端相対位置
                $v = null;
                if ($line[3] < $prev[3] - $line_margin) {
                    $v = 'shorter';
                } else if ($line[3] > $prev[3] + $line_margin) {
                    $v = 'longer';
                }
                if ($v) {
                    $features['rellength'] = $v;
                }

                // 行間
                $v = 'normal';
                if ($line[2] - $prev[4] > ($line[4] - $line[2]) + 2 * $line_space) {
                    // 二重改行（現在行基準）
                    $v = 'double';
                }
                if ($line[2] - $prev[4] > $line_space + 0.5 * $dpi) {
                    // 行間が通常より 0.5インチ以上広い
                    $v = 'wider';
                }
                if ($line[2] - $prev[4] > $this->line_space + 0.1 * $dpi) {
                    // printf("%d: '%s'(%d), '%s'(%d)\n", $line[2] - $prev[4], $line[5], $line[2], $prev[5], $prev[4]);
                    // 行間が通常より 0.1インチ以上広い
                    $v = 'wide';
                }
                if (abs($line[4] - $prev[4]) < 0.01 * $dpi) {
                    // 改行していない
                    $v = 'none';
                }
                $features['linefeed'] = $v;
            }

            // 特別な文字や単語
            foreach ($this->keyreps as $p=>$r) {
                if (preg_match($p, $line[5], $m)) {
                    $features[$r] = 't';
                }
            }

            // 行の先頭、5文字または最初の空白まで
            if (preg_match('/^\S{1,5}/u', $line[5], $m)) {
                $features['text'] = $m[0];
            } else {
                $features['text'] = 'SP';
            }

            $r1[$i] = array("words"=>$line[5], "features"=>$features);
            $prev = $line;
        }

        // 結果結合
        $r2 = array();
        for ($i = 0; $i < count($r1) ; $i++) {
            $r = $r1[$i];
            $feature = '';
            foreach ($r['features'] as $k => $v) {
                $feature .= $k . '=' . $v . ' ';
            }
            $r2 []= sprintf("%d\t%s\t%s", $i, $r['words'], $feature);
        }
        return $r2;
    }

    public function processFile($pdf_filename) {
        if (!is_readable($pdf_filename)) {
            throw new RuntimeException("File '{$pdf_filename}' is not readable.");
        }
        // pdffigures の代わりに処理済みデータを読む
        // $tmp = tempnam('/tmp', 'pdffigures');
        // $cmd = sprintf("pdffigures -j %s %s", $tmp, $pdf_filename);
        // if (isset($GLOBALS['debug']) && $GLOBALS['debug']) echo "Executing '${cmd}'\n";
        // $output = array();
        // exec($cmd, $output);
        $basename = basename($pdf_filename, ".pdf");
        $jsonfile = 'abekawa_json/' . $basename . '.json';
        if (!is_readable($jsonfile)) {
            throw new RuntimeException("File '{$jsonfile}' cannot be found.");
        }
        $content = file_get_contents($jsonfile);
        $this->pdffigures = json_decode($content, true);
        // 図と表に番号を振る
        $ifigure = 1;
        $itable  = 1;
        for ($i = 0; $i < count($this->pdffigures); $i++) {
            $fig = $this->pdffigures[$i];
            switch ($fig['Type']) {
            case 'Figure':
                $this->pdffigures[$i]['Number'] = $ifigure;
                $ifigure++;
                break;
            case 'Table':
                $this->pdffigures[$i]['Number'] = $itable;
                $itable++;
                break;
            }
            // BBOX の dpi を 72 -> 100 に変換
            $this->pdffigures[$i]['ImageBB'] = array(
                $fig['ImageBB'][0] * 100 / 72,
                $fig['ImageBB'][1] * 100 / 72,
                $fig['ImageBB'][2] * 100 / 72,
                $fig['ImageBB'][3] * 100 / 72,
            );
        }
    
        // pdftotext bbox
        $cmd = sprintf("pdftotext -r 100 -bbox %s -", $pdf_filename);
        if (isset($GLOBALS['debug']) && $GLOBALS['debug']) echo "Executing '${cmd}'\n";
        $ouput = array();
        exec($cmd, $output);
        $this->bbox = implode("\n", $output);
        file_put_contents("pdftotext_output.txt", $output);
    
        $this->la->setLines($this->bbox, $this->pdffigures);
        return $this->la->getLines();
    }

    // ページ画像を生成
    public function pageImages($pdf_filename) {
        $doc_id = self::getCode($pdf_filename);
        if (!$doc_id) {
            throw new RuntimeException("Code is not contained in '{$pdf_filename} '");
        }
        // Create Images
        $ppmroot = $this->getImageDir($doc_id);
        $ppmroot .= $doc_id;
        if (!file_exists($ppmroot.'-1.png') && !file_exists($ppmroot.'-01.png')) {
            // create PPM images
            $cmd = sprintf('pdftoppm -r 100 %s %s', $pdf_filename, $ppmroot);
            exec($cmd);

            // convert to PNG
            foreach (glob($ppmroot.'-*.ppm') as $ppm) {
                if (preg_match('/(.*)\-(\d+)\.ppm/', $ppm, $m)) {
                    $png = sprintf("%s-%02d.png", $m[1], $m[2]);
                }
                // $png = preg_replace('/.ppm$/', '.png', $ppm);
                $cmd = sprintf('pnmtopng %s > %s', $ppm, $png);
                exec($cmd);
                unlink($ppm);
            }
        }
    }

    // ページ内画像を切り出し
    public function cutImage($doc_id, $page, $bdr, $image_id) {
        $imgdir = $this->getImageDir($doc_id);
        // ページ画像
        $page_img = sprintf("%s%s-%02d.png", $imgdir, $doc_id, $page + 1); // $page は 0から、ファイル名は 1 から
        if (!file_exists($page_img)) {
            throw new RuntimeException("Page image '{$page_img}' is not exists");
        }
        $abs_bdr = $this->la->getAbsoluteBdr($bdr[0], $bdr[1], $bdr[2], $bdr[3], $page);
        // 出力先
        $content_img = sprintf("%s%s-%s.png", $imgdir, $doc_id, $image_id);
        $cmd = sprintf("convert -crop %sx%s+%s+%s %s %s", $abs_bdr[2] - $abs_bdr[0], $abs_bdr[3] - $abs_bdr[1], $abs_bdr[0], $abs_bdr[1], $page_img, $content_img);
        // 実行
        exec($cmd);
    }

    // CRFSuite でタグ付けされた結果を付与する
    private function __mergeTags($la_lines, &$tags) {
        $line_info = array();
        $textboxes = array();
        $n = 1; // ボックス番号
        $last_boxes = array(); // タイプ別の最終ボックス番号
        // タグが同じ部分を結合
        for ($i = 0; $i < count($la_lines);) {
            $line = $la_lines[$i];
            $label = $tags[$i];
            $continued = false;
            if (preg_match('/^([BIE])-(.*)/', $label, $m)) {
                if ($m[1] !== 'B') $continued = true; // 継続ブロック
                $label = $m[2];
            }
            for ($t = $i + 1; $t < count($la_lines); $t++) {
                if (($la_lines[$t][6] != $line[6])  // ページが変わった
                || ($la_lines[$t][7] != $line[7])) { // 段組みが変わった
                    break;
                }
                if ($tags[$t] == 'E-'.$label) {
                    $t++;
                    break; // 終了ラベル
                }
                if ($tags[$t] != $label && $tags[$t] != 'I-'.$label) break; // ラベルが変わった
            }
            // $i .. $t-1 を結合する
            $p = array('n'=>$n, 'text'=>'', 'line'=>array(), 'bdr'=>null, 'page'=>$line[6]);
            for ($j = $i; $j < $t; $j++) {
                $li = $la_lines[$j];
                if ($p['text'] != '') $p['text'] .= "\n";
                $p['text'] .= $li[5];
                // 行末のハイフネーション処理
                if ($j < $t - 1) {
                    if (preg_match('/^([A-Za-z]+)\-$/u', $li[0][count($li[0]) - 1][0], $m)
                    && preg_match('/(^[A-Za-z0-9]+)[\.\,]?$/u', $la_lines[$j + 1][0][0][0], $m2)) {
                        $candidate = $m[1].$m2[1];
                        if (pspell_check($this->pspell_link, $candidate)) {
                            $li[0][count($li[0]) - 1][0] = $m[1].$la_lines[$j + 1][0][0][0];
                            $la_lines[$j + 1][0][0][0] = '';
                            // $la_lines[$j + 1][0][0][8] = 'ss';
                        } else {
                            $p['text'] .= '-';
                        }
                    }
                }
                $p['line'][]= $li[0];
                // 領域の計算
                if (is_null($p['bdr'])) {
                    $p['bdr'] = array($li[1], $li[2], $li[3], $li[4]);
                } else {
                    $p['bdr'][0] = min($p['bdr'][0], $li[1]);
                    $p['bdr'][1] = min($p['bdr'][1], $li[2]);
                    $p['bdr'][2] = max($p['bdr'][2], $li[3]);
                    $p['bdr'][3] = max($p['bdr'][3], $li[4]);
                }
            }
            // ハイフネーションの置き換え処理
            $text = preg_replace('/(\s[A-Za-z\-]+)\-\n([A-Za-z0-9\-]+)(\s|\.|\,)/us', '${1}${2}${3}', $p['text']);
            // 文末結合処理
            $lines = explode("\n", $text);
            $p['text'] = self::mergeWords($lines);
            $i = $t;

            // パラグラフが図表かキャプションの場合、情報を追加する
            $pdffigures = $this->la->getPdfFigures();
            for ($j = 0; $j < count($pdffigures); $j++) {
                $fig = $pdffigures[$j];
                if ($fig['Page'] != $p['page'] + 1) continue;
                if ($fig['ImageBB'][0] <= $p['bdr'][2]
                && $fig['ImageBB'][1] <= $p['bdr'][3]
                && $fig['ImageBB'][2] >= $p['bdr'][0]
                && $fig['ImageBB'][3] >= $p['bdr'][1]) {
                    $p['fig'] = $fig['Type'].'_'.$fig['Number'];
                    break;
                }
                if ($fig['CaptionBB'][0] <= $p['bdr'][2]
                && $fig['CaptionBB'][1] <= $p['bdr'][3]
                && $fig['CaptionBB'][2] >= $p['bdr'][0]
                && $fig['CaptionBB'][3] >= $p['bdr'][1]) {
                    $p['caption'] = $fig['Type'].'_'.$fig['Number'];
                    break;
                }
            }
      
            // 登録
            $k = count($textboxes);
            if ($continued && isset($last_boxes[$label])) {
                // 継続ボックスなので継続元を見つける
                $p['continued_from'] = $last_boxes[$label];
                // 継続元に継続先の情報を追加する
                for ($i2 = count($textboxes) - 1; $i2 >= 0; $i2--) {
                    for ($j2 = count($textboxes[$i2]['paragraphs']) - 1; $j2 >= 0; $j2--) {
                        if ($textboxes[$i2]['paragraphs'][$j2]['n'] == $last_boxes[$label]) {
                            $textboxes[$i2]['paragraphs'][$j2]['continue_to'] = $n;
                        }
                    }
                }
            }
            if ($k > 0 && $textboxes[$k - 1]['boxType'] == $label) {
                // 最後尾のボックスにパラグラフとして追加
                $textboxes[$k - 1]['paragraphs'] []= $p;
            } else {
                // 新しいボックスとして追加
                $textboxes []= array('boxType'=>$label, 'paragraphs'=>array($p));
            }
            $last_boxes[$label] = $n;
            $n++;
        }

        return $textboxes;
    }

    // タグ付け済み結果にセクション構造を付与する
    private function __assignSections($boxes) {
        $sections = array(array('title'=>'meta', 'boxes'=>array()));
        $last_section = 0;
        foreach ($boxes as $box) {
            switch ($box['boxType']) {
            case 'Title':
            case 'Author':
            case 'Affiliation':
            case 'Address':
            case 'Email':
                //case 'Header':
                //case 'Footer':
                $sections[0]['boxes'] []= $box;
                break;
            default:
                if (preg_match('/(.*)Header$/', $box['boxType'], $m)) {
                    $last_section++;
                    // 新しいセクション
                    $sections[$last_section]
                        = array('title' => $m[1], 'boxes' => array());
                } else if ($last_section == 0) {
                    // セクションヘッダがないまま本文が始まった場合
                    ;
                } else {
                    $last_section++;
                    $sections[$last_section]
                        = array('title' => '(no title)', 'boxes' => array());
                }
                $sections[$last_section]['boxes'] []= $box;
            }
        }
        return $sections;
    }
  
    /**
     * セクション化済み結果を XHTML 形式に変換する
     * @param $pdfpath  PDF ファイルパス
     * @param $sections __assignSections の結果
     * @return xhtml テキスト
     * @sideeffect   cutimage が true の場合、PNG 画像も生成する
     */
    private function __toXhtml($pdfpath, $sections) {
        $doc_id = self::getCode($pdfpath);
        $this->n2id = array();
    
        $doctype = DOMImplementation::createDocumentType("html",
        "-//W3C//DTD XHTML 1.0 Strict//EN",
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd");

        $dom = DOMImplementation::createDocument(null, null, $doctype);
        // $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $html = $dom->createElement('html');
        $attr = $dom->createAttribute('xml:lang');
        $attr->value = 'en';
        $html->appendChild($attr);
    
        $dom->appendChild($html);

        // head
        $head = $dom->createElement('head');
    
        $meta = $dom->createElement('meta');
        $attr = $dom->createAttribute('charset');
        $attr->value = 'UTF-8';
        $meta->appendChild($attr);
        $head->appendChild($meta);

        // <link href="css/pdf2xhtml.css" rel="stylesheet" type="text/css">
        $link = $dom->createElement('link');
        $attr = $dom->createAttribute('href');
        $attr->value = 'css/pdf2xhtml.css';
        $link->appendChild($attr);
        $attr = $dom->createAttribute('rel');
        $attr->value = 'stylesheet';
        $link->appendChild($attr);
        $attr = $dom->createAttribute('type');
        $attr->value = 'text/css';
        $link->appendChild($attr);
        $head->appendChild($link);

        $title = $dom->createElement('title', htmlspecialchars($doc_id));
        $head->appendChild($title);

        $docid = $dom->createElement('meta');
        $attr = $dom->createAttribute('docid');
        $attr->value = htmlspecialchars($doc_id);
        $docid->appendChild($attr);
        $head->appendChild($docid);

        // ページ情報
        $pages = $dom->createElement('pages');
        $dpi   = $this->la->getDpi();
        $la_pages = $this->la->getPages();

        $page_images = array();
        $imgdir = $this->getImageDir($doc_id);
        if ($this->cutimage) {
            // ページ画像を作成する（図表を切り出すために必要）
            for ($i = 0; $i < count($la_pages); $i++) {
                $page_img = sprintf("%s%s-%02d.png", $imgdir, $doc_id, $i + 1); // $page は 0から、ファイル名は 1 から
                $cmd = sprintf("pdftoppm -r %d -f %d -l %d -png %s > %s", $dpi, $i + 1, $i + 1, $pdfpath, $page_img);
                echo "executing: '", $cmd, "'...\n";
                echo `{$cmd}`;
                $page_images []= $page_img;
            }
        }
    
        foreach ($la_pages as $page) {
            $area = $page['text_area'];
            $p = $dom->createElement('page');

            $attr = $dom->createAttribute('width');
            $attr->value = sprintf("%5.4f in", $page['width'] / $dpi); // inch
            $p->appendChild($attr);

            $attr = $dom->createAttribute('height');
            $attr->value = sprintf("%5.4f in", $page['height'] / 100); // inch
            $p->appendChild($attr);

            $attr = $dom->createAttribute('data-bdr');
            $attr->value = sprintf("%6.5f,%6.5f,%6.5f,%6.5f", $area[0]/$page['width'], $area[1]/$page['height'], $area[2]/$page['width'], $area[3]/$page['height']);
            $p->appendChild($attr);

            $pages->appendChild($p);
        }
        $head->appendChild($pages);

        // フォントタイプ
        $ftypes = $dom->createElement('ftypes');
        // <fontspec id="0" size="11" family="Times" color="#000000"/>
        $la_fonts = $this->la->getFontClasses();
        foreach ($la_fonts as $fontkey => $id) {
            $e = $dom->createElement('fontspec');
            $attr = $dom->createAttribute('id');
            $attr->value = $id;
            $e->appendChild($attr);
            $e->setIdAttribute('id', true);
            list($fontname, $fontsize) = explode("\t", $fontkey);
            $attr = $dom->createAttribute('name');
            $attr->value = $fontname;
            $e->appendChild($attr);
            $attr = $dom->createAttribute('size');
            $attr->value = sprintf("%.1f pt", $fontsize);
            $e->appendChild($attr);
            $ftypes->appendChild($e);
        }
        $head->appendChild($ftypes);

        $html->appendChild($head);

        // body
        $body = $dom->createElement('body');
        $html->appendChild($body);
    
		$i = 0;
		foreach ($sections as $section) {
			$div_section = $dom->createElement('div');
            $attr = $dom->createAttribute('class');
            $attr->value = 'section';
            $div_section->appendChild($attr);
            $attr = $dom->createAttribute('data-name');
            $attr->value = $section['title'];
            $div_section->appendChild($attr);
            $attr = $dom->createAttribute('id');
            $attr->value = 'sec-'.$i;
            $div_section->appendChild($attr);
            $div_section->setIdAttribute('id', true);

            $body->appendChild($div_section);

            $j = 0;
			foreach ($section['boxes'] as $box) {
                $div_box = $dom->createElement('div');
                $attr = $dom->createAttribute('class');
                $attr->value = 'box';
                $div_box->appendChild($attr);
                $attr = $dom->createAttribute('data-name');
                $attr->value = $box['boxType'];
                $div_box->appendChild($attr);
                $attr = $dom->createAttribute('id');
                $attr->value = "box-{$i}-{$j}";
                $div_box->appendChild($attr);
                $div_box->setIdAttribute('id', true);


                $this->__toXhtmlParagraphs($dom, $div_box, $box['paragraphs'], $doc_id, $i, $j);
                $div_section->appendChild($div_box);
				$j++;
			}
			$i++;
        }

        // 作成したページ画像を削除する
        /* 
           foreach ($page_images as $page_img) {
           unlink($page_img);
           }
           if (count(glob($imgdir . '*.png')) == 0) {
           // 画像が1つもないのでディレクトリも削除する
           rmdir($imgdir);
           }
        */

        //echo $dom->saveXML(); die();
        $xhtml = @$dom->saveXML();
        // サロゲートペア表現を UTF-8 表現に戻す
        $xhtml = preg_replace_callback('/&#x([0-9a-zA-Z]{4});/',
        function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16');
        }, $xhtml);

        return $xhtml;
    }

    /**
     * 1パラグラフ分のデータから XHTML ツリーを作る
     * @param $dom
     * @param $div_box     XHTML を追加するサブツリーノード
     * @param $paragraphs  追加すべきパラグラフのデータ
     * @param $doc_id      文書ID
     * @param $section_no  セクションナンバー
     * @param $box_no      ボックスナンバー
     * @return 無し, $div_box に追加する（参照渡し）
     */
	private function __toXhtmlParagraphs($dom, &$div_box, $paragraphs, $doc_id, $section_no, $box_no) {

		$i = 0;
		foreach ($paragraphs as $paragraph) {
            $p = $dom->createElement('p'); //htmlspecialchars($paragraph['text']));
            $id = "p-{$section_no}-{$box_no}-{$i}";
      
            // 単語列を中に格納する
            if (isset($paragraph['fig'])) { // 画像の場合
                // 画像
                $j = 0;
                foreach ($paragraph['line'] as $line) {
                    foreach ($line as $word) {
                        $w = $dom->createElement('span');
                        $attr = $dom->createAttribute('class');
                        $attr->value="image";
                        $w->appendChild($attr);
                        $word_id = "w-{$section_no}-{$box_no}-{$i}-{$j}";
                        $attr = $dom->createAttribute('id');
                        $attr->value = $word_id;
                        $w->appendChild($attr);
                        $w->setIdAttribute('id', true);
                        $attr = $dom->createAttribute('data-bdr');
                        $bdr = $this->la->getRelativeBdr($word[1], $word[2], $word[3], $word[4], $paragraph['page']);
                        // $bdr = array($word[1], $word[2], $word[3], $word[4]);
                        $attr->value = sprintf("%6.5f,%6.5f,%6.5f,%6.5f", $bdr[0], $bdr[1], $bdr[2], $bdr[3]);
                        $w->appendChild($attr);
                        $img = $dom->createElement('img');
                        $attr = $dom->createAttribute('src');
                        $attr->value = $this->getImageDir($doc_id, true).$doc_id.'-'.$id.'.png';
                        if ($this->cutimage) {
                            $this->cutImage($doc_id, $paragraph['page'], $bdr, $id);
                        }
                        $img->appendChild($attr);
                        $w->appendChild($img);
                        $p->appendChild($w);
                        $j++;
                        break;
                    }
                    break;
                }
            } else { // 文字列の場合
                if ($this->have_mecab && preg_match('/[一-龠]+|[ぁ-ん]+|[ァ-ヴー]+|[ａ-ｚＡ-Ｚ０-９]+/u', $paragraph['text'] )) {
                    // 日本語文字列のマージ
                    $this->__mergeJapaneseWords($paragraph);
                    if (!isset($paragraph)) {
                        var_dump($paragraph);
                        die();
                    }
                }
                $j = 0;
                foreach ($paragraph['line'] as $line) {
                    foreach ($line as $word) {

                        // 前の語との間に空白を挟む
                        if (!isset($word[8]) || !in_array($word[8], array('ns', 'ss'))) {
                            $w = $dom->createTextNode(' ');
                            $p->appendChild($w);
                        }
            
                        // $w = $dom->createElement('span', htmlspecialchars($word[0]));
                        $w = $dom->createElement('span', $word[0]);
                        $attr = $dom->createAttribute('class');
                        $attr->value="word";
                        $w->appendChild($attr);
                        $word_id = "w-{$section_no}-{$box_no}-{$i}-{$j}";
                        $attr = $dom->createAttribute('id');
                        $attr->value = $word_id;
                        $w->appendChild($attr);
                        $w->setIdAttribute('id', true);

                        // bdr
                        $attr = $dom->createAttribute('data-bdr');
                        $bdr = $this->la->getRelativeBdr($word[1], $word[2], $word[3], $word[4], $paragraph['page']);
                        // $bdr = array($word[1], $word[2], $word[3], $word[4]);
                        $attr->value = sprintf("%6.5f,%6.5f,%6.5f,%6.5f", $bdr[0], $bdr[1], $bdr[2], $bdr[3]);
                        $w->appendChild($attr);

                        // フォント情報
                        if (count($word) > 5) {
                            $attr = $dom->createAttribute('data-ftype');
                            if (!isset($word[7])) {
                                var_dump($line); echo "\n";
                                var_dump($word); echo "\n";
                                die();
                            }
                            $attr->value = sprintf("%d", $word[7]);
                            $w->appendChild($attr);
                        }

                        // 前の語との空白
                        if (isset($word[8])) {
                            $attr = $dom->createAttribute('data-space');
                            switch ($word[8]) {
                            case 'ns':
                                $attr->value = 'nospace';
                                break;
                            case 'ss':
                                $attr->value = 'subsequence';
                                break;
                            case 'sp':
                                $attr->value = 'space';
                                break;
                            case 'hd':
                                $attr->value = 'bol';
                                break;
                            }
                            $w->appendChild($attr);
                        }

                        $p->appendChild($w);
                        $j++;
                    }
                }
            }

            $attr = $dom->createAttribute('id');
            $this->n2id[$paragraph['n']] = $id;
            $attr->value = $id;
            $p->appendChild($attr);
            $p->setIdAttribute('id', true);
            $attr = $dom->createAttribute('data-text');
            $attr->value = htmlspecialchars($paragraph['text']);
            $p->appendChild($attr);
            $attr = $dom->createAttribute('data-page');
            $attr->value = $paragraph['page'];
            $p->appendChild($attr);
            $attr = $dom->createAttribute('data-bdr');
            $bdr = $this->la->getRelativeBdr($paragraph['bdr'][0], $paragraph['bdr'][1], $paragraph['bdr'][2], $paragraph['bdr'][3], $paragraph['page']);
            $attr->value = sprintf("%6.5f,%6.5f,%6.5f,%6.5f", $bdr[0], $bdr[1], $bdr[2], $bdr[3]);
            // $attr->value = implode(',', $paragraph['bdr']);
            $p->appendChild($attr);
            // 継続パラグラフ
            if (isset($paragraph['continued_from'])) {
                $attr = $dom->createAttribute('data-continued-from');
                $from_id = $this->n2id[$paragraph['continued_from']];
                $attr->value = $from_id;
                $p->appendChild($attr);
                // 逆引き
                $element = $dom->getElementById($from_id);
                if (is_null($element)) {
                    printf("'%s' is not found in;\n", $from_id);
                    echo $dom->saveXML();
                    die();
                }
                $attr = $dom->createAttribute('data-continue-to');
                $attr->value = $id;
                $element->appendChild($attr);
            }
            // 図表
            if (isset($paragraph['fig'])) {
                $attr = $dom->createAttribute('data-fig');
                $attr->value = $paragraph['fig'];
                $p->appendChild($attr);
            }
            if (isset($paragraph['caption'])) {
                $attr = $dom->createAttribute('data-fig');
                $attr->value = $paragraph['caption'];
                $p->appendChild($attr);
            }

            $div_box->appendChild($p);
            $i++;
		}
	}


    /**
     * 二つの配列の最短距離ペアを DP で求める
     * @param $array0  配列1, [i]['key'] に i 番目の要素のキー
     * @param $array1  配列2  （同上）
     * @return array(<配列1から見た相手のインデックス>, <配列2から見た相手のインデックス>)
     */
    public static function __dpPairwise($array0, $array1) {
        // init table
        $table = array();
        for ($j = 0; $j <= count($array1); $j++) {
            $table[$j] = array();
            for ($i = 0; $i <= count($array0); $i++) {
                $table[$j][$i] = 0;
            }
        }
        for ($i = 0; $i <= count($array0); $i++) {
            $table[0][$i] = $i;
        }
        for ($j = 0; $j <= count($array1); $j++) {
            $table[$j][0] = $j;
        }

        // dynamic programming
        for ($j = 1; $j <= count($array1); $j++) {
            for ($i = 1; $i <= count($array0); $i++) {
                // calculate distance
                $cost = ($array0[$i - 1]['key'] == $array1[$j - 1]['key']) ? 0 : 1;
                $cost_delete = $table[$j][$i - 1] + 1;
                $cost_insert = $table[$j - 1][$i] + 1;
                $cost_replace = $table[$j - 1][$i - 1] + $cost;
                $table[$j][$i] = min($cost_delete, $cost_insert, $cost_replace);
            }
        }

        // revert to pairs
        $i = count($array0);
        $j = count($array1);
        $pairs = array(array(), array());
        while ($i > 0 && $j > 0) {
            $ins = $table[$j - 1][$i];
            $del = $table[$j][$i - 1];
            $rep = $table[$j - 1][$i - 1];
            if ($rep <= $ins && $rep <= $del) {
                $pairs[0][$i - 1] = $j - 1;
                $pairs[1][$j - 1] = $i - 1;
                $i--;
                $j--;
            } else if ($ins < $del) {
                $pairs[1][$j - 1] = $i -1;
                $j--;
            } else {
                $pairs[0][$i - 1] = $j -1;
                $i--;
            }
        }
        while ($i > 0) {
            $pairs[0][$i - 1] = 0;
            $i--;
        }
        while ($j > 0) {
            $pairs[1][$j - 1] = 0;
            $j--;
        }
        ksort($pairs[0], SORT_NUMERIC);
        ksort($pairs[1], SORT_NUMERIC);
        return $pairs;
    }

    /**
     * 配列形式のパラグラフデータに含まれる
     * 文字ごとに分割された「語」の情報を、
     * MeCab を使って正しい語の単位に結合する
     * @param $orig 文字ごとの「語」を含むパラグラフの配列データ
     * @return なし（ $orig を上書き、参照渡し）
     */
    private function __mergeJapaneseWords(&$orig) {
        // MeCab で分かち書き
        $words = mecab_split($orig['text']);
        $char_table = array();
        for ($i = 0; $i < count($words); $i++) {
            $chars = preg_split("//u", $words[$i], -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $c) {
                $char_table []= array('key' => $c, 'wc' => $i);
            }
        }

        $word_table = array();
        for ($l = 0; $l < count($orig['line']); $l++) {
            $line = $orig['line'][$l];
            for ($w = 0; $w < count($line); $w++) {
                $word = $line[$w][0];
                $chars = preg_split("//u", $word, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $c) {
                    $word_table []= array('key' => $c, 'line' => $l, 'word' => $w);
                }
            }
        }

        // 対応を計算
        $pairs = self::__dpPairwise($char_table, $word_table);
        $lines = array();
        for ($i = 0; $i < count($word_table); $i++) {
            $w = $word_table[$i];
            $l = $w['line'];
            $word = $orig['line'][$l][$w['word']];
            // printf("i:%03d\t '%s'\n", $i, $word[0]);
            if (!isset($lines[$l])) {
                $lines[$l] = array();
            }
            if ($i == 0) {
                // この行に語情報を追加する
                $lines[$l] []= $word;
            } else if ($w['line'] == $pre_w['line'] && $w['word'] == $pre_w['word']) {
                // この語は処理済みなのでスキップ
                continue;
            } else {
                $line_changed = $w['line'] != $word_table[$i - 1]['line']; // 行が変わった
                if (!isset($char_table[$pairs[1][$i]]['wc']) || !isset($char_table[$pairs[1][$i - 1]]['wc'])) {
                    echo "char_table:\n";
                    print_r($char_table);
                    echo "word_table:\n";
                    print_r($word_table);
                    echo "pairs:\n";
                    print_r($pairs);
                    die();
                }
                $word_changed = $char_table[$pairs[1][$i]]['wc'] != $char_table[$pairs[1][$i - 1]]['wc']; // 単語が変わった
                $font_changed = $word[7] != $pre_word[7]; // フォントが変わった（サイズも確認）
                if ($line_changed || $word_changed || $font_changed) {
                    // この行に語情報を追加する
                    if (!$word_changed && !$font_changed) {
                        // 本来は一語の途中で改行されている場合
                        $word[8] = 'ss'; // subsequence
                    }
                    $lines[$w['line']] []= $word;
                } else {
                    // 最後の語に追加
                    $last = array_pop($lines[$l]);
                    $last[0] = $last[0] . $word[0];
                    $last[1] = min($last[1], $word[1]);
                    $last[2] = min($last[2], $word[2]);
                    $last[3] = max($last[3], $word[3]);
                    $last[4] = max($last[4], $word[4]);
                    // フォント、フォントサイズ、語の間隔、フォントIDは変更不要
                    $lines[$l] []= $last;
                }
            }
            $pre_w = $w;
            $pre_word = $word;
        }
        $orig['line'] = $lines;
    }

    /**
     * 機械学習教師データ（ラベル無し）を生成する
     * 素性は PDF から取得して結合する
     * @param $pdf_dir      PDF が配置されているディレクトリ
     * @param $master_dir   教師データを配置するディレクトリ
     * @param $pdffigures_callback  pdffigures callback関数
     * @return なし
     * @sideeffect $master_dir 内のファイルは削除される
     */
    public function generateMaster($pdf_dir = 'pdf/', $master_dir = 'master/', $pdffigures_callback = null) {
        foreach (glob($master_dir . "*.csv") as $f) unlink($f);
        foreach (glob($pdf_dir . "*.pdf") as $pdf) {
            $basename = basename($pdf, ".pdf");
            $csv = $master_dir . $basename . '.csv';
            printf("Generating master '%s' from '%s'...\n", $csv, $pdf);
            if (!is_null($pdffigures_callback)) {
                $pdffigures = call_user_func($pdffigures_callback, $pdf);
            } else {
                $pdffigures = null;
            }
            $this->la->analyze($pdf, $pdffigures);
            $structure = $this->la->getLines();
            $features = $this->getCRFfeatures();
            $merged = '';
            $texts = array('master'=>'', 'analyzed'=>'');
            foreach ($features as $feature) {
                $args_feature = explode("\t", $feature);
                $args_feature[0] = '-';
                $merged .= sprintf("%s\n", implode("\t", $args_feature));
            }
            file_put_contents($csv, $merged);
        }
    }

    /**
     * 機械学習トレーニングデータを更新する
     * ラベルは教師データから、素性は PDF から取得して結合する
     * （教師データを更新した場合、素性を変更した場合に必要）
     * @param $pdfs   再作成したい PDF
     * @return なし
     * @sideeffect $training_dir 内のファイルは annotation_dir から
     *             再作成される
     */
    public function updateTrain($pdfs) {
        // --all 対応
        if (count($pdfs) == 1 && $pdfs[0] == '*') {
            $pdfs = glob($this->pdf_dir . '*.pdf');
        }
        @mkdir($this->training_dir, 0755, true);
        
        // アノテーションデータからトレーニングデータを作成する
        foreach ($pdfs as $pdf) {
            $basename = basename($pdf, ".pdf");
            $annofname  = $this->annotation_dir . $basename . ".csv";
            $trainfname = $this->training_dir . $basename . ".csv";
            $errfname = $this->training_dir . $basename . ".err";
            $logfname = "update_training.log";
            if (!is_readable($pdf)) {
                $pdf = $this->pdf_dir . $basename . '.pdf';
                if (!is_readable($pdf)) {
                    printf("PDF '%s' is not readable. (skipped)\n", $pdf);
                    continue;
                }
            }
            if (!is_readable($annofname)) {
                printf("Annotation file '%s' is not readable. (skipped)\n", $annofname);
                continue;
            }

            $fig_json = null;
            if ($this->figure_dir) {
                // pdffigures の出力を取得する
                $fig_json = AbekawaPdffigures::get($pdf, $this->figure_dir);
            }
        
            printf("Updating training data '%s'...", $trainfname, $pdf);

            $this->la->analyze($pdf, $fig_json);
            $structure = $this->la->getLines();
            $features = $this->getCRFfeatures();

            // ロジック変更で解析結果が変わってしまうことがあるため、
            // アノテーションデータと解析結果の diff を作成する

            // アノテーションデータを読み込み
            $lines['anno'] = array('label' => array(), 'text' => array(), 'feature' => array());
            $fanno = fopen($annofname, "r");
            while ($line = fgets($fanno)) {
                $args = explode("\t", trim($line));
                if (count($args) == 2 || count($args) == 3) {
                    $lines['anno']['label'] []= $args[0];
                    $lines['anno']['text'] []= $args[1];
                }
            }
            fclose($fanno);

            // 解析結果を展開
            $lines['analyzed'] = array('label' => array(), 'text' => array(), 'feature' => array());
            foreach ($features as $line) {
                $args = explode("\t", trim($line));
                if (count($args == 3)) {
                    $lines['analyzed']['label'] []= $args[0];
                    $lines['analyzed']['text'] []= $args[1];
                    $lines['analyzed']['feature'] []= $args[2];
                }
            }

            // 比較
            require_once(dirname(__FILE__).'/class.Diff.php');
            $diff = Diff::compare(
                $lines['anno']['text'],
                $lines['analyzed']['text']
            );

            // 結果を出力
            $stack = array();
            $fh = fopen($trainfname, "w");
            if (!$fh) {
                throw new RuntimeException("Train file '{$trainfname}' cannot open.");
            }
            $fh_err = fopen($errfname, "w");
            $fh_log = fopen($logfname, "w");
            if (!$fh_err) {
                throw new RuntimeException("Error file '{$errffname}' cannot open.");
            }
            $im = 0;
            $ia = 0;
            for ($i = 0; $i < count($diff); $i++) {
                $sw = $diff[$i][1];
                if ($im == count($lines['anno']['label'])) {
                    if ($ia == count($lines['analyzed']['label'])) {
                        printf("--- text drained while processing %d of 'diff', where:\n", $i);
                        $w = array("UNMODIFIED", "DELETED", "INSERTED");
                        for ($i = $i - 5 ; $i < count($diff); $i++) {
                            printf("%03d[%s]:%s\n", $i, $w[$diff[$i][1]], $diff[$i][0]);
                        }
                        die();
                        break;
                    }
                    $sw = Diff::INSERTED;
                } else if ($ia == count($lines['analyzed']['label'])) {
                    $sw = Diff::DELETED;
                }
                switch ($sw) {
                case Diff::UNMODIFIED:
                    if ($lines['analyzed']['text'][$ia] !== $diff[$i][0]) {
                        printf("--- warn: i[%d]:'%s', ia[%d]:'%s'\n", $i, $diff[$i][0], $ia, $lines['analyzed']['text'][$ia]);
                    }
                    if ($im >= count($lines['anno']['label'])
                    || $ia >= count($lines['analyzed']['label'])) {
                        $this->__outputUpdateModelLog($fh_log, $im, $ia, $lines);
                        print_r($diff);
                        printf("Error on Diff:UNMODIFIED, target csv: '%s'\n", $f);
                        throw new RuntimeException('Number of lines mismatch, see "update_training.log"');
                        die();
                    }
                    fprintf(
                        $fh,
                        "%s\t%s\t%s\n",
                        $lines['anno']['label'][$im],
                        $lines['analyzed']['text'][$ia],
                        $lines['analyzed']['feature'][$ia]
                    );
                    fprintf($fh_log, "--\n%03d:%s\n%03d:%s\n", $im, $lines['anno']['text'][$im], $ia, $lines['analyzed']['text'][$ia]);
                    $im++;
                    $ia++;
                    unset($stack);
                    $stack = array();
                    break;
                case Diff::DELETED:  // 該当行は解析結果にはない
                    if ($im >= count($lines['anno']['label'])) {
                        $this->__outputUpdateModelLog($fh_log, $im, $ia, $lines);
                        print_r($diff);
                        printf("Error on Diff:DELETED, target csv: '%s'\n", $f);
                        throw new RuntimeException('Number of lines mismatch, see "update_training.log"');
                        die();
                    }
                    array_push($stack, array($lines['anno']['label'][$im], $lines['anno']['text'][$im]));
                    fprintf($fh_log, "--\n%03d:%s\n", $im, $lines['anno']['text'][$im]);
                    $im++;
                    break;
                case Diff::INSERTED: // 該当行はマスターにはない
                    if ($ia >= count($lines['analyzed']['label'])) {
                        $this->__outputUpdateModelLog($fh_log, $im, $ia, $lines);
                        print_r($diff);
                        printf("Error on Diff:INSERTED, target csv: '%s'\n", $f);
                        throw new RuntimeException('Number of lines mismatch, see "update_training.log"');
                        die();
                    }
                    if (count($stack) > 0) {
                        list($label, $text) = array_shift($stack);
                        // マスターのテキストと比較して十分近ければ採用する
                        $str1 = preg_replace('/<\/?su[pb]>/', '', $text);
                        $str2 = preg_replace('/<\/?su[pb]>/', '', $lines['analyzed']['text'][$ia]);
                        $d = levenshtein($str1, $str2);
                        if ($d > strlen($str1 . $str2) * .2) {
                            fprintf($fh_err, "--- dist:%d\n%d-\t%s\n%d+\t%s\n", $d, $im, $text, $ia, $lines['analyzed']['text'][$ia]);
                            $label = self::UNLABELLED_LINE . ' ' . $label;
                        }
                    } else {
                        fprintf($fh_err, "---\n%d+\t%s\n", $ia, $lines['analyzed']['text'][$ia]);
                        $label = self::UNLABELLED_LINE;
                    }
                    fprintf(
                        $fh,
                        "%s\t%s\t%s\n",
                        $label,
                        $lines['analyzed']['text'][$ia],
                        $lines['analyzed']['feature'][$ia]
                    );
                    fprintf($fh_log, "--\n%03d:%s\n", $ia, $lines['analyzed']['text'][$ia]);
                    $ia++;
                    break;
                }
            }
            fclose($fh);
            fclose($fh_err);
            fclose($fh_log);
            echo "done.\n";
        }
    }

    /**
     * トレーニングデータから機械学習モデルを更新する
     * （トレーニングデータを修正、追加した場合に必要）
     * @param $train_dir    素性付き学習用データが配置されているディレクトリ
     * @return なし
     * @sideeffect $modelfile が上書きされる
     */
    public function updateModel() {
        $training_data = array();
        foreach (glob($this->training_dir . "*.csv") as $f) {
            $training_data []= $f;
        }
        printf("Learning model from training data...");
        $this->crf->learn($training_data);
        printf("done.\n");
    }

    /**
     * updateModel 処理中のエラーログを出力する
     */
    private function __outputUpdateModelLog($fh_log, $im, $ia, &$lines) {
        fprintf($fh_log, "---\nError: Number of lines mismatch.\nim = %d/%d, ia = %d/%d\n", $im, count($lines['anno']['label']), $ia, count($lines['analyzed']['label']));
        fprintf($fh_log, "--- 'anno' remains:\n");
        for ($j = $im; $j < count($lines['anno']['text']); $j++) {
            fprintf($fh_log, "%03d:%s\n", $j, $lines['anno']['text'][$j]);
        }
        fprintf($fh_log,  "--- 'analyzed' remains:\n");
        for ($j = $ia; $j < count($lines['analyzed']['text']); $j++) {
            fprintf($fh_log, "%03d:%s\n", $j, $lines['analyzed']['text'][$j]);
        }
    }

    /**
     * PDF からアノテーションファイルを生成する
     * @param $pdfs   PDF のリスト
     */
    public function pdf2anno($pdfs) {
        if (!is_readable($this->modelfile)) {
            echo "Warning: Model file '" . $this->modelfile . "' is not readable.\nGenerate annotation files with label='O'.\n";
        }

        // --all 対応
        if (count($pdfs) == 1 && $pdfs[0] == '*') {
            $pdfs = glob($this->pdf_dir . '*.pdf');
        }
    
        foreach ($pdfs as $pdfpath) {
            if (!is_readable($pdfpath)) {
                $pdfpath = $this->pdf_dir . $pdfpath;
                if (!is_readable($pdfpath)) {
                    echo "Cannot read file '{$pdfpath}'. (skipped)\n";
                    continue;
                }
            }
            echo "Reading '{$pdfpath}'... ";

            // アノテーションファイル生成
            $basename = basename($pdfpath, ".pdf");
            $annofname = $this->annotation_dir . $basename . '.csv';
            if (file_exists($annofname)) {
                // バックアップ
                $backupdir = $this->annotation_dir . date(DATE_ATOM) . '/';
                @mkdir($backupdir);
                copy($annofname, $backupdir . $basename . '.csv');
            }
            if (!file_exists($this->annotation_dir)) {
                @mkdir($this->annotation_dir, 0755, true);
            }
            $fha = fopen($annofname, "w");
            if (!$fha) {
                printf("Cannot open annotation file '%s' to write. (skip)", $annofname);
                continue;
            }

            $fig_json = null;
            if ($this->figure_dir) {
                // pdffigures の出力を取得する
                $fig_json = AbekawaPdffigures::get($pdfpath, $this->figure_dir);
            }

            // Analyze layout
            $this->la->analyze($pdfpath, $fig_json);
            $lines = $this->la->getLines();

            // Get crf features
            echo "GetCRFfeatures, ";
            $features = $this->getCRFfeatures();
            $infile = $basename.'_in.txt';
            file_put_contents($infile, implode("\n", $features));

            // Tagging
            if (is_readable($this->modelfile)) {
                echo "Tagging, ";
                $tagged = $this->crf->tagging($infile);
                // アノテーションファイル出力
                foreach ($tagged as $l) {
                    unset($l[2]);
                    fprintf($fha, "%s\n", implode("\t", $l));
                }
            } else {
                // モデルファイルがないので空ラベルを付ける
                foreach ($features as $l) {
                    $args = explode("\t", $l);
                    $args[0] = 'O';
                    unset($args[2]);
                    fprintf($fha, "%s\n", implode("\t", $args));
                }
            }
            fclose($fha);
            echo "done.\n";

            unlink($infile);
        }

    }

    /**
     * PDF を XHTML に変換する
     * @param $pdfs   PDF のリスト
     */
    public function pdf2xhtml($pdfs) {
        if (!file_exists($this->modelfile)) {
            echo "Model file '" . $this->modelfile . "' is not exists.(abort)\n";
            return;
        }

        // --all 対応
        if (count($pdfs) == 1 && $pdfs[0] == '*') {
            $pdfs = glob($this->pdf_dir . '*.pdf');
        }
    
        foreach ($pdfs as $pdfpath) {
            if (!is_readable($pdfpath)) {
                $pdfpath = $this->pdf_dir . $pdfpath;
                if (!is_readable($pdfpath)) {
                    echo "Cannot read file '{$pdfpath}'. (skipped)\n";
                    continue;
                }
            }

            // ディレクトリの準備
            if (!file_exists($this->xhtml_dir)) {
                @mkdir($this->xhtml_dir, 0755, true);
            }
            if (!file_exists($this->xhtml_dir . 'css/')) {
                @mkdir($this->xhtml_dir . 'css/', 0755, true);
            }
            // CSS をコピー
            if (!file_exists($this->xhtml_dir . 'css/pdf2xhtml.css')) {
                @copy(dirname(__FILE__) . '/pdf2xhtml.css', $this->xhtml_dir . 'css/pdf2xhtml.css');
            }
      
            echo "Reading '{$pdfpath}'... ";

            // Analyze layout
            $this->la->analyze($pdfpath);
            $lines = $this->la->getLines();

            // Get crf features
            echo "GetCRFfeatures, ";
            $features = $this->getCRFfeatures();
            $basename = basename($pdfpath, ".pdf");
            $infile = $basename.'_in.txt';
            file_put_contents($infile, implode("\n", $features));
  
            // Tagging
            $outfile = $basename.'_tag.txt';
            echo "Tagging, ";
            $tagged = $this->crf->tagging($infile);
            $fh = fopen($outfile, "w");
            foreach ($tagged as $l) {
                fprintf($fh, "%s\n", implode("\t", $l));
            }
            fclose($fh);

            // Merge tagged result
            $tags = array();
            foreach ($tagged as $l) {
                $tags []= $l[0];
            }
            echo "mergeTags, ";
            $this->__reset();
            $textboxes = $this->__mergeTags($lines, $tags);
            $jsonfile = $basename.'.json';
            file_put_contents($jsonfile, json_encode($textboxes));

            echo "AssignSections, ";
            $sections = $this->__assignSections($textboxes);
            echo "toXhtml, ";
            $xhtml = $this->__toXhtml($pdfpath, $sections);
            file_put_contents($this->xhtml_dir . $basename.'.xhtml', $xhtml);
            echo "done.\n";
            unlink($infile);
            unlink($outfile);
            unlink($jsonfile);
        }

    }
  
}
