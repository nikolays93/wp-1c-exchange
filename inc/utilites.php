<?php

class ExchangeUtils
{
    private function __construct()
    {

    }

    public static function translit($s) {
        $s = strip_tags( (string) $s);
        $s = str_replace(array("\n", "\r"), " ", $s);
        $s = preg_replace("/\s+/", ' ', $s);
        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
        $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
        $s = str_replace(" ", "-", $s);
        return $s;
    }

    public static function write_to_file($value, $path, $mode = 'a'){
      if( empty($value) || !is_string($path) )
        return false;

        $fp = fopen($path, 'a');
        if($fp){
            fwrite($fp, serialize($value) . "\r\n" );
            fclose($fp);
        }
        else {
            echo "Файл не найден или не может быть записан.";
            return false;
        }

        return true;
    }

    public static function sanitizePrice( $string, $delimiter = '.' )
    {
        $arrPriceStr = explode($delimiter, $string);
        $price = 0;
        foreach ($arrPriceStr as $i => $priceStr) {
            if( sizeof($arrPriceStr) !== $i + 1 ){
                $price += (int)preg_replace("/\D/", '', $priceStr);
            }
            else {
                $price += (int)$priceStr / 100;
            }
        }

        return $price;
    }
}
