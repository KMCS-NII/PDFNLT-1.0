<?php

/**
 * @file LayoutAnalyzer.php
 * PDF のレイアウト構造解析を行い
 * 行単位の構造データを出力する
 */

class LayoutAnalyzer
{
    function __construct() {
        $this->dpi = 100;  // 100dpi
        $this->target_page_from = null;
        $this->target_page_to   = null;
        $this->column_gap  = 0.15; // 段落間に必要な隙間 0.15 inch(3.81mm)
        $this->wgap = 0.1; // 単語間に必要な隙間 0.1 inch
        $this->mingap = 0.02; // 単語間の空白と認めない最大間隔 0.02 inch
        $this->debug = false;
        $this->__reset();
    }

    private function __reset() {
        $this->pdffigures = null;
        $this->bbox = 0;
        $this->pages = array();
        $this->lines = array();
        $this->indents = null;
        $this->font_classes = null;
    }

    function __destruct() {
    }

    /**
     * 行情報を返す
     * @return 行情報の配列
     */
    public function getLines() {
        return $this->lines;
    }

    /**
     * ページ情報を返す
     * @return ページ情報の配列
     */
    public function getPages() {
        return $this->pages;
    }

    /**
     * フォント情報を返す
     * @return フォント情報の配列
     */
    public function getFontClasses() {
        return $this->font_classes;
    }

    /**
     * 標準的な（頻度の最も大きな）行の高さを返す
     * @return 行の高さ
     */
    public function getLineHeight() {
        return $this->line_height;
    }

    /**
     * 標準的な（頻度の最も大きな）行間を返す
     * @return 行間
     */
    public function getLineSpace() {
        return $this->line_space;
    }

    /**
     * カラムの x 座標を返す
     * @return x 座標列
     */
    public function getIndents() {
        return $this->indents;
    }

    /**
     * pdffigures の結果を返す
     * @return pdffigures の結果配列
     */
    public function getPdfFigures() {
        return $this->pdffigures;
    }

    /**
     * debug フラグの setter, getter
     */
    public function setDebug($bool = true) {
        $this->debug = $bool;
    }
    public function getDebug() {
        return $this->Debug;
    }

    /**
     * DPI の setter, getter
     */
    public function setDpi($dpi = 100) {
        $this->dpi = $dpi;
    }
    public function getDpi() {
        return $this->dpi;
    }

    /**
     * 段落間の最小の隙間の setter, getter
     * 単位は inch, デフォルトは 0.15
     */
    public function setColumnGap($gap = 0.15) {
        $this->column_gap = $gap;
    }
    public function getColumnGap() {
        return $this->column_gap;
    }

    /**
     * 処理開始ページの setter, getter
     * 最初のページを1
     * 0 をセットした場合は1ページ目から
     */
    public function setTargetPageFrom($from = 1) {
        $this->target_page_from = $from;
    }
    public function getTargetPageFrom() {
        return $this->target_page_from;
    }

    /**
     * 処理終了ページの setter, getter
     * 最初のページを1
     * 0 または実際のページ数より大きい値をセットした場合は最後まで
     */
    public function setTargetPageTo($to = 999) {
        $this->target_page_to = $to;
    }
    public function getTargetPageTo() {
        return $this->target_page_to;
    }

    // Boundary Rectangle をページに対する相対的な大きさに変換する
    // @return 座標値
    // @param $page ページ情報
    public function getRelativeBdr($x0, $y0, $x1, $y1, $page) {
        $x0 = $x0 / $this->pages[$page]['width'];
        $y0 = $y0 / $this->pages[$page]['height'];
        $x1 = $x1 / $this->pages[$page]['width'];
        $y1 = $y1 / $this->pages[$page]['height'];
        return array($x0, $y0, $x1, $y1);
    }

    // Boundary Rectangle を絶対的な大きさに変換する
    // @return 座標値
    // @param $page ページ情報
    public function getAbsoluteBdr($x0, $y0, $x1, $y1, $page) {
        $x0 = $x0 * $this->pages[$page]['width'];
        $y0 = $y0 * $this->pages[$page]['height'];
        $x1 = $x1 * $this->pages[$page]['width'];
        $y1 = $y1 * $this->pages[$page]['height'];
        return array($x0, $y0, $x1, $y1);
    }

    /**
     * Ligature を置き換える
     * @param $text   Ligature を含むかもしれないテキスト
     * @return        Ligature を置き換えたテキスト
     * ref: http://www.fileformat.info/info/unicode/block/alphabetic_presentation_forms/images.htm
     **/
    public static function replaceLigature($text) {
        // コード列なので 'u' オプションはつけてはいけない
        $text = preg_replace('/\xef\xac\x80/s', 'ff', $text);
        $text = preg_replace('/\xef\xac\x81/s', 'fi', $text);
        $text = preg_replace('/\xef\xac\x82/s', 'fl', $text);
        $text = preg_replace('/\xef\xac\x83/s', 'ffi', $text);
        $text = preg_replace('/\xef\xac\x84/s', 'ffl', $text);
        $text = preg_replace('/\xef\xac\x85/s', 'ft', $text);
        $text = preg_replace('/\xef\xac\x86/s', 'st', $text);
        // P00-1021 で化ける
        /*
          $text = preg_replace('/\x0b/su', 'ff', $text);
          $text = preg_replace('/\x0c/su', 'fi', $text);
          $text = preg_replace('/\x0d/su', 'fl', $text);
          $text = preg_replace('/\x0e/su', 'ffi', $text);
          $text = preg_replace('/\x0f/su', '•', $text);
          $text = preg_replace('/\x10/su', 'ft', $text);
          $text = preg_replace('/\x11/su', 'st', $text);
        */
        return $text;
    }

    /**
     * 指定した y 座標でのレイアウト番号を返す
     * @param $page_indents そのページのインデント座標配列
     *             [0]=>[0, ページ高さ, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [1]=>[ymin, ymax, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [2]=>...
     * @param $y   判定する y 座標
     * @return 0 -> その他, 1, 2, ... 部分レイアウトの番号
     */
    private function __get_indent_number_by_y($page_indents, $y) {
        // 部分的にインデント位置が違う領域をチェック
        for ($i = 1; $i < count($page_indents); $i++) {
            if ($page_indents[$i][0] < $y && $page_indents[$i][1] > $y) {
                return $i;
            }
        }
        return 0;
    }

    /**
     * 指定した y 座標での左右カラムの左端、右端座標を返す
     * @param $page_indents そのページのインデント座標配列
     *             [0]=>[0, ページ高さ, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [1]=>[ymin, ymax, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [2]=>...
     * @param $y   判定する y 座標
     * @return [左段左端, 左段右端, 右段左端, 右段右端]
     */
    private function __get_indent_by_y($page_indents, $y) {
        $n = $this->__get_indent_number_by_y($page_indents, $y);
        return $page_indents[$n][2];
    }

    /**
     * 2 つの行が、レイアウト上で連続した領域に含まれるかどうか判定する
     * @param $page_indents そのページのインデント座標配列
     *             [0]=>[0, ページ高さ, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [1]=>[ymin, ymax, [左段左端, 左段右端, 右段左端, 右段右端]]
     *             [2]=>...
     * @param $l0, $l1  判定する行
     * @return 連続する場合 true, 連続しない場合 false
     */
    private function __is_continuable_lines($page_indents, $l0, $l1) {
        if (is_null($l0) || is_null($l1)) {
            return false;
        }
        // printf("l0:'%s' <-> l1:'%s' = %f\n", implode(' ', $l0[5]), implode(' ', $l1[5]), min(abs($l0[4] - $l1[2]), abs($l0[2] - $l1[4])));
        if (abs($l0[4] - $l1[2]) > 0.3 * $this->dpi
            && abs($l0[2] - $l1[4]) > 0.3 * $this->dpi) { // ベースラインでチェック
            // y 方向に 0.3in 以上離れている
            return false;
        }
        $n0 = $this->__get_indent_number_by_y($page_indents, $l0[4]);
        $n1 = $this->__get_indent_number_by_y($page_indents, $l1[4]);
        if ($n0 == $n1) {
            // 同じレイアウトに属している
            return true;
        }
        if ($n0 < 0 || $n1 < 0
            || $n0 >= count($page_indents)
            || $n1 >= count($page_indents)
            || abs($n0 - $n1) > 1) {
            // 隣り合わないレイアウト
            return false;
        }
        $ll0 = $page_indents[$n0][2];
        $ll1 = $page_indents[$n1][2];
        if (is_null($ll0[1]) && is_null($ll1[1])) {
            // どちらもシングルカラムの場合
            return true;
        }
        if (is_null($ll0[1]) || is_null($ll1[1])) {
            // 片方がシングルカラムの場合
            return false;
        }
        // どちらもダブルカラムの場合
        if ($ll0[1] < $ll1[2] && $ll0[2] > $ll1[1]) {
            // 段落間の空白が交差していなければ OK
            return true;
        }
        // それ以外は続かない
        return false;
    }

    /**
     * 指定した行情報のｙ座標での左右カラムの左端、右端座標を返す
     * @param $line  行情報
     * @return [左段左端, 左段右端, 右段左端, 右段右端]
     */
    public function getIndentOfLine($line) {
        $page = $line[6];
        $page_indents = $this->pages[$page]['indent'];
        return $this->__get_indent_by_y($page_indents, $line[4]);
    }

    /**
     * 単語ボックスがどの行と一番近いかを決定する
     * @param $lines    既に配置済みの行情報
     * @param $w        単語ボックス情報
     * @param $page_indents       ページインデントの配列
     * @return array
     *          i:    何行目かを表すインデックス番号
     *          r:    上付き、下付きを表す数字 (see __is_same_line)
     *          dist: ボックス中心線の距離（高さ方向のずれ）
     **/
    private function __get_best_line(&$lines, $w, $page_indents) {
        $min = array("i"=>null, "r"=>0, "dist"=>null);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $r = $this->__is_same_line($line[2], $line[4], $w[2], $w[4]);
            if ($this->debug) {
                printf("[%d]:'%s' => %d\n", $i, implode(' ', $line[5]), $r);
                if ($r != 0) {
                    printf("line: %s\nw: %s\n", var_export($line, 1), var_export($w, 1));
                }
            }
            if ($r != 0 // 語が行と同じ行
            && $line[1] < $w[1]        // 語が行より右にある（重なっていることもある）
            && $line[3] + 2 * $this->wgap * $this->dpi > $w[1]) { // 語が行の右端から離れすぎていない
                // 段組みチェック、右段落ならこの行に接続しない
                list($l0, $r0, $l1, $r1) = $this->__get_indent_by_y($page_indents, $line[4]);
                $cond1 = (!is_null($r0));
                $cond2 = ($line[3] <= $l1 + $this->wgap * $this->dpi);
                $cond3 = ($w[1] >= $l1 - $this->wgap * $this->dpi);
                $cond4 = ($w[1] - $line[3] > $this->wgap * $this->dpi);
                $cond5 = (abs($w[1] - $l1) <= 0.01 * $this->dpi);
                if ($this->debug) {
                    echo "Checking if this word is in the right column.\n";
                    echo "  r0(right x of the left column):", var_export($r0, 1), "\n";
                    echo "  line[3](right x of the line):", var_export($line[3], 1), "\n";
                    echo "  w[1](left x of the word):", var_export($w[1], 1), "\n";
                    echo "  l1(left x of the right column):", var_export($l1, 1), "\n";
                    if ($cond1) {
                        echo "cond1=true:  right column exists in this page.\n";
                    } else {
                        echo "cond1=false: right column is not exist.\n";
                    }
                    if ($cond2) {
                        echo "cond2=true:  the line is in the left column.\n";
                    } else {
                        echo "cond2=false: the line is not in the left column.\n";
                    }
                    if ($cond3) {
                        echo "cond3=true:  the word is in the right column.\n";
                    } else {
                        echo "cond3=false: the word is not in the right column.\n";
                    }
                    if ($cond4) {
                        echo "cond4=true:  the line and the word have enough space.\n";
                    } else {
                        echo "cond4=false: the line and the word doesn't have enough space.\n";
                    }
                    if ($cond5) {
                        echo "cond5=true:  the word is just on the left edge of the right column.\n";
                    } else {
                        echo "cond5=false: the word is not just on the left edge of the right column.\n";
                    }
                }
                if ($cond1 && $cond2 && $cond3 && ($cond4 || $cond5)) {
                    if ($this->debug) {
                        echo "-> The word is in the right column.\n";
                    }
                    continue;
                }
                if ($this->debug) {
                    echo "-> The word will be connected to the line.\n";
                }

                $dist = abs(($line[2] + $line[4]) / 2.0 - ($w[2] + $w[4]) / 2.0);
                if (is_null($min['i']) || $min['dist'] > $dist) {
                    $min['i'] = $i;
                    $min['r'] = $r;
                    $min['dist'] = $dist;
                }
            }
        }
        return $min;
    }

    // 2つのボックスが同じ行にあるかどうかをチェックする
    // y座標が[$y00,$y01]のボックス１と[$y10,$y11]のボックス２を比較
    // 同じ行の場合は 1, 違う行の場合は 0、
    // ボックス１がボックス２の上付き文字の場合は 2、
    // ボックス１がボックス２の下付き文字の場合は -2、
    // ボックス２がボックス１の上付き文字の場合は 3、
    // ボックス２がボックス１の下付き文字の場合は -3
    // をそれぞれ返す
    private function __is_same_line($y00, $y01, $y10, $y11) {
        $h0 = $y01 - $y00;
        $h1 = $y11 - $y10;
        $h = min($y01, $y11) - max($y00, $y10); // 交差している部分の高さ
        $rate = $h / max($h0, $h1); // 大きい方のボックスに対する割合
        if ($rate > 0.3) return 1;
        return 0;
    }
    private function __is_same_line_alt($y00, $y01, $y10, $y11) {
        // y は10倍されている点に注意
        $h0 = ($y01 - $y00) / 2.0;
        $h1 = ($y11 - $y10) / 2.0;
        if ($this->debug) {
            printf("h0:%.3f, h1:%.3f\n",
                   $h0, $h1);
            die();
        }
        if ($h0 > $h1) {
            if ($h0 > $this->dpi) { // 図表の場合ボックスが大きいことがあるが
                $h0 = $this->dpi;     // 最大でも上下 0.1inch まで
            }
      
            // ボックス１が基本ボックス
            if ($y00 - $h0 > $y10 || $y01 + $h0 < $y11) return 0;
            if ($y00 > $y10 && $y01 > $y11 && $h0 > $h1 * 1.3) return 3; // 3;
            if ($y00 < $y10 && $y01 < $y11 && $h0 > $h1 * 1.3) return -3; // -3;
        } else {
            if ($h1 > $this->dpi) { // 最大でも上下 0.1inch まで
                $h1 = $this->dpi;
            }
            // ボックス２が基本ボックス
            if ($y10 - $h1 > $y00 || $y11 + $h1 < $y01) return 0;
            if ($y10 > $y00 && $y11 > $y10 && $h1 > $h0 * 1.3) return 2; // 2;
            if ($y10 < $y00 && $y11 < $y10 && $h1 > $h0 * 1.3) return -2; //-2;
        }
        return 1;
    }

    /**
     * pdffigures コマンドを実行し、図表領域を取得する
     * @param   $pdfpath     処理対象の PDF ファイルパス
     * @return  pdffigures 情報（JSON形式）をパースした配列を返す
     **/
    public function pdfFigures($pdfpath) {
        $tmpfname = tempnam('/tmp', 'pdffigures');
        $cmd = sprintf("pdffigures -j %s %s", $tmpfname, $pdfpath);
        if (isset($this->debug) && $this->debug) {
            echo "Executing '${cmd}'\n";
        }
        $output = array();
        exec($cmd, $output);
        if (!is_readable($tmpfname.'.json')) {
            unlink($tmpfname);
            $reason = implode("\n", $output);
            throw new RuntimeException("File '{$pdfpath}' cannot be processed by pdffigures. 'pdffigures' returned message:{$reason}");
        }
        $content = file_get_contents($tmpfname.'.json');
        $pdffigures = json_decode($content, true);
        unlink($tmpfname);
        unlink($tmpfname.'.json');
        if (isset($this->debug) && $this->debug) {
            file_put_contents("pdffigures_output.txt", $output);
        }
        return $pdffigures;
    }

    /**
     * pdftotext コマンドを実行し、 単語単位の情報（bbox）を取得する
     * @param   $pdfpath     処理対象の PDF ファイルパス
     * @param   $dpi  解析する際の解像度 (DPI)
     * @return  bbox 情報（文字列）を返す
     **/
    public function pdfToText($pdfpath) {
        $cmd = sprintf("pdftotext -r %d -bbox %s -", $this->dpi, $pdfpath);
        if (isset($this->debug) && $this->debug) {
            echo "Executing '${cmd}'\n";
        }
        $ouput = array();
        exec($cmd, $output);
        $bbox = implode("\n", $output);

        if ($this->debug) {
            file_put_contents("pdftotext_output.txt", $output);
        }
        return $bbox;
    }

    /**
     * ページ内の情報を生成する
     * @param  $page_info <page>...</page> マッチパターン
     *           [width, height, タグ内文字列]
     * @param  $npage 処理中のページ番号（0スタート）
     * @param  $is_last_page  最終頁の場合 true
     */
    private function __setLinesInOnePage($page_info, $npage, $is_last_page) {
        $width = $page_info[1] * $this->dpi / 72; // この部分だけ 72dpi で計算されているので変換する
        $height = $page_info[2] * $this->dpi / 72;
        // 文字領域 x0, y0, x1, y1, 二段組み左段右端, 二段組み右段左端
        $text_area = array($width, $height, 0, 0, 0, $width); 
        $this->pages[$npage] = array("width"=>$width, "height"=>$height);
    
        // 単語情報を抽出
        //  <word xMin="76.001100" yMin="72.481122" xMax="122.884482" yMax="85.378356">Parsing</word>
        preg_match_all('/<word xMin="(.*?)" yMin="(.*?)" xMax="(.*?)" yMax="(.*?)" fName="(.*?)" fSize="(.*?)">(.*?)<\/word>/u', $page_info[3], $word_info, PREG_SET_ORDER);
        // 全単語をまず x 順、次に y 順にソート
        usort($word_info, function($w0, $w1) {
            $dx = $w0[1] - $w1[1];
            if ($dx != 0) return $dx;
            $dy = $w0[2] - $w1[2];
            return $dy;
        });

        // リガチャ（合字）の置換
        for ($i = 0; $i < count($word_info); $i++) {
            $word_info[$i][0] = self::replaceLigature($word_info[$i][7]);
            unset($word_info[$i][7]);
        }
        /*
          $word_info[i] には以下の情報が格納されている
          0: 単語テキスト（ligature 変換済み）
          1: xmin
          2: ymin
          3: xmax
          4: ymax
          5: font-name
          6: font-size
        */
      
        // 図表領域に交差する文字列を除去
        $pdffigures = array();
        // このページに含まれる図表だけ選択
        foreach ($this->pdffigures as $fig) {
            if ($fig['Page'] != $npage + 1) continue;
            $pdffigures []= $fig;
        }
        for ($i = 0; $i < count($word_info); $i++) {
            $w = $word_info[$i];
            foreach ($pdffigures as $fig) {
                if (($w[1] < $fig['ImageBB'][2] && $w[3] > $fig['ImageBB'][0])
                && ($w[2] < $fig['ImageBB'][3] && $w[4] > $fig['ImageBB'][1])) {
                    $word_info[$i] = null;
                    break;
                }
            }
        }
        $word_info = array_values(array_filter($word_info));

        // 文字領域と段組み情報を取得
        $page_structure = $this->__getPageStructure($word_info, $width, $height, $is_last_page);

        // 単語情報を行ごとにまとめる
        $lines = $this->__getLineInfo($word_info, $page_structure, $npage);
        $page_structure['lines'] = $lines;
    
        return $page_structure;
    }

    /**
     * 単語情報を行ごとにまとめ、行情報の配列として返す
     * @param $word_info   ページ内単語情報の配列（ソート済み）
     * @param $page_structure  ページ内のテキスト領域および段落x座標の情報
     * @param $npage       処理中のページ番号
     * @return 行情報の配列
     * 行情報
     *   0: 行に含まれる単語情報の配列
     *   1: xmin
     *   2: ymin
     *   3: xmax
     *   4: ymax
     *   5: 行に含まれる単語表記
     *   6: ページ番号
     *   7: 段組み（single, left, right）
     *   8: 単語間スペースの情報（hd:行頭, sp:空白あり, ns:空白なし）
     */
    private function __getLineInfo(&$word_info, &$page_structure, $npage) {
        $text_area = $page_structure['text_area'];
        $page_indents = $page_structure['indent'];
        $lines = array();

        foreach ($word_info as $w) {
            if (preg_match('/^[\x00-\x1f]$/', $w[0])) continue;

            /*
            if ($w[0] == "P" && $w[1] == 440.000000) {
                // print_r($w); echo "\n";
                $this->debug = true;
            } else {
                $this->debug = false;
            }
            */

            $min = $this->__get_best_line($lines, $w, $page_indents);
            if ($this->debug) {
                print_r($w);
                printf("w[0] = '%s', min = (i:%s, r:%d, dist:%f)\n", $w[0], var_export($min['i'], true), $min['r'], $min['dist']);
            }

            if (is_null($min['i'])) {
                // 新しい行の先頭になる
                $newline = $w;
                $newline[0] = array($w);
                $newline[5] = array($w[0]);
                $newline[6] = $npage;
                $newline[7] = 'single';
                $lines []= $newline;
            } else {
                // この行につなげる
                $line = $lines[$min['i']];
                $line[0][]= $w;
                $line[2] = min($line[2], $w[2]);
                $line[3] = max($line[3], $w[3]);
                $line[4] = max($line[4], $w[4]);
                $line[5] []= $w[0];
                /*
                  switch ($min['r']) {
                  case 1:
                  $line[5] []= $w[0];
                  break;
                  case 2:
                  foreach ($line[5] as $j => $lw) {
                  if (!preg_match('!^<su.>.*</su.>$!u', $lw))
                  $line[5][$j] = '<sup>'.$lw.'</sup>';
                  }
                  $line[5] []= $w[0];
                  break;
                  case -2:
                  foreach ($line[5] as $j => $lw) {
                  if (!preg_match('!^<su.>.*</su.>$!u', $lw))
                  $line[5][$j] = '<sub>'.$lw.'</sub>';
                  }
                  $line[5] []= $w[0];
                  break;
                  case 3:
                  $line[5] []= '<sup>'.$w[0].'</sup>';
                  break;
                  case -3:
                  $line[5] []= '<sub>'.$w[0].'</sub>';
                  break;
                  }
                */
                $lines[$min['i']] = $line;
            }
        }
    
        // 図表領域を登録
        foreach ($this->pdffigures as $fig) {
            if ($fig['Page'] == $npage + 1) {
                $title = '__'.$fig['Type'].' '.$fig['Number'].'__';
                $w0 = array(
                    $title,
                    $fig['ImageBB'][0],
                    $fig['ImageBB'][1],
                    $fig['ImageBB'][2],
                    $fig['ImageBB'][3],
                    '',
                    0);
                $lines []= array(
                    array($w0),
                    $fig['ImageBB'][0],
                    $fig['ImageBB'][1],
                    $fig['ImageBB'][2],
                    $fig['ImageBB'][3],
                    array($title),
                    $npage,
                    $fig['Type']
                );
            }
        }

        // y 順 -> x 順にソート
        usort($lines, function($l0, $l1) {
            $dy = $l0[2] - $l1[2];
            if ($dy != 0) return $dy;
            $dx = $l0[1] - $l1[1];
            return $dx;
        });


        /*
        foreach ($lines as $key => $line) {
          printf("%s:%s\n", $key, implode(' ', $line[5]));
        }
        die();
        */
    
        // ブロックレイアウト
        $blocks = array('left' => array(), 'right' => array());
        $layout_lines = array();
        $last_single_line = null;
        $line = null;
        $no_break_until_y = 0; // ここまでは左右どちらかに文字か図がある
        for ($i = 0; $i < count($lines); $i++) {
            $debug = false;
            $last_line = $line;
            $line = $lines[$i];
            if ($line[4] > $no_break_until_y) {
                $no_break_until_y = $line[4];
            }

            /*
            if ($i == 50) {//$line[1] == 442.479167 && $line[2] == 173.381913) {
                $debug = true;
            }
            */

            // インデントレイアウト変更のチェック
            if ($line[4] > $no_break_until_y
                && !$this->__is_continuable_lines($page_indents, $last_line, $line)) {
                // printf("%d: y:%.2f, '%s'\n", $i, $line[4], implode(' ', $line[5]));
                // 変更
                if (count($blocks['left']) + count($blocks['right']) > 0) {
                    foreach ($blocks['left'] as $l) $layout_lines []= $l;
                    foreach ($blocks['right'] as $l) $layout_lines []= $l;
                }
                $blocks = array('left' => array(), 'right' => array());
            }
            list($l0, $r0, $l1, $r1) = $this->__get_indent_by_y($page_indents, $line[4]);

            if (is_null($r0)) {
                // シングルカラム
                $layout_lines []= $line;
            } else {
                // ダブルカラム
                $is_continued = false;
                $is_clinging = false;
                $is_fig = preg_match('/^__.* \d+__$/', $line[5][0]);
                $margin = $this->wgap * $this->dpi; // 左右端から1文字分を許容範囲
                $is_left = ($line[3] < $l1 // 右段左端より左，はみ出るケースが多い
                            || $is_fig && $line[3] < $l1);  // または図表の場合は右段左端より左
                $is_right = ($line[1] > $l1 - $margin // 右段左端より右側
                             || $is_fig && $line[1] > $r0); // または図表の場合は左段右端よりより右

                if ($last_single_line) {
                    if ($this->__is_same_line($last_single_line[2], $last_single_line[4], $line[2], $line[4])) {
                        // 直前の全幅行の残り部分
                        $is_continued = true;
                    }
                    $is_clinging = ($line[2] - $last_single_line[4] < $last_single_line[4] - $last_single_line[2] && $line[2] - $last_single_line[4] < 20); // 直前の全幅行のすぐ下に続いている
                    if ($is_clinging) {
                        for ($j = $i - count($blocks['right']); $j < count($lines); $j++) {
                            if ($debug) {
                                printf("Comparing '%s'...", implode(' ', $lines[$j][5]));
                                echo "j:"; print_r($lines[$j]);
                            }
                            if ($lines[$j][2] > $line[4]) {
                                if ($debug) printf(" y:%d is larger than baseline:%d.\n", $lines[$j][2], $line[4]);
                                break;
                            }
                            if ($lines[$j][1] > $line[3] && $this->__is_same_line($line[2], $line[4], $lines[$j][2], $lines[$j][4])) {
                                // 同じ行の右側に別の行が存在しているので
                                // 左ブロック確定
                                if ($debug) printf(" clinging turned off.\n");
                                $is_clinging = false;
                                break;
                            }
                        }
                    }
                }
                if ($debug) {
                    printf("indents: %f,%f,%f,%f\n", $l0, $r0, $l1, $r1);
                    // echo json_encode($line), "\n";
                    // var_dump($line);
                    printf("is_continued: %s, is_clinging: %s, is_fig: %s, is_left: %s, is_right: %s\n", var_export($is_continued, 1), var_export($is_clinging, 1), var_export($is_fig, 1), var_export($is_left, 1), var_export($is_right, 1));
                    die();
                }
                if (!$is_continued && $is_left && !$is_clinging) {
                    // 左ブロック
                    $line[7] = 'left';
                    $blocks['left'] []= $line;
                    // if (!$is_fig) $text_area[4] = max($text_area[4], $line[3]);
                } else if (!$is_continued && $is_right) {
                    // 右ブロック
                    $line[7] = 'right';
                    $blocks['right'] []= $line;
                    // if (!$is_fig) $text_area[5] = min($text_area[5], $line[1]);
                } else {
                    // 全幅
                    $line[7] = 'single';
                    foreach ($blocks['left'] as $l) {
                        if ($this->__is_same_line($line[2], $line[4], $l[2], $l[4])) {
                            $l[7] = 'single'; // この行は左段ではなく全幅の段の左側一部
                        }
                        $layout_lines []= $l;
                    }
                    foreach ($blocks['right'] as $l) {
                        if ($this->__is_same_line($line[2], $line[4], $l[2], $l[4])) {
                            $l[7] = 'single'; // この行は右段ではなく全幅の段の右側一部
                        }
                        $layout_lines []= $l;
                    }
                    $blocks = array('left' => array(), 'right' => array());
                    $layout_lines []= $line;
                    $last_single_line = $line;
                }
            }
        }
        if (count($blocks['left']) + count($blocks['right']) > 0) {
            foreach ($blocks['left'] as $l) $layout_lines []= $l;
            foreach ($blocks['right'] as $l) $layout_lines []= $l;
        }
        $lines = $layout_lines;

        /*
        foreach ($lines as $key => $line) {
          printf("% 3d(%s):%s\n", $key, $line[7], implode(' ', $line[5]));
        }
        die();
        */

        // 同一行のブロックを結合する
        unset($new_lines);
        $new_lines = array();
        $cur = null;
        for ($i = 0; $i < count($lines); $i++) {
            if (is_null($cur)) {
                $cur = $lines[$i];
            } else if (abs($cur[4] - $lines[$i][4]) < 0.05 * $this->dpi) {
                // 同一行
                $cur[1] = min($cur[1], $lines[$i][1]);
                $cur[2] = min($cur[2], $lines[$i][2]);
                $cur[3] = max($cur[3], $lines[$i][3]);
                $cur[4] = max($cur[4], $lines[$i][4]);
                if ($cur[1] < $lines[$i][1]) {
                    $cur[0] = array_merge($cur[0], $lines[$i][0]);
                    $cur[5] = array_merge($cur[5], $lines[$i][5]);
                } else {
                    $cur[0] = array_merge($lines[$i][0], $cur[0]);
                    $cur[5] = array_merge($lines[$i][5], $cur[5]);
                }
            } else {
                $new_lines []= $cur;
                $cur = $lines[$i];
            }
        }
        if ($cur) $new_lines []= $cur;
        $lines = $new_lines;

        /*
        foreach ($lines as $key => $line) {
          printf("% 3d(%s):%s\n", $key, $line[7], implode(' ', $line[5]));
        }
        die();
        */

        // 行のテキストを修正する
        $min_sp = $this->mingap * $this->dpi; // これ以下の間隔は空白と認めない
        $max_sp = $this->wgap * $this->dpi;   // これ以上の間隔は空白
        for ($i = 0; $i < count($lines); $i++) {
            if (count($lines[$i][0]) < 2) {
                $text = implode('', $lines[$i][5]);
            } else {
                // 語の間の間隔の平均値から、空白を入れるか入れないか決める
                $space = 0;
                $n = 0;
                for ($j = 1; $j < count($lines[$i][0]); $j++) {
                    $sp = $lines[$i][0][$j][1] - $lines[$i][0][$j - 1][3];
                    if ($sp >= $min_sp && $sp <= $max_sp) {
                        $space += $sp;
                        $n++;
                    }
                }
                if ($n == 0) {
                    $space = $min_sp;
                } else {
                    $space = $space / $n / 2.0; // 平均間隔の半分以上あれば空白
                }
                $text = '';
                for ($j = 0; $j < count($lines[$i][0]); $j++) {
                    if ($j == 0) {
                        $sp = 0;
                        $lines[$i][0][0][8] = 'hd';
                    } else {
                        $cur_w = $lines[$i][0][$j];
                        $pre_w = $lines[$i][0][$j - 1];
                        $sp = $cur_w[1] - $pre_w[3]; // 単語間の隙間
                        if ($cur_w[6] != $pre_w[6] // フォントサイズが違う
                        || $sp >= $max_sp          // 単語間隔しきい値以上
                        || $sp >= $min_sp && $sp >= $space) { // 空白が空いている
                            $text .= ' ';
                            $lines[$i][0][$j][8] = 'sp';
                        } else {
                            $lines[$i][0][$j][8] = 'ns';
                        }
                    }              
                    $text .= $lines[$i][0][$j][0];
                }
            }
        
            $text = html_entity_decode($text); // ENT_COMPAT | ENT_QUOTES | ENT_HTML5;
            // $text = preg_replace('/[^A-Za-z0-9]/u', '', $text);
            $text = preg_replace('/[\x01-\x1f]/u', '', $text);
            $lines[$i][5] = $text;
        }

        // echo "PAGE {$npage}----\n"; print_r($lines); echo "\n";
        return $lines;
    }

    /**
     * 全ページの段落の左端、右端座標を求める
     * @param $page_list ページ情報の配列
     * @return [左段左端x, 左段右端x, 右段左端x, 右段右端x]
     */
    private function __getStructure(&$page_list) {
        $width = 0;
        $height = 0;
        // ベースラインでまとめた粗い行情報を作成
        $baselines = array();
        for ($npage = 0; $npage < count($page_list); $npage++) {
            $page_info = $page_list[$npage];
            $page_width = $page_info[1] * $this->dpi / 72; // この部分だけ 72dpi で計算されているので変換する
            $page_height = $page_info[2] * $this->dpi / 72;
            $width = $width > $page_width ? $width : $page_width;
            $height = $height > $page_height ? $height : $page_width;

            preg_match_all('/<word xMin="(.*?)" yMin="(.*?)" xMax="(.*?)" yMax="(.*?)" fName="(.*?)" fSize="(.*?)">(.*?)<\/word>/u', $page_info[3], $word_info, PREG_SET_ORDER);
            // 全単語をまず x 順、次に y 順にソート
            usort($word_info, function($w0, $w1) {
                $dx = $w0[1] - $w1[1];
                if ($dx != 0) return $dx;
                $dy = $w0[2] - $w1[2];
                return $dy;
            });
      
            // ベースラインでまとめた粗い行情報を作成
            foreach ($word_info as $w) {

                // 単語の位置（左端、右端、ページを連結したｙ座標）
                $x0 = floor($w[1]*10);
                $x1 = floor($w[3]*10);
                $by = floor($w[4] + $page_height * $npage);
                if (isset($baselines[$by])) {
                    $rx = $baselines[$by][count($baselines[$by]) - 1]['x1'];
                    if ($x0 - $rx > 10 * $this->column_gap * $this->dpi) { // 段落間に必要な間隔がある
                        $baselines[$by][] = array('x0'=>$x0, 'x1'=>$x1, 'w'=>$w[0]);
                    } else {
                        $baselines[$by][count($baselines[$by]) - 1]['x1'] = $x1;
                        $baselines[$by][count($baselines[$by]) - 1]['w'] .= ' ' . $w[0];
                    }
                } else {
                    $baselines[$by] = array(array('x0'=>$x0, 'x1'=>$x1, 'w'=>$w[0]));
                }
            }
        }
        ksort($baselines); // y 座標でソート
        /*
          $baselines: ベースライン座標でまとめた行ごとのブロック情報
          [y][{x0:左端x(x10), x1:右端x(x10), w:'表記'}]
        */

        $indents = $this->__getIndentsFromBaselines($baselines, $width, $height);
        return $indents;
    }

    /**
     * 粗くまとめられた単語集合の座標から
     * 左段左端、左段右端、右段左端、右段右端の座標を推測する
     * @param $baselines
     * @param $width      ページ幅
     * @param $height     ページ高さ
     * @param $yrange     array(<検査対象y0>, <検査対象y1>)
     *                    null の場合ページ全体
     * @return array(左段左端, 左段右端, 右段左端, 右段右端) 
     *         それぞれ x 座標のみ、座標は $this->dpi による
     */
    private function __getIndentsFromBaselines(&$baselines, $width, $height, $yrange = null) {
        $indents = array(array(0, $height, null));
        /*
          $blocks:    ブロック情報をフラットに展開した配列
          [{x0:左端x(x10), x1:右端x(x10), y1:下端y, w:'表記'}
        */
        $lefts = array();
        $rights = array();
        $blocks = array();
        foreach ($baselines as $by => $bl) {
            if (!is_null($yrange) && ($by < $yrange[0] || $by > $yrange[1])) {
                continue;
            }
            foreach ($bl as $b) {
                $b['y1'] = $by;
                $blocks []= $b;
            }
        }

        // 左端、右端を x 座標で集計
        foreach ($blocks as $b) {
            $x0 = $b['x0'];
            $x1 = $b['x1'];
            if (isset($lefts[$x0])) $lefts[$x0]++;
            else $lefts[$x0] = 1;
            if (isset($rights[$x1])) $rights[$x1]++;
            else $rights[$x1] = 1;
        }
        // 左端が1度しか出現しない x 座標は除去
        foreach ($lefts as $x0 => $freq) {
            if ($freq <= 2) unset($lefts[$x0]);
        }
        arsort($lefts);
        arsort($rights);
        // 左端、右端の上位50件のみ利用する（高速化）
        $arr = array();
        $i = 0;
        foreach ($lefts as $k => $v) {
            $arr[$k] = $v;
            $i++;
            if ($i > 50) break;
        }
        $lefts = $arr;
        unset($arr);
        $arr = array();
        $i = 0;
        foreach ($rights as $k => $v) {
            $arr[$k] = $v;
            $i++;
            if ($i > 50) break;
        }
        $rights = $arr;
        unset($arr);

        // ページの一部だけ段組みが違うパターンに対応
        if (isset($this->indents) && is_null($yrange)) {
            // ページ内レイアウトの計算
            $tabs = array();
            // 左右端が揃っている部分をチェック
            $h = 10 * $this->dpi / 72; // 行の高さは 10pt と仮定
            foreach ($lefts as $k => $v) {
                if ($v < 10) break;
                foreach ($blocks as $b) {
                    if ($k == $b['x0']) {
                        if (!isset($tabs[$k])) {
                            $tabs[$k] = array($b['y1'] - $h, $b['y1']);
                        }
                        $tabs[$k] = array(min($b['y1'] - $h, $tabs[$k][0]),
                                          max($b['y1'], $tabs[$k][1]));
                    }
                }
            }

            if (count($tabs) > 2) { // 2カラムなら2つ存在する
                // ページ内にインデント位置が異なる箇所がある
                $regions = array(0);
                foreach ($tabs as $t) {
                    $regions []= $t[0];
                    $regions []= $t[1];
                }
                $regions []= $height;
                sort($regions);

                $regions2 = array();
                $y0 = $regions[0];
                for ($i = 0; $i < count($regions) - 1; $i++) {
                    $y1 = $regions[$i + 1];
                    if ($y1 - $y0 < 2.0 * $this->dpi) {
                        // 2in 以下の高さしかない場合は無視
                        continue;
                    }
                    $c = 0; // その行のカラム数
                    foreach ($tabs as $t) {
                        if ($t[1] > $y0 && $t[0] < $y1) {
                            $c++; 
                        }
                    }
                    if ($c > 0) {
                        $regions2 [] = array($y0, $y1, $c);
                    }
                    $y0 = $y1 + 1;
                }

                for ($i = 0; $i < count($regions2); $i++) {
                    list($y0, $y1, $c) = $regions2[$i];
                    if ($c < 2) {
                        continue;
                    }
                    if ($i > 0 && $regions2[$i - 1][2] < 2) {
                        $y0 -= 0.5 * $this->dpi; // 0.5in マージン
                    }
                    if ($i < count($regions2) - 1 && $regions[$i + 1][2] < 2) {
                        $y1 += 0.5 * $this->dpi;
                    }
                    $partial_indents = $this->__getIndentsFromBaselines($baselines, $width, $height, array($y0, $y1));
                    $indents []= array(
                        $y0, // 開始 y 座標
                        $y1, // 終了 y 座標
                        $partial_indents[0][2],
                    );
                }

                // 0.5in マージンが重複している区間があれば調整
                for ($i = 1; $i < count($indents) - 1; $i++) {
                    if ($indents[$i][1] > $indents[$i + 1][0]) {
                        $border = ($indents[$i][1] + $indents[$i + 1][0]) / 2;
                        $indents[$i][1] = $border;
                        $indents[$i + 1][0] = $border + 1;
                    }
                }
            }
        }
        
        // 全ページから求めた縦位置から許容される誤差
        $margin = $this->column_gap * $this->dpi;
        if (!is_null($this->indents)) {
            $page_indent = $this->indents[0][2];
        } else {
            $page_indent = null;
        }

        // 左段左端を決める
        $crosses = array();
        foreach ($lefts as $x => $freq) {
            if ($x > $width * 10 / 3) continue; // ページの左1/3にない
            if (!is_null($page_indent) AND abs($x / 10 - $page_indent[0]) > $margin) {
                continue; // ずれすぎ
            }
            $range = array(0, $x);  // この範囲と交差するブロック数を求める
            $crosses[$x] = 0;
            foreach ($blocks as $b) {
                if ($b['x1'] < $range[0] || $b['x0'] > $range[1]) continue;
                $crosses[$x]++;
            }
        }
        asort($crosses);
        $l0 = 0;
        foreach ($crosses as $l0 => $freq) break; // 最も交差しない座標を l0 とする
      
        // 右段右端を決める
        $scores = array();
        foreach ($rights as $x => $freq) {
            if ($x < $width * 20 / 3) continue; // ページの右1/3にない
            if (!is_null($page_indent) AND abs($x / 10 - $page_indent[3]) > $margin) {
                continue; // ずれすぎ
            }
            $range = array($x + 1, $width * 10); // +1 は切り捨て誤差
            $scores[$x] = $freq;  // 端が一致するとプラス１
            foreach ($blocks as $b) {
                if ($b['x1'] < $range[0] || $b['x0'] > $range[1]) continue;
                $scores[$x] -= 2; // 交差するとマイナス２
            }
        }
        arsort($scores);
        $r1 = null;
        $max_score = -999;
        foreach ($scores as $r => $score) {
            if ($score < $max_score) {
                continue;
            }
            $max_score = $score;
            if (is_null($r1) || $rights[$r] > $rights[$r1]) {
                $r1 = $r;
            }
        }
        unset($scores);

        // 左段右端と右段左端を決める
        $l1 = null;
        $r0 = null;
        $crosses = array();
        $matches = array();
        if (!is_null($page_indent)) {
            // 全ページから計算した左段右端と右段左端
            $x0 = $page_indent[1] * 10;
            $x1 = $page_indent[2] * 10;
            $k = ($x0 + 1).','.$x1;
            $matches[$k] = 5; // 5行一致していると下駄をはかせる
            $crosses[$k] = 0;
            foreach ($blocks as $bl) {
                // 交差する行数を数える
                if ($bl['x1'] < $x0 || $bl['x0'] > $x1) continue;
                $crosses[$k]++;
            }
        }
        foreach ($rights as $x0 => $freq_r) {
            if ($x0 < $width * 10 / 4 || $x0 > $width * 30 / 4) continue;
            if (!is_null($page_indent) && is_null($yrange) && abs($x0 / 10 - $page_indent[1]) > $margin) {
                continue; // ずれすぎ
            }
            foreach ($lefts as $x1 => $freq_l) {
                if (!is_null($page_indent) && is_null($yrange) && abs($x1 / 10 - $page_indent[2]) > $margin) {
                    continue; // ずれすぎ
                }
                if ($x1 <= $x0 + 10 * $this->column_gap * $this->dpi || $x1 > $width * 30 / 4) continue; // 段落間には最低 0.15inch の間隔が必要
                $range = array($x0 + 1, $x1); // +1は切り捨て誤差
                $k = ($x0 + 1).','.$x1;
                if (!isset($crosses[$k])) {
                    $crosses[$k] = 0;
                    $matches[$k] = 0;
                }
                foreach ($baselines as $by => $bl) {
                    if (!is_null($yrange) && ($by < $yrange[0] || $by > $yrange[1])) {
                        continue;
                    }
                    for ($i = 0; $i < count($bl); $i++) {
                        // 左右段落で完全にY座標が一致している組に対し、
                        // 右段左端が区切りの右側に一致していて、なおかつ
                        // 左段右端が区切りの左側より左にある（交差しない）
                        // 行数を数える
                        if ($bl[$i]['x0'] == $x1 && (
                            $i == 0 || $bl[$i - 1]['x1'] <= $x0)) {
                            $matches[$k]++;
                            break;
                        }
                    }
                    for ($i = 0; $i < count($bl); $i++) {
                        // もう一つの指標：単純に交差する行数を数える
                        if ($bl[$i]['x1'] <= $x0 || $bl[$i]['x0'] >= $x1) continue;
                        $crosses[$k]++;
                    }
                }
            }
        }
        arsort($matches);
        $max_score = -999;
        $max_gap = 0;
        foreach ($matches as $k => $freq) {
            $score = $freq - $crosses[$k] * 5; // 交差があると-5点
            /*
            if (!is_null($this->indents) && isset($yrange[0]) && $yrange[0] > 770) {
                printf("%s : %d (freq:%d, cross:%d)\n", $k, $score, $freq, $crosses[$k]);
            }
            */
            if ($score >= $max_score) {
                // 同点の場合は頻度が低い＝交差が少ない方を選ぶ
                list($r0, $l1) = explode(',', $k);
                $max_score = $score;
            }
        }
        // asort($crosses); print_r($crosses);
        // 10倍して計算していたので dpi に戻す
        $l0 = $l0 / 10.0;
        if (isset($r1)) {
            $r1 = $r1 / 10.0;
        } else {
            $r1 = $width - $l0;
        }
        if (!is_null($r0)) {
            $r0 = $r0 / 10.0;
            $l1 = $l1 / 10.0;
        }

        if (!true && !is_null($this->indents) && $yrange[0] > 770) {
            print_r($this->indents);
            echo "\nleft = "; print_r($lefts);
            echo "\nright = "; print_r($rights);
            echo "\nmatches = "; print_r($matches);
            echo "\ncrosses = "; print_r($crosses);
            echo "\nlimit = ";
            print_r(array('l0'=>$l0, 'r0'=>$r0, 'l1'=>$l1, 'r1'=>$r1));
            die();
        }

        $indents[0][2] = array($l0, $r0, $l1, $r1);

        // 部分インデントでページ全体と一致するものは削除
        for ($i = 1; $i < count($indents); $i++) {
            $ident = true;
            for ($j = 0; $j < count($indents[0][2]); $j++) {
                // プラスマイナス0.5in以内であれば同じインデント位置とみなす
                if (abs($indents[0][2][$j] - $indents[$i][2][$j]) > 0.5 * $this->dpi) {
                    $ident = false;
                    break;
                }
            }
            /*
            printf("i:%d => %s\n", $i, var_export($ident, true));
            print_r($indents[0][2]);
            print_r($indents[$i][2]);
            */
            if ($ident) {
                $indents[$i] = null;
            }
        }
        $indents = array_values(array_filter($indents));
        
        return $indents;
    }

    /**
     * ページ内段落の左端、右端座標を求める
     * @param $word_info 単語情報の配列
     * @param $width     ページ幅
     * @param $height    ページ高さ
     * @param $is_last_page 最終頁の場合 true
     * @return  {text_area: テキスト領域 [xmin, ymin, xmax, ymax, 左段xmax, 右段xmin]
     *           indent: 段落x座標 [左段左端x, 左段右端x, 右段左端x, 右段右端x]}
     */
    private function __getPageStructure(&$word_info, $width, $height, $is_last_page) {
        // 文字領域の取得と
        // ベースラインでまとめた粗い行情報を作成
        $baselines = array();
        $lefts = array();
        $rights = array();
        $text_area = array($width, $height, 0, 0, $width, 0, $width);
        foreach ($word_info as $w) {
            $text_area[0] = min($text_area[0], $w[1]);
            $text_area[1] = min($text_area[1], $w[2]);
            $text_area[2] = max($text_area[2], $w[3]);
            $text_area[3] = max($text_area[3], $w[4]);
            $x0 = floor($w[1]*10);
            $x1 = floor($w[3]*10);
            $by = floor($w[4]);
            if (isset($baselines[$by])) {
                $rx = $baselines[$by][count($baselines[$by]) - 1]['x1'];
                if ($x0 - $rx > 10 * $this->column_gap * $this->dpi) { // 段落間に必要な間隔がある
                    $baselines[$by][] = array('x0'=>$x0, 'x1'=>$x1, 'w'=>$w[0]);
                } else {
                    $baselines[$by][count($baselines[$by]) - 1]['x1'] = $x1;
                    $baselines[$by][count($baselines[$by]) - 1]['w'] .= ' ' . $w[0];
                }
            } else {
                $baselines[$by] = array(array('x0'=>$x0, 'x1'=>$x1, 'w'=>$w[0]));
            }
        }
        ksort($baselines);

        $indents = $this->__getIndentsFromBaselines($baselines, $width, $height);

        // 最終頁に左段しかない場合への対応
        if ($is_last_page
        && is_null($indents[0][2][2]) && $indents[0][2][3] < $width * 0.6) {
            $indents[0][2] = array(
                $indents[0][2][0],
                $indents[0][2][3],
                null,
                null
            );
        }

        return array("text_area" => $text_area, "indent" => $indents);
    }

    /**
     * フォントクラスを作成
     * @param $lines   行情報の配列
     * @return フォントクラス
     * @sideeffect  単語情報[7]にフォントIDが埋め込まれる
     * フォントクラス：
     *   {<フォントキー>:<フォントID>, ...}
     */
    private function __createFontClasses(&$lines) {
        // フォントクラスを作成
        $font_classes = array();
        for ($i = 0; $i < count($this->lines); $i++) {
            $line = $this->lines[$i];
            for ($j = 0; $j < count($line[0]); $j++) {
                $wordinfo = $line[0][$j];
                if (count($wordinfo) > 5) {
                    if (preg_match('/.+\+(.*)/', $wordinfo[5], $matches)) {
                        $fontname = $matches[1];
                    } else {
                        $fontname = $wordinfo[5];
                    }
                    $fontsize = round($wordinfo[6] * 72.0 / $this->dpi, 1);
                    $fontkey = sprintf("%s\t%.1f", $fontname, $fontsize);
                    if (!isset($font_classes[$fontkey])) {
                        $font_classes[$fontkey] = count($font_classes) + 1;
                    }
                    $this->lines[$i][0][$j][7] = $font_classes[$fontkey];
                }
            }
        }
        return $font_classes;
    }

    /**
     * 標準的な行の高さと行間を取得する
     * @param $lines   行情報の配列
     * @return {line_height: 行の高さ, line_space: 行間}
     **/
    private function __getLineHeightAndSpace(&$lines) {
        $line_heights = array();
        $line_breaks = array();
        $prev = null;
        foreach ($lines as $line) {
            $height = floor(($line[4] - $line[2]) * 10);
            if (isset($line_heights[$height])) $line_heights[$height]++;
            else $line_heights[$height] = 1;
            if (!is_null($prev)
            && abs($prev[1] - $line[1]) < 0.05 * $this->dpi) {
                $brk = floor(($line[2] - $prev[4]) * 10);
                // print_r(array("prev"=>$prev, "line"=>$line, "brk"=>$brk));
                if (isset($line_breaks[$brk])) $line_breaks[$brk]++;
                else $line_breaks[$brk] = 1;
            }
            $prev = $line;
        }
        $max_heights = array('freq'=>0, 'height'=>0);
        foreach ($line_heights as $height => $freq) {
            if ($freq > $max_heights['freq']) {
                $max_heights['freq'] = $freq;
                $max_heights['height'] = $height / 10.0;
            }
        }
        $max_breaks = array('freq'=>0, 'gap'=>0);
        foreach ($line_breaks as $gap => $freq) {
            if ($freq > $max_breaks['freq']) {
                $max_breaks['freq'] = $freq;
                $max_breaks['gap'] = $gap / 10.0;
            }
        }

        return array(
            'line_height' => $max_heights['height'],
            'line_space'  => $max_breaks['gap']
        );
    }

    /**
     * pdftotext -bbox の出力する HTML から
     * ページの情報を抽出して配列として返す
     * @param $html   HTML テキスト
     * @return  [ [<ページ最初の行>, <width>, <height>, <page の innerHtml>] ]
     */
    private function __getPageContents($html) {
      $page_info = array();
      $pp = array();
      $inner = false;
      foreach (explode("\n", $html) as $line) {
	if ($inner == false) {
	  if (preg_match('/<page width="([\d\.]+)" height="([\d\.]+)">(.*)/', $line, $m)) {
	    $pp = array($m[0], $m[1], $m[2]);
	    $inner = true;
	    $page_content = $m[3];
	  }
	} else {
	  if (preg_match('/(.*)<\/page>/', $line, $m)) {
	    $page_content .= $m[1];
	    $pp[3] = $page_content;
	    $page_info []= $pp;
	    unset($pp);
	    $inner = false;
	  } else {
	    $page_content .= $line . "\n";
	  }
	}
      }
      return $page_info;
    }
  
    /**
     * PDF をレイアウト解析し、単語情報と行単位の構造情報を生成する
     * @param   $pdfpath     PDF のファイルパス
     * @param   $pdffigures  pdffigures で取得した図表領域情報
     *                       null の場合 pdffigures を実行
     *                       pdffigures を実行したくない場合は [] を渡す
     * @param   $bbox        pdftotext で取得した bbox 情報
     *                       null の場合 pdftext を実行
     * @return  無し  結果はメンバ変数に格納される
     *          $this->lines 行単位の情報
     *          $this->pages ページの情報
     **/
    public function analyze($pdfpath, $pdffigures = null, $bbox = null) {
        $this->__reset();

        // pdffigures を実行して図表領域を取得する
        if (is_null($pdffigures)) {
            $this->pdffigures = $this->pdfFigures($pdfpath);
        } else {
            $this->pdffigures = $pdffigures;
        }
        for ($i = 0; $i < count($pdffigures); $i++) {
            $fig = $pdffigures[$i];
            $figdpi = $fig['DPI'];
            if ($figdpi != $this->dpi) {
                // BBOX の dpi をに変換
                $pdffigures[$i]['ImageBB'] = array(
                    $fig['ImageBB'][0] * $this->dpi / $figdpi,
                    $fig['ImageBB'][1] * $this->dpi / $figdpi,
                    $fig['ImageBB'][2] * $this->dpi / $figdpi,
                    $fig['ImageBB'][3] * $this->dpi / $figdpi,
                );
            }
        }
    
        // pdftotext を実行して単語情報を取得する
        if (is_null($bbox)) { 
            $this->bbox = $this->pdfToText($pdfpath);
        } else {
            $this->bbox = $bbox;
        }

        // bbox ファイルを解析して単語情報を得る
        // 2017-03-26
        // preg_match_all('/<page width="([\d\.]+)" height="([\d\.]+)">(.*?)<\/page>/us', $this->bbox, $p, PREG_SET_ORDER);
        // でいいはずが古い PHP では正しい結果が得られないので関数化
        $p = $this->__getPageContents($this->bbox);
        $this->indents = $this->__getStructure($p);
    
        for ($npage = 0; $npage < count($p); $npage++) {
            if (($this->target_page_from > 0 && $this->target_page_from > $npage + 1)
            || ($this->target_page_to > 0 && $this->target_page_to < $npage + 1)) {
                continue;
            }
            $page_info = $p[$npage];
            $is_last_page = ($npage == count($p) - 1); // 最終頁フラグ
            $page_structure = $this->__setLinesInOnePage($page_info, $npage, $is_last_page);

            $this->lines = array_merge($this->lines, $page_structure['lines']);
            $this->pages[$npage]['text_area'] = $page_structure['text_area'];
            $this->pages[$npage]['indent'] = $page_structure['indent'];
        }
        // print_r($this->lines); die();

        // フォントクラスを生成し、単語情報に埋め込む
        $this->font_classes = $this->__createFontClasses($this->lines);

        // 最も頻度の高い行の高さと行間を取得する
        $line_info = $this->__getLineHeightAndSpace($this->lines);
        $this->line_height = $line_info['line_height'];
        $this->line_space  = $line_info['line_space'];

        // 行情報出力（デバッグ用）
        $article = array('pages'=>$this->pages, 'line height'=>$this->line_height, 'line space'=>$this->line_space, 'lines'=>$this->lines, 'font_classes'=>$this->font_classes);
        if (false) {
            @file_put_contents('this_lines_debug.json', json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
        }
    }

}

/**
 * レイアウト解析のデバッグ用コード
 */
if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
    $pdffigures = null;
    $page = 0;
    if (count($argv) == 0) {
        // For IPSJ
        $target = 'pdf/103097_1_1.pdf';
        // require_once('AbekawaPdffigures.php');
        // $pdffigures =  AbekawaPdffigures::get('pdf/' . $target . '.pdf');

        // For ACL Anthology
        // $target = "pdf/E06-1035.pdf";
        // $pdffigures = null;
    } else {
        for ($i = 1; $i < count($argv); $i++) {
            if (preg_match('/^\d+$/', $argv[$i])) {
                $page = $argv[$i];
            } else if (preg_match('/pdf$/i', $argv[$i])) {
                $target = $argv[$i];
            } else {
                echo "Unrecognized parameter '", $argv[$i], "'. (skipped)";
            }
        }
    }

    $p = new LayoutAnalyzer();
    $p->setTargetPageFrom($page); // 1 が最初のページ
    $p->setTargetPageTo($page);
    $p->analyze($target, $pdffigures);
    echo "--- indents ---\n";
    print_r($p->getIndents());
    echo "--- pages ---\n";
    print_r($p->getPages());
    echo "--- lines ---\n";
    $lines = $p->getLines();
    foreach ($lines as $line) {
        echo $line[5], "\n";
    }
}
