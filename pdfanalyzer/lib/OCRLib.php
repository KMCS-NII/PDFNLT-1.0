<?php
/**
 * @file OCRLib.php
 * @description TesseractOCR Manager class
 */

/**
 * @class OCRLib
 */
class OCRLib
{

    private static $isOCRInstalled = null;
    
    static public function check() {
        if (is_null(self::$isOCRInstalled)) {
            self::$isOCRInstalled = `which tesseract`;
        }
        if (self::$isOCRInstalled) {
            echo "Use Teseract!\n";
        } else {
            echo "Cannot use Teseract!\n";
        }
        return self::$isOCRInstalled;
    }

    static private function __check_numeric($val) {
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = self::__check_numeric($v);
            }
            return $val;
        }
        if (preg_match('/^\-?\d+$/', $val)) {
            return (int)$val;
        } else if (preg_match('/^\-?[\d\.]+$/', $val)) {
            return (real)$val;
        }
        return $val;
    }

    static private function __get_hOCR_params($str) {
        $params = array();
        foreach (preg_split('/\s*;\s*/', $str) as $param) {
            $values = preg_split('/\s+/', $param);
            $key = array_shift($values);
            if (count($values) == 1) {
                $params[$key] = self::__check_numeric($values[0]);
            } else {
                $params[$key] = self::__check_numeric($values);
            }
        }
        return $params;
    }
    
    /**
     * Read PNG image file and return list of words with hocr attributes
     */
    static public function read($pngpath, $dpi=400) {
        $dpi_rate = $dpi / 72.0; // dot size in 'pt'
        
        if (!is_readable($pngpath)) {
            throw new RuntimeException("PNG file '{$pngpath}' is not readable.");
        }

        // Execute tesseract-ocr from shell
        $cmd = sprintf("tesseract %s stdout hocr", $pngpath);
        $results = array();
        exec($cmd, $results);
        $hocr = implode("\n", $results);

        // Extract <div> part
        $html_part = $hocr;
        if (!preg_match("/<div class='ocr_page'.*div>/is", $html_part, $m)) {
            return '';
        } else {
            $html_part = $m[0];
        }
        $xml = new SimpleXMLElement($html_part);

        // Extract words
        $words = array();
        foreach ($xml->xpath('//div[@class="ocr_carea"]') as $div_area) {
            $block_id = (string)$div_area['id'];
            foreach ($div_area->xpath('p[@class="ocr_par"]') as $p_par) {
                $par_id = (string)$p_par['id'];
                foreach ($p_par->xpath('span[@class="ocr_line"]') as $span_line) {
                    // hOCR parameters for the line
                    $line_id = (string)$span_line['id'];
                    $hocr_line_params = self::__get_hOCR_params($span_line['title']);
                    foreach ($span_line->xpath('span[@class="ocrx_word"]') as $span_word) {
                        $word_id = (string)$span_word['id'];
                        $word_text = '';
                        $strong_flag = false;
                        foreach ($span_word->children() as $text_element) {
                            if ($text_element->getName() == 'strong') {
                                $word_text .= trim((string)$text_element);
                                $strong_flag = true;
                            }
                        }
                        if ($word_text == '') {
                            $word_text = trim((string)$span_word);
                        }
                        if ($word_text != '') {
                            $hocr_word_params = array_merge(
                                array(
                                    "block_id" => $block_id,
                                    "par_id"   => $par_id,
                                    "line_id"  => $line_id,
                                    "id"       => $word_id,
                                    "text"     => $word_text,
                                    "strong"   => $strong_flag,
                                ),
                                $hocr_line_params,
                                self::__get_hOCR_params($span_word['title'])
                            );
                            $words []= $hocr_word_params;
                            /*
                              foreach ($hocr_line_params as $key => $value) {
                              if (!isset($hocr_word_params[$key])) {
                              $hocr_word_params[$key] = $value;
                              }
                              }
                            */
                        }
                    }
                }
            }
        }
        return array("hocr"=>$hocr, "words"=>$words);

        /*
        // Set position property
        $xml->addAttribute('style', 'position:relative;');

        // Delete empty divs
        foreach ($xml->div as $div) {
            $text = '';
            foreach ($div->xpath('p//span[@class="ocrx_word"]') as $word) {
                $text .= $word->__toString();
            }
            $text = trim($text);
            if (preg_match('/^\s+$/s', $text)) {
                unset($div);
            }
        }

        foreach ($xml->xpath('//span[@class="ocrx_word"]') as $word) {
            $title = (string)($word['title']);
            if (preg_match("/bbox\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $title, $m)) {
                $left = $m[1] / $dpi_rate;
                $top = $m[2] / $dpi_rate;
                $font_size = ($m[4] - $m[2]) / $dpi_rate;
                $style = sprintf("font-size:%.1fpt;position:absolute;left:%.2fpt;top:%.2fpt;", $font_size, $left, $top);
                $word->addAttribute('style', $style);
            }
            
        }

        return $xml->asXML();
        */
    }
}
