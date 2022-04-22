<?php

namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as Controller;
use App\Models\Disability;
use Illuminate\Http\Request;

class DisabilityController extends Controller
{
    
     public function showOneDisability (Request $request)
     {

        // extracting request parameters
        $id = $request->input('ORG_ID');
        $year = $request->input('Year','-1');



        // Model objects
        $disability = new Disability(); 
        //Formate for failed api response
        $response = ["status" => 'failed'];

         if($id != null){

            $result = $disability->getDocTypeCount($id);
            $uidresult = $disability->getUidCount($id);

            //Response Format
            $response['status']='success';
            $response['data'] = ['Total_Disability_Certificate'=>0, 'Total_UID_Card'=>0, 'Total_Awards'=>0];
          
         
             foreach($result as $resultline) {

                if($resultline->DOC_TYPE == 'DPICR') {      
                // Count certificate when Doc Type is DPICR
                    $response['data']['Total_Disability_Certificate'] = $resultline->aggregate;
                    $response['data']['Total_Awards'] += $resultline->aggregate;
                
                }

            }

             foreach($uidresult as $resultline){

                if($resultline->UID == 'govid'){
                // Count certificate when Doc Type is govid
                    $response['data']['Total_UID_Card'] = $resultline->aggregate;
                    $response['data']['Total_Awards'] += $resultline->aggregate;
                }
            }

             //Total Awards yearwise
             if($year != "-1" && $year != null){
                $yearwise_result = $disability->getYearwiseDocCount($id, $year);
                $yearwise_uidresult = $disability->getYearwiseUidCount($id, $year);
                // $yearwise_result = $disability->get_yearwise_data($org_id,$year);
                $response['yearwise_data'] = ['Total_Disability_Certificate'=>0, 'Total_UID_Card'=>0, 'Total_Awards'=>0];
                
                foreach($yearwise_result as $resultline) {

                    if($resultline->DOC_TYPE == 'DPICR') {      
                    // Count certificate when Doc Type is DPICR
                        $response['yearwise_data']['Total_Disability_Certificate'] = $resultline->aggregate;
                        $response['yearwise_data']['Total_Awards'] += $resultline->aggregate;
                    
                    }
    
                }
    
                 foreach($yearwise_uidresult as $resultline){
    
                    if($resultline->UID == 'govid'){
                    // Count certificate when Doc Type is govid
                        $response['yearwise_data']['Total_UID_Card'] = $resultline->aggregate;
                        $response['yearwise_data']['Total_Awards'] += $resultline->aggregate;
                    }
                }
                
            }

    }   


       return response()->json($response);
        
    }   
}