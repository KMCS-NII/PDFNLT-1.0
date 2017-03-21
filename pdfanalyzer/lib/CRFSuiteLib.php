<?php
/**
 * @file CRFSuiteLib.php
 * @description crfsuite の管理クラス
 */

require_once(dirname(__FILE__). '/PdfAnalyzer.php');

/**
 * @class CRFSuiteLib
 */
class CRFSuiteLib
{

    /**
     * コンストラクタ
     * @param $modelfile   モデルファイル名
     */
    function __construct($modelfile = "crfsuite.model") {
        $this->modelfile = $modelfile;
    }

    /**
     * 素性ファイルを読み込む
     * @param $filename   ラベル－素性を記録したファイル名
     * @param $range      前後何行まで素性を利用するか
     * @return 読み込んだデータ（テキスト形式）
     **/
    private function __readFeatureFile($filename, $range = 2) {
        $fh = fopen($filename, 'r');
        if (!$fh) {
            throw new RuntimeException("Cannot read file '{$filename}.");
        }

        $lines = array();
        while ($line = fgets($fh)) {
            $line = trim($line);
            if (preg_match('/^' . PdfAnalyzer::UNLABELLED_LINE . '/', $line)) {
                // 行頭が '#' の行は学習に利用しない
                continue;
            }
            $args = explode("\t", $line);
            if (count($args) < 3) {
                throw new RuntimeException("Invalid line:'{$line}' in file:'{$filename}'");
            }
            $words = explode(' ', $args[1]);
            preg_match_all('/(\S+)=(\S+)/u', $args[2], $matches, PREG_SET_ORDER);
            $features = array();
            foreach ($matches as $m) {
                $features[$m[1]] = $m[2];
            }
            $lines []= array(
                "label" => $args[0],
                "surface" => $args[1],
                "features" => $features,
            );
        }
        fclose($fh);

        $text = "";
        $nlines = count($lines);
        for ($i = 0; $i < $nlines; $i++) {
            $f = array();
            for ($r = - $range; $r <= $range; $r++) {
                if ($i + $r < 0 || $i + $r >= $nlines) {
                    continue;
                }
                foreach ($lines[$i + $r]['features'] as $k => $v) {
                    $f []= sprintf("%s[%d]=%s", $k, $r, $v);
                }
            }
            $text .= sprintf("%s\t%s\n", $lines[$i]['label'], implode("\t", $f));
        }
        $text .= "\n";
        return $text;
    }

    /**
     * モデルを学習する
     * @param $filenames   教師データファイル名の配列
     * @return なし（学習結果は $this->modelfile に格納される）
     **/
    public function learn($filenames) {
        $tmp = tempnam('/tmp', 'crfsuite_learn');
        $fh = fopen($tmp, 'w');
        foreach ($filenames as $filename) {
            $content = $this->__readFeatureFile($filename);
            fwrite($fh, $content, strlen($content));
        }
        fclose($fh);

        $cmd = sprintf("crfsuite learn -m %s %s", $this->modelfile, $tmp);
	echo "Executing command;\n{$cmd} ...\n";
        system($cmd);
        unlink($tmp);
    }

    /**
     * ラベルを付与する
     * @param $filename   ラベルを付与する素性ファイル名
     * @return  ラベル付与済み素性データ（2次元配列）
     **/
    public function tagging($filename) {
        $tmp = tempnam('/tmp', 'crfsuite_tagging');
        $fh = fopen($tmp, 'w');
        $content = $this->__readFeatureFile($filename);
        fwrite($fh, $content, strlen($content));
        fclose($fh);
    
        $cmd = sprintf("crfsuite tag -m %s %s", $this->modelfile, $tmp);
        $results = array();
        exec($cmd, $results);
        unlink($tmp);

        $tagged = array();
        $fh = fopen($filename, 'r');
        $ln = 0;
        while ($line = fgets($fh)) {
            $line = trim($line);
            $args = explode("\t", $line);
            $args[0] = $results[$ln];
            $tagged []= $args; //sprintf("%s", implode("\t", $args));
            $ln++;
        }
        fclose($fh);
        return $tagged;
    }
  
}
