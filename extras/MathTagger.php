<?php

class MathTaggerException extends Exception {}

class MathTagger {

    function __constructor() {
        $this->docid = '';
        $this->feature_list = array();
        $this->text_list = array();
    }

    /*
    // 語情報からフォント名を作成する
    static public function get_fontkey($wordinfo) {
    if (count($wordinfo) > 5) {
    if (preg_match('/.+\+(.*)/', $wordinfo[5], $matches)) {
    $fontname = $matches[1];
    } else {
    $fontname = $wordinfo[5];
    }
    $fontsize = round($wordinfo[6] * 72.0 / 100.0, 1);
    $fontkey = sprintf("%s-%.1f", $fontname, $fontsize);
    } else {
    $fontkey = '';
    }
    return $fontkey;
    }
    */

    // fontspec からフォント名を作成する
    static public function get_fontkey_from_fontspec($fontspec) {
        $fontsize = $fontspec['size'];
        $fontkey = $fontspec['name'].'-'.substr($fontspec['size'], 0, strlen($fontspec['size'] - 2));
        return $fontkey;
    }

    // 数式の可能性がある文字列を含むかどうか
    static public function may_equation($string) {
    if (preg_match('/[<->\(\)\{\}\[\]Α-Ωα-ω]/u', $string)) { // ギリシャ文字を含む
    return true;
    }
    if (preg_match('/(\xe2[\x88-\x8b][\x80-\xbf])/', $string)) { // 数学記号を含む e28880-e28bbf
    return true;
    }
    return false;
    }

    // タグ付けした xhtml を標準出力に送る
    // ToDo: 正規表現で置き換えるのではなく
    //       XML 階層に埋め込む
    public function outputTaggedXhtml($xhtml, $model) {
        $xml = simplexml_load_file($xhtml);
        try {
            $this->fromXml($xml);
        } catch (MathTaggerException $e) {
            // MathTagged XHTML なのでそのまま出力する
            $content = file_get_contents($xhtml);
            echo $content;
            return;
        }
        $labels = $this->tag($model);
        $xhtml_text = file_get_contents($xhtml);
        for ($i = 0; $i < count($labels); $i++) {
            $wid = $labels[$i][0];
            $val = $labels[$i][1];
            if ($val != 'O') {
                $keypattern = "/id=\"".$wid."\"/";
                $xhtml_text = preg_replace($keypattern, '$0'.' data-math="'.$val.'"', $xhtml_text, 1);
            }
        }
        echo $xhtml_text;
        // copy($xhtml, $xhtml . '.orig');
        // file_put_contents($xhtml, $xhtml_text);
    }

    // crfsuite でタグ付けする
    public function tag($modelfile) {
        if (!$this->docid) {
            echo "The docid is empty.\n";
            die();
        }
        $seq_no = 1;
        $tmp_in  = dirname(__FILE__) . "/tmp_aclview_crfsuite_in";
        $tmp_out = dirname(__FILE__) . "/tmp_aclview_crfsuite_out";
        $fh = fopen($tmp_in, "w");
        $wids = array();
        foreach ($this->feature_list as $p_id => $paragraph) {
            for ($i = 0; $i < count($paragraph); $i++) {
                list($w_id, $spell, $start, $end, $features) = $paragraph[$i];
                fprintf($fh, "%s\t%s\n", $w_id, implode("\t", explode(' ', $features)));
                $wids[] = $w_id;
            }
        }
        fclose($fh);

        $cmd = "crfsuite tag -m {$modelfile} {$tmp_in} > {$tmp_out}";
        `{$cmd}`;
        $fh = fopen("{$tmp_out}", "r");
        $taggs = array();
        while ($line = fgets($fh)) {
            if ($line != '') {
                $taggs[] = trim($line);
            }
        }
        fclose($fh);
        unlink($tmp_in);
        unlink($tmp_out);

        $labels = array();
        for ($i = 0; $i < count($wids); $i++) {
            $labels[$i] = array($wids[$i], $taggs[$i]);
        }
        return $labels;
    }

    // 語の素性を取得する
    public function fromXml($xml) {
        $this->feature_list = array();
        $this->text_list = array();
        $fontspecs = array();
        $font_freqs = array(); // フォントごとの出現頻度
        $equation_fonttypes = array();
        $equation_spells = array();
        $equation_fontspells = array();

        // docid を取得
        $this->docid = '';
        foreach ($xml->head->meta as $meta) {
            foreach ($meta->attributes() as $k => $v) {
                if ($k == 'docid') {
                    $this->docid = $v;
                    break;
                }
            }
            if ($this->docid) break;
        }
        $seq_no = 1; // シーケンシャル番号

        // フォント情報を取得
        foreach ($xml->head->ftypes->fontspec as $fontspec) {
            $font = array();
            foreach ($fontspec->attributes() as $k => $v) {
                $font[$k] = (string)$v[0];
            }
            $fontspecs[$font['id']] = $font;
        }

        // スキャン１回目
        foreach ($xml->body->div as $section) {
            foreach ($section->div as $box) {
                $boxtype= (string)$box['data-name'];
                foreach ($box->p as $paragraph) {
                    foreach ($paragraph->span as $word) {
                        if (isset($word['data-math'])) {
                            throw new MathTaggerException("Math tagged xhtml");
                        }
                        if (!isset($word['data-ftype'])) continue;
                        $ftype = (int) $word['data-ftype'];
                        $w = (string) $word[0];

                        if ($boxtype === 'Equation') {
                            // 数式内に出現した情報を収集
                            $equation_fonttypes[$ftype] = true;
                            $equation_spells[$w] = true;
                            $equation_fontspells[$ftype.'_'.$w] = true;
                        }

                        // フォント出現頻度カウント
                        if (!isset($font_freqs[$ftype])) {
                            $font_freqs[$ftype] = 0;
                        }
                        $font_freqs[$ftype]++;
                    }
                }
            }
        }

        // 本文フォントを決定
        $maxfreq = -1;
        $mainfont = null;
        foreach ($font_freqs as $ftype => $freq) {
            if ($freq > $maxfreq) {
                $mainfont = $ftype;
                $maxfreq = $freq;
            }
        }

        // スキャン2回目
        foreach ($xml->body->div as $section) {
            foreach ($section->div as $box) {
                $boxtype= (string)$box['data-name'];
                // if ($boxtype == 'Equation') {
                if (! in_array(mb_strtolower($boxtype, 'UTF-8'), array('title', 'abstract', 'body', 'listitem', 'caption'))) {
                    continue; // 数式ブロックは除外する
                }
                foreach ($box->p as $paragraph) {
                    $may_math_paragraph = false;
                    $p_id = (string)$paragraph['id'];
                    $p_text = '';
                    $p_cursor = 0;
                    $map = array();
                    $features = array();
                    $text_output_content = '';
                    $is_url = false;

                    // 語情報を取得
                    $nwords = count($paragraph->span);
                    for ($wi = 0; $wi < $nwords; $wi++) {
                        $word = $paragraph->span[$wi];
                        if (!isset($word['data-ftype'])) continue;
                        $ftype = (int) $word['data-ftype'];

                        // マップファイル用情報
                        $spell = html_entity_decode((string) $word[0]);
                        $len = mb_strlen($spell, 'UTF-8');
                        if ($wi > 0) {
                            $p_text .= ' ';
                            $p_cursor ++;
                        }
                        $p_text .= $spell;
                        $map[(string)$word['id']] = array($p_cursor, $p_cursor + $len);
                        $p_cursor += $len;

                        // URL の一部かどうかを判定
                        if ($is_url) {
                            if ($word['data-space'] == 'space') {
                                $is_url = false;
                            }
                        } else if ($spell == 'http' || $spell == 'https') {
                            $is_url = true;
                        }
                        // if ($is_url) echo $spell, "\n";
          
                        // 数式中で利用されたフォント、文字列のチェック
                        $fontkey = self::get_fontkey_from_fontspec($fontspecs[$ftype]);
                        $feature = array('w'=>$spell, 'f'=>$fontkey, 'len'=>strlen($spell));
                        $fontspell = $fontkey.'_'.$spell;
                        $feature['equ_font'] = (isset($equation_fonttypes[$fontkey])) ? 't' : 'f';
                        $feature['equ_spell'] = (isset($equation_spells[$spell])) ? 't' : 'f';
                        $feature['equ_fontspell'] =  (isset($equation_fontspells[$fontspell])) ? 't' : 'f';
                        // アルファベット？
                        $feature['alpha'] = (preg_match('/^[A-Za-z]+$/', $spell)) ? 't' : 'f';
                        // ギリシャ文字を含む？
                        $feature['greek'] = (preg_match('/[Α-Ωα-ω]/u', $spell)) ? 't' : 'f';
                        // 数学記号を含む？
                        $feature['algebra'] = (preg_match('/([<->]|\xe2[\x88-\x8b][\x80-\xbf])/u', $spell)) ? 't' : 'f';
                        // 一文字？
                        $feature['singlechar'] = (mb_strlen($spell, 'UTF-8') == 1) ? 't' : 'f';
                        /*
                          if ($feature['greek'] == 't') {
                          printf("'%s' はギリシャ文字を含む\n", $spell);
                          }
                          if ($feature['algebra'] == 't') {
                          printf("'%s' は数学記号を含む\n", $spell);
                          }
                        */
            
                        // 本文フォント？
                        $feature['mainfont'] = ($ftype == $mainfont) ? 't' : 'f';

            
                        // 領域の名前（caption, referenceなど）
                        $feature['box-type'] = $boxtype;

                        // URL の一部
                        $feature['url'] = $is_url ? 't' : 'f';
            
                        $features[$wi] = $feature;

                        // 数式を含むパラグラフの可能性チェック
                        if (preg_match('/CMMI/', $fontkey)) { // CMMI フォントを含む
                            $may_math_paragraph = true;
                        } else if ($feature['equ_font'] == 't') { // 数式と同じフォント
                            $may_math_paragraph = true;
                        } else if (self::may_equation($spell)) {
                            $may_math_paragraph = true;
                        }
                    }

                    // 素性出力
                    if ($boxtype == 'Equation') {
                        $is_equation = 'E';
                    } else {
                        $is_equation = 'N';
                    }
                    $paragraph_features = array();
                    for ($wi = 0; $wi < $nwords; $wi++) {
                        $word = $paragraph->span[$wi];
                        $w_id = (string) $word['id'];
                        $spell = html_entity_decode((string) $word[0]);
                        if (!isset($map[$w_id])) continue; // 画像等領域
                        $f = '';
                        for ($i = - 3; $i < 4; $i++) {
                            if ($wi + $i < 0 || $wi + $i >= $nwords) continue;
                            if (isset($features[$wi + $i])) {
                                $feature = $features[$wi + $i];
                                foreach ($feature as $key => $value) {
                                    $f .= sprintf("%s[%d]=%s ", $key, $i, $value);
                                }
                            }
                        }
                        $paragraph_features []= array($w_id, $spell, $map[$w_id][0], $map[$w_id][1], $f);
                    }
                    $this->feature_list[$p_id] = $paragraph_features;
                    if (true || $may_math_paragraph) {
                        $this->text_list [$p_id]= $p_text;
                    }
                }
            }
        }
        return array($this->feature_list, $this->text_list);
    }
  
}

function usage() {
    $cmd = isset($argv[0]) ? $argv[0] : '<cmd>';
    echo "Usage: php {$cmd} <xml-filepath>\n";
    echo "\tThe math-tagged XML will be output to stdout.\n";
    die();
}
  
if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  
    if (count($argv) < 2) {
        usage();
    }

    $mt = new MathTagger();
    $mt->outputTaggedXhtml($argv[1], dirname(__FILE__).'/inline_math.model');
}
