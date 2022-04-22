<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Disability extends Model 

{
    //collection name
    protected $collection = 'disabled_persons_records'; 

    //Method to get total Doc Type count on org_id
    public function getDocTypeCount($ORG_ID){

        return Disability::where('ORG_ID',$ORG_ID)->groupBy('DOC_TYPE')->aggregate('count',['DOC_TYPE'])
        ->get();
        
    
    }

    //Method to get total UDI count on org_id
    public function getUidCount($ORG_ID){

       return Disability::where('ORG_ID',$ORG_ID)->groupBy('UID')->aggregate('count',['UID'])
        ->get();
       
    }  


    public function getYearwiseDocCount($ORG_ID, $year){

        return Disability::where(['ORG_ID' => $ORG_ID, 'YEAR' =>$year])->groupBy('DOC_TYPE')->aggregate('count',['DOC_TYPE'])
        ->get();
        
    
    }

    //Method to get total UDI count on org_id
    public function getYearwiseUidCount($ORG_ID, $year){

       return Disability::where(['ORG_ID' => $ORG_ID, 'YEAR' =>$year])->groupBy('UID')->aggregate('count',['UID'])
        ->get();
       

    }  
     
}