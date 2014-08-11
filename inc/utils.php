<?php

function build_sorter($key,$asc) {
    return create_function("\$a,\$b"," return ".($asc?"-":"")."strnatcmp(\$a->".$key.",\$b->".$key.");");
}

function is_assoc($array) 
{
    if(!is_array($array))return false;
    return (bool)count(array_filter(array_keys($array), 'is_string'));
}

function XML2ArrayGetKeyOrArray($xmlArray,$key)
{
    $toret = array();
    if(array_key_exists($key,$xmlArray)){
        if(is_assoc($xmlArray[$key])){
            $toret[] =   $xmlArray[$key]; 
        }else{
            $toret = $xmlArray[$key];
        }
    }
    return $toret;
}

function XML2Array ( $xml , $recursive = false )
{
    if ( ! $recursive ){
        $array = simplexml_load_string ( $xml ) ;
    } else {
        $array = $xml ;
    }
   
    $newArray = array () ;
    $array = ( array ) $array ;
    foreach ( $array as $key => $value ){   
        //echo $key."\n";
        
        //echo "---\n";
        $value = ( array ) $value ;
        if(is_string($value)){
            $newArray [ strtolower ($key) ] = trim($value) ;
        }else if (!is_assoc($value ) && isset($value [0]) && sizeof($value)==1){
            
            $newArray [ strtolower ($key) ] = trim ( $value[0] ) ;
        }/*else if (!is_assoc($value ) && isset($value [0]) && sizeof($value)==1){
            $subArray = array();
            foreach($value as $subValue){
              $subArray[] = XML2Array ( $value , true ) ;
            }
            $newArray [ strtolower ($key) ] = $subArray ;
        }*/ else {
            //echo "AAA".$key."\n";
            //print_r($value);
            $newArray [ strtolower ($key) ] = XML2Array ( $value , true ) ;
        }
    }
    return $newArray ;
}

function DeleteDir($path)
{
    $files = glob($path.'/*.*'); // get all file names
    foreach($files as $file){ // iterate files
      if(is_file($file))
        unlink($file); // delete file
    }
    rmdir($path);
}

function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
            .chr(125);// "}"
        return $uuid;
    }
}

function urlsafe_b64encode($string) 
{ 
    $data = base64_encode($string); 
    $data = str_replace(array('+','/','='),array('-','_',''),$data); 
    return $data; 
}
function iso8601($time=false) {
    if(!$time) $time=time();
    return date("Y-m-d", $time) . 'T' . date("H:i:s", $time) .'.000000Z';
}
    