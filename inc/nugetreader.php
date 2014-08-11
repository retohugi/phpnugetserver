<?php
if(!defined('__ROOT__')){
    define('__ROOT__',dirname( dirname(__FILE__)));
}
define('__TEMPLATE_FILE__',__ROOT__."/inc/nugetTemplate.xml");

require_once(__ROOT__."/inc/nugetentity.php");
require_once(__ROOT__."/inc/zipmanager.php");
require_once(__ROOT__."/inc/nugetdb.php");
require_once(__ROOT__."/inc/utils.php");
require_once(__ROOT__."/settings.php");
/*

The comparison function must return an integer less than, equal to, or greater than zero if 
the first argument is considered to be respectively less than, equal to, or greater than the second. 

Sort(a,b) <0   => a<b
Sort(a,b) >0   => a>b
Sort(a,b) =0   => a=b
*/
function NugetManagerSortIdVersion($a, $b)
{
    $res = strcmp($a->Identifier, $b->Identifier);
    if($res==0){
       $aVersion = explode(".",$a->Version);
       $bVersion = explode(".",$b->Version);
       for($i=0;$i<sizeof($aVersion) && $i<sizeof($bVersion);$i++){
            $res = $aVersion[$i]-$bVersion[$i];
            if($res!=0) return $res; 
       }
    }
    return $res;
}

/*
Sort(a,b) <0   => a<b
Sort(a,b) >0   => a>b
Sort(a,b) =0   => a=b
*/
function NugetManagerSortVersion($a, $b)
{
    $res = 0;
    $aVersion = explode(".",$a);
    $bVersion = explode(".",$b);
    for($i=0;$i<sizeof($aVersion) && $i<sizeof($bVersion);$i++){
        $res = $aVersion[$i]-$bVersion[$i];
        if($res!=0) return $res; 
    }
    return $res; 
}

//http://net.tutsplus.com/articles/news/how-to-open-zip-files-with-php/
class NugetManager
{
    var $template = null;
    
    public function DeleteNuspecData($e)
    {
        $nugetDb = new NuGetDb();
        
        
        $destination = __UPLOAD_DIR__."/".strtolower($e->Identifier).".".$e->Version.".nupkg";
        
        if(file_exists($destination)) unlink($destination);
        
        $nugetDb->DeleteRow($e);
        
    }
    
    public function SpecialChars($hasMap)
    {
        foreach($hasMap as $key=>$value){
	       $hasMap[$key]=trim(htmlspecialchars($value));
	    }
    }
	
	public function LoadXml($e,$m,$xml)
	{
	    $this->SpecialChars($m);
		$e->Version = $m["version"];
        $e->Identifier = $m["id"];
        $e->Title = $m["title"];
        if(sizeof($e->Title)==0 || $e->Title==""){
            $e->Title = $e->Identifier;   
        }
        $e->LicenseUrl = $m["licenseurl"];
        $e->ProjectUrl = $m["projecturl"];
        $e->RequireLicenseAcceptance = $m["requirelicenseacceptance"];
        $e->Description = $m["description"];
        $e->Tags = $m["tags"];
        $e->Author = $m["authors"];
        $e->Published = iso8601();
        $e->Copyright = $m["owners"];
	}
    
    public function LoadNuspecData($nupkgFile)
    {
        $zipmanager = new ZipManager($nupkgFile);
        $files = $zipmanager->GenerateInfos();
        $nupckgName = "";
        foreach($files["entries_name"] as $fileName)
        {
            $pinfo = pathinfo($fileName);
            if($pinfo["basename"]==$fileName){
                if(endsWith($fileName,".nuspec")){
                    $nupckgName = $fileName;
                }
            }
        }
        $nuspecContent = $zipmanager->LoadFile($nupckgName);
        
		
        $xml = XML2Array($nuspecContent);
        $e = new NugetEntity();
        $m=$xml["metadata"];
        
        $this->LoadXml($e,$m,$xml);
        /*for($i=0;$i<sizeof($ark);$i++){
            $m[strtolower ($ark[$i])]=$mt[$ark[$i]];
        }*/
        
        $e->Dependencies = $this->LoadDependencies($m);
        
        
        $e->References = $this->LoadReferences($m);
       
        $e->PackageHash = base64_encode(hash(strtolower(__PACKAGEHASH__), file_get_contents($nupkgFile),true)); //true means raw, fals means in hex
        $e->PackageHashAlgorithm = strtoupper(__PACKAGEHASH__);
        $e->PackageSize = filesize($nupkgFile);
        $e->Listed = true;
         $nugetDb = new NuGetDb();
         if($nugetDb->AddRow($e,false)){
             $destination = __UPLOAD_DIR__."/".strtolower($e->Identifier).".".$e->Version.".nupkg";
             if(file_exists($destination)){
                unlink($destination);
            }
             rename($nupkgFile,$destination);
            return $e; //$this->buildNuspecEntity($e,$template);
         }else{ return null;}
    }
    
    public function LoadAllPackagesEntries()
    {
        $nugetDb = new NuGetDb();
        $toret = $nugetDb->GetAllRows();
        
        return $toret;
    }
    
    public function BuildNuspecEntity($baseUrl,$e)
    {
         
        $t = "";
        if($this->template==null){
            $handle = fopen(__TEMPLATE_FILE__, "rb");
            $this->template = fread($handle, filesize(__TEMPLATE_FILE__));
            fclose($handle);
        }
        $t = $this->template;
        $t.="  ";
        $authors = explode(";",$e->Author);
        $author = "";
        if(sizeof($authors)>0){
            $author = "<name>".implode("</name>\n<name>",$authors)."</name>";
        }
        //print_r($e);
        $t= str_replace("\${BASEURL}",$baseUrl,$t);
        $t= str_replace("\${NUSPEC.ID}",$e->Identifier,$t);
        
        //echo $e->Id."CAZZO".$t;die();
        $t= str_replace("\${NUSPEC.IDLOWER}",strtolower($e->Identifier),$t);
        $t= str_replace("\${NUSPEC.TITLE}",$e->Title,$t);
        $t= str_replace("\${NUSPEC.VERSION}",$e->Version,$t);
        $t= str_replace("\${NUSPEC.LICENSEURL}",$e->LicenseUrl,$t);
        $t= str_replace("\${NUSPEC.PROJECTURL}",$e->ProjectUrl,$t);
        $t= str_replace("\${NUSPEC.REQUIRELICENSEACCEPTANCE}",$e->RequireLicenseAcceptance?"true":"false",$t);
        $t= str_replace("\${NUSPEC.DESCRIPTION}",$e->Description,$t);
        if($e->Tags!=""){
            $t= str_replace("\${NUSPEC.TAGS}"," ".$e->Tags." ",$t);
        }else{
            $t= str_replace("\${NUSPEC.TAGS}","",$t);
        }
        $t= str_replace("\${NUSPEC.AUTHOR}",$author,$t);
        $t= str_replace("\${DB.PUBLISHED}",$e->Published,$t);
        $t= str_replace("\${DB.PACKAGESIZE}",$e->PackageSize,$t);
        $t= str_replace("\${DB.PACKAGEHASHALGORITHM}",$e->PackageHashAlgorithm,$t);
        $t= str_replace("\${DB.PACKAGEHASH}",$e->PackageHash,$t);
       
        if(sizeof($e->Dependencies)==0){
            $t= str_replace("\${NUSPEC.DEPENDENCIES}","",$t);
        }else{
            $t= str_replace("\${NUSPEC.DEPENDENCIES}",$this->MakeDepString($e->Dependencies),$t);
        }
        $t= str_replace("\${DB.DOWNLOADCOUNT}",$e->DownloadCount,$t);
        $t= str_replace("\${DB.VERSIONDOWNLOADCOUNT}",$e->VersionDownloadCount,$t);
        $t= str_replace("\${DB.UPDATED}",$e->Published,$t);
        
        $t= str_replace("\${DB.ISABSOLUTELATESTVERSION}",$e->IsAbsoluteLatestVersion?"true":"false",$t);
        $t= str_replace("\${DB.ISLATESTVERSION}",$e->IsLatestVersion?"true":"false",$t);
        $t= str_replace("\${DB.LISTED}",$e->Listed?"true":"false",$t);
        
        $t= str_replace("\${DB.COPYRIGHT}",$e->Copyright,$t);
        //rint_r($e);die();
        return preg_replace('/<!--(.*)-->/Uis', '', $t);
    }
    
    public function IsValid($e,$c,$isPackagesById)
    {
        if($isPackagesById) return $e->Identifier==$c;
        if(stripos($e->Title,$c)!==false) return true;
        if(stripos($e->Description,$c)!==false) return true;
        if(stripos($e->Tag,$c)!==false) return true;

        if(stripos($e->Identifier,$c)!==false) return true;
        return false;              
    }  
  
    private function LoadDependencies($m)
    {
         
        $toret = array();
        if(!array_key_exists("dependencies",$m))return $toret;
        $groups = XML2ArrayGetKeyOrArray($m["dependencies"],"group");
        
        for($i=0;$i<sizeof($groups);$i++){
            $group = $groups[$i];
            
            $groupEntity = new NugetDependencyGroup();
            $groupEntity->TargetFramework = $group["@attributes"]["targetframework"];
            $dependencies = XML2ArrayGetKeyOrArray($group,"dependency");
            $groupEntity->Dependencies = array();
            for($a=0;$a<sizeof($dependencies);$a++){
                $dependency = $dependencies[$a];
                
                $dep = new NugetDependency();
                $dep->Id = $dependency["@attributes"]["id"];
                $dep->Version = $dependency["@attributes"]["version"];
                $groupEntity->Dependencies[] = $dep;
                   
            }
            
            $toret[]=$groupEntity;
        }
        
        $dependencies = XML2ArrayGetKeyOrArray($m["dependencies"],"dependency");
        for($a=0;$a<sizeof($dependencies);$a++){
            $dependency = $dependencies[$a];
            
            $dep = new NugetDependency();
            $dep->Id = $dependency["@attributes"]["id"];
            $dep->Version = $dependency["@attributes"]["version"];
            $toret[] = $dep;
               
        }
        
        return $toret;
    }
    
    private function LoadReferences($m)
    {
        $toret = array();
        if(!array_key_exists("references",$m))return $toret;
        $refs = XML2ArrayGetKeyOrArray($m["references"],"reference");
       
        for($i=0;$i<sizeof($refs);$i++){
            $ref = $refs[$i]["@attributes"]["file"];
            $toret[]= $ref;
        }
        return $toret;
    }
    
    private function MakeDepString($d)
    {
        $tora = array();
        
        //<d:Dependencies>Castle.Core:3.1.0:net40|Castle.Windsor:3.1.0:net40|Common.Logging:2.0.0:net40|Quartz:2.0.1:net40|Castle.Core:2.1.0:net20|Castle.Windsor:2.1.0:net20|Common.Logging:1.0.0:net20|Quartz:1.0.1:net20</d:Dependencies>
        for($i=0;$i<sizeof($d);$i++){
            $sd = $d[$i];
            if($sd->IsGroup){
                $fw= $this->TranslateNet($sd->TargetFramework);
                for($j=0;$j<sizeof($sd->Dependencies);$j++){
                    $sdd = $sd->Dependencies[$j];
                    $tora[]=$sdd->Id.":".$sdd->Version.":".$fw;
                }
            }else{
                $tora[]=$sd->Id.":".$sd->Version.":";
            }
        }
        //print_r($tora);die();
        return implode("|",$tora);
    }
    
    private function TranslateNet($tf)
    {
        $tf = strtolower($tf);
        switch($tf){
            case(".netframework3.5"): return "net35";
            case(".netframework4.0"): return "net40";
            case(".netframework3.0"): return "net30";
            case(".netframework2.0"): return "net20";
            case(".netframework1.0"): return "net10";
            default: return "UNKNOWN";
        }
    }
    
    public function LoadNextVersions($packages,$versions,$available)
    {
        $result = array();
        for($i=0;$i< sizeof($available);$i++){
            $sd = $available[$i];
            $packageFounded =false;
            for($j=0;$j< sizeof($packages) && $packageFounded==false;$j++){
                $sp = $packages[$j];
                $vp = $versions[$j];
                if($sd->Identifier == $sp){
                    //echo $sd->Version." XXX ".$vp." res ".NugetManagerSortIdVersion($sd->Version,$vp)."\n";
                    if(NugetManagerSortVersion($sd->Version,$vp)>0){
                        $packageFounded=true;
                    }
                }
            }
            if($packageFounded){
               $result[]=$sd; 
            }
            
        }
        //echo "AAAA".sizeof($result); die();
        return $result;
    }
    
}
?>